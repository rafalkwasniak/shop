<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Koszyk gościa trzymany w sesji (a ta w bazie — SESSION_DRIVER=database).
 * Zasada bezpieczeństwa: w sesji ląduje WYŁĄCZNIE `product_id => ilość`. Nazwę,
 * cenę i stan magazynowy pobieramy świeżo z bazy przy każdym renderze, więc
 * koszyka nie da się „zepsuć" starą ceną ani podmianą wartości w sesji.
 *
 * Koszyk jest kluczowany po `shop_id`: storefront to jeden sklep na subdomenę,
 * a klucz i tak zabezpiecza przed pomieszaniem, gdyby sesja była współdzielona.
 */
class CartService
{
    /** Sesyjny worek wszystkich koszyków: [shop_id => [product_id => qty]]. */
    private const KEY = 'carts';

    /** Kody rabatowe przyklejone do koszyków: [shop_id => 'KOD']. */
    private const DISCOUNT_KEY = 'cart_discounts';

    /** Przełącznik „Użyj punktów" per sklep — sama decyzja, nigdy kwota. */
    private const POINTS_KEY = 'cart_points';

    /**
     * Surowa zawartość koszyka sklepu: [product_id => qty]. Ilość jest ułamkowa
     * (waga: 2,50 kg) albo całkowita (sztuki). Kolejność wstawiania zachowana.
     *
     * @return array<int, float>
     */
    public function raw(int $shopId): array
    {
        return session()->get(self::KEY.'.'.$shopId, []);
    }

    /**
     * Liczba POZYCJI w koszyku (do licznika w nagłówku) — nie suma ilości, bo ta
     * miesza sztuki z kilogramami i dawałaby przy wadze ułamek („4,5"). Liczona
     * z samej sesji, bez zapytań; pełne uzgodnienie robi strona koszyka (lines()).
     */
    public function count(int $shopId): int
    {
        return count($this->raw($shopId));
    }

    /**
     * Dodaje produkt do koszyka (zwiększa ilość o $qty; domyślnie o krok jednostki
     * — 1 szt. albo 0,5 kg). Ignoruje produkt spoza sklepu, nieaktywny oraz taki,
     * którego sklep nie ma czym dostarczyć albo czym opłacić. Ilość przycinana do
     * stanu magazynowego, gdy śledzony.
     *
     * Warunek sklepu jest tu, a nie tylko w widoku: ukrycie przycisku to UX, nie
     * zabezpieczenie — komponent Livewire da się wywołać z palca.
     */
    public function add(Product $product, ?float $qty = null): void
    {
        $qty ??= $product->sale_unit->step();

        if ($product->shop_id === null || ! $product->is_active || $qty <= 0) {
            return;
        }

        if ($product->shop?->acceptsOrders() !== true) {
            return;
        }

        $current = $this->raw($product->shop_id);
        $current[$product->id] = ($current[$product->id] ?? 0) + $qty;

        $this->store($product->shop_id, $product->id, $current[$product->id], $product);
    }

    /**
     * Ustawia dokładną ilość produktu. Ilość normalizowana wg jednostki produktu
     * (waga do 2 miejsc, sztuki do całkowitej); poniżej minimum lub ≤ 0 usuwa
     * pozycję.
     */
    public function setQuantity(int $shopId, int $productId, float $qty): void
    {
        $product = Product::where('shop_id', $shopId)->where('is_active', true)->find($productId);

        if ($product === null) {
            $this->remove($shopId, $productId);

            return;
        }

        $qty = $product->sale_unit->normalizeQuantity($qty);

        if ($qty <= 0) {
            $this->remove($shopId, $productId);

            return;
        }

        $cart = $this->raw($shopId);
        $cart[$productId] = $this->capToStock($product, $qty);
        session()->put(self::KEY.'.'.$shopId, $cart);
    }

    public function remove(int $shopId, int $productId): void
    {
        $cart = $this->raw($shopId);
        unset($cart[$productId]);
        session()->put(self::KEY.'.'.$shopId, $cart);
    }

    public function clear(int $shopId): void
    {
        session()->forget(self::KEY.'.'.$shopId);
        $this->clearDiscountCode($shopId);
        $this->stopUsingPoints($shopId);
    }

    /**
     * Kod rabatowy przyklejony do koszyka. W sesji trzymamy WYŁĄCZNIE sam kod —
     * nigdy wyliczonej kwoty. Zniżkę przeliczamy przy każdym renderze z aktualnej
     * zawartości koszyka, więc nie da się jej zamrozić przez dołożenie i wyjęcie
     * produktów ani podmianę wartości w sesji (ta sama zasada co przy cenach).
     */
    public function discountCode(int $shopId): ?string
    {
        $code = session()->get(self::DISCOUNT_KEY.'.'.$shopId);

        return is_string($code) && $code !== '' ? $code : null;
    }

    public function setDiscountCode(int $shopId, string $code): void
    {
        session()->put(self::DISCOUNT_KEY.'.'.$shopId, mb_strtoupper(trim($code)));
    }

    public function clearDiscountCode(int $shopId): void
    {
        session()->forget(self::DISCOUNT_KEY.'.'.$shopId);
    }

    /**
     * Czy klient chce zapłacić punktami. Jak przy kodzie rabatowym, w sesji
     * leży WYŁĄCZNIE decyzja — ile punktów i za ile złotych, liczy przy każdym
     * renderze `LoyaltyLedger::redeemable()` z aktualnego koszyka i salda.
     */
    public function usesPoints(int $shopId): bool
    {
        return (bool) session()->get(self::POINTS_KEY.'.'.$shopId, false);
    }

    public function usePoints(int $shopId): void
    {
        session()->put(self::POINTS_KEY.'.'.$shopId, true);
    }

    public function stopUsingPoints(int $shopId): void
    {
        session()->forget(self::POINTS_KEY.'.'.$shopId);
    }

    /**
     * Nadpisuje cały koszyk sklepu podaną mapą [product_id => qty]. Używane przy
     * uzgodnieniu przed złożeniem zamówienia (finalna weryfikacja dostępności).
     *
     * @param  array<int, float>  $items
     */
    public function overwrite(int $shopId, array $items): void
    {
        if ($items === []) {
            $this->clear($shopId);

            return;
        }

        session()->put(self::KEY.'.'.$shopId, $items);
    }

    /**
     * Uzgodnione pozycje koszyka + komunikaty o dokonanych korektach — do banera
     * na stronie koszyka. Zwraca świeże produkty z bazy, ilości przycięte do stanu,
     * pomija produkty nieistniejące/nieaktywne/wyprzedane (i czyści je z sesji).
     * Treść komunikatów jest ta sama co przy składaniu zamówienia (OrderService),
     * żeby klient słyszał jeden, spójny głos na obu etapach.
     *
     * @return array{lines: Collection<int, array{product: Product, quantity: float, unit_price: float, line_total: float}>, notices: list<string>}
     */
    public function reconcile(int $shopId): array
    {
        $raw = $this->raw($shopId);

        if ($raw === []) {
            return ['lines' => collect(), 'notices' => []];
        }

        $products = Product::where('shop_id', $shopId)
            ->where('is_active', true)
            ->whereIn('id', array_keys($raw))
            ->get()
            ->keyBy('id');

        $reconciled = [];
        $notices = [];

        $lines = collect(array_keys($raw))
            ->map(function (int $productId) use ($raw, $products, &$reconciled, &$notices) {
                $product = $products->get($productId);
                $requested = (float) $raw[$productId];

                if ($product === null) {
                    $notices[] = 'Jeden lub więcej produktów nie jest już dostępnych i został usunięty z koszyka.';

                    return null;    // zdjęty/usunięty — wypadnie z koszyka
                }

                $qty = $this->capToStock($product, $requested);

                if ($qty <= 0) {
                    $notices[] = 'Produkt „'.$product->name.'" jest wyprzedany i został usunięty z koszyka.';

                    return null;    // wyprzedany — wypada
                }

                if ($qty < $requested) {
                    $notices[] = 'Ilość „'.$product->name.'" została dostosowana do dostępności ('.$product->sale_unit->formatQuantity($qty).').';
                }

                $reconciled[$productId] = $qty;
                $unit = (float) $product->price_gross;

                return [
                    'product' => $product,
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'line_total' => round($unit * $qty, 2),
                ];
            })
            ->filter()
            ->values();

        // Zapisujemy uzgodnioną wersję z powrotem, żeby licznik i sesja nie
        // trzymały pozycji, których już nie ma.
        if ($reconciled !== $raw) {
            session()->put(self::KEY.'.'.$shopId, $reconciled);
        }

        return ['lines' => $lines, 'notices' => array_values(array_unique($notices))];
    }

    /**
     * Same pozycje koszyka, bez komunikatów o korektach (cienki wrapper na
     * reconcile()). Używane tam, gdzie liczy się tylko zawartość/suma.
     *
     * @return Collection<int, array{product: Product, quantity: float, unit_price: float, line_total: float}>
     */
    public function lines(int $shopId): Collection
    {
        return $this->reconcile($shopId)['lines'];
    }

    public function total(int $shopId): float
    {
        return $this->lines($shopId)->sum('line_total');
    }

    /**
     * Zapis pojedynczej pozycji z przycięciem do stanu.
     */
    private function store(int $shopId, int $productId, float $qty, Product $product): void
    {
        $cart = $this->raw($shopId);
        $cart[$productId] = $this->capToStock($product, $qty);
        session()->put(self::KEY.'.'.$shopId, $cart);
    }

    /**
     * Ilość ograniczona stanem magazynowym, gdy produkt go śledzi. Bez śledzenia
     * (usługa/nielimitowany) ilość bez ograniczeń.
     */
    private function capToStock(Product $product, float $qty): float
    {
        if ($product->track_stock && $product->stock !== null) {
            return max(0, min($qty, (float) $product->stock));
        }

        return max(0, $qty);
    }
}
