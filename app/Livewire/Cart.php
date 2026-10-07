<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use App\Services\DiscountResolver;
use App\Services\LoyaltyLedger;
use App\Support\DiscountResult;
use App\Support\Money;
use Livewire\Component;

/**
 * Strona koszyka: lista pozycji, zmiana ilości (+/−), wpisywanie z palca,
 * usuwanie i suma. Stan trzyma CartService (sesja); komponent tylko go
 * modyfikuje i re-renderuje. Krok +/− zależy od jednostki produktu (1 szt.
 * albo 0,5 kg). Każda zmiana rozgłasza `cart-updated`, by licznik nadążał.
 */
class Cart extends Component
{
    public int $shopId;

    /** Kod wpisywany w polu „Mam kod rabatowy" (nie: kod już zastosowany). */
    public string $discountInput = '';

    public ?string $discountError = null;

    /** Liczba punktów wpisywana w karcie „Twoje punkty" (nie: już zastosowana). */
    public string $pointsInput = '';

    public ?string $pointsError = null;

    /** Czy klient zmienia zastosowaną liczbę punktów („Zmień"). */
    public bool $editingPoints = false;

    public function mount(int $shopId): void
    {
        $this->shopId = $shopId;
    }

    public function increment(int $productId): void
    {
        $product = $this->product($productId);

        if ($product === null) {
            return;
        }

        $cart = app(CartService::class);
        $current = (float) ($cart->raw($this->shopId)[$productId] ?? 0);
        $cart->setQuantity($this->shopId, $productId, $current + $product->sale_unit->step());
        $this->dispatch('cart-updated');
    }

    public function decrement(int $productId): void
    {
        $product = $this->product($productId);

        if ($product === null) {
            return;
        }

        $cart = app(CartService::class);
        $current = (float) ($cart->raw($this->shopId)[$productId] ?? 0);
        $step = $product->sale_unit->step();

        // „−" schodzi tylko do minimum (1 szt. / 0,5 kg) — poniżej jest KOSZ,
        // żeby dwuklik nie skasował pozycji przez przypadek.
        if ($current - $step >= $product->sale_unit->minQuantity()) {
            $cart->setQuantity($this->shopId, $productId, $current - $step);
            $this->dispatch('cart-updated');
        }
    }

    /**
     * Ilość wpisana z palca (pole w koszyku). Parsujemy polski zapis (przecinek,
     * spacje); CartService normalizuje wg jednostki, przycina do stanu i usuwa
     * pozycję, gdy zejdzie poniżej minimum.
     */
    public function updateQuantity(int $productId, string $value): void
    {
        $qty = (float) str_replace([' ', "\u{a0}", ','], ['', '', '.'], trim($value));

        app(CartService::class)->setQuantity($this->shopId, $productId, $qty);
        $this->dispatch('cart-updated');
    }

    public function remove(int $productId): void
    {
        app(CartService::class)->remove($this->shopId, $productId);
        $this->dispatch('cart-updated');
    }

    /**
     * Wpisany kod rabatowy. Przyklejamy go do koszyka TYLKO gdy naprawdę działa —
     * kod odrzucony zostaje w polu razem z powodem, żeby klient mógł go poprawić,
     * a nie zgadywać, czy „się zapisał".
     */
    public function applyDiscount(): void
    {
        $shop = Shop::find($this->shopId);

        if ($shop === null) {
            return;
        }

        $result = app(DiscountResolver::class)->resolve(
            $shop,
            $this->discountInput,
            app(CartService::class)->lines($this->shopId),
            auth('customer')->user(),
        );

        if (! $result->accepted()) {
            $this->discountError = $result->error;

            return;
        }

        app(CartService::class)->setDiscountCode($this->shopId, $result->code->code);
        $this->discountInput = '';
        $this->discountError = null;
    }

    public function removeDiscount(): void
    {
        app(CartService::class)->clearDiscountCode($this->shopId);
        $this->discountError = null;
    }

    /** „Wykorzystaj wszystkie" — tyle, ile wolno w tym koszyku. */
    public function usePoints(): void
    {
        if ($this->customer() !== null) {
            app(CartService::class)->usePoints($this->shopId);
            $this->resetPointsForm();
        }
    }

    /**
     * Własna liczba punktów. Więcej niż wolno — przytniemy przy renderze i
     * powiemy o tym; mniej niż minimum sklepu — odmowa od razu, z liczbą.
     */
    public function applyPoints(): void
    {
        $customer = $this->customer();
        $shop = Shop::find($this->shopId);

        if ($customer === null || $shop === null) {
            return;
        }

        $raw = str_replace([' ', "\u{a0}"], '', trim($this->pointsInput));

        if (! preg_match('/^\d+$/', $raw) || (int) $raw <= 0) {
            $this->pointsError = 'Wpisz liczbę punktów, np. 500.';

            return;
        }

        $minimum = $shop->loyalty_min_redeem_points;

        if ($minimum && (int) $raw < $minimum) {
            $this->pointsError = 'Najmniej możesz wykorzystać '.$minimum.' pkt.';

            return;
        }

        app(CartService::class)->usePoints($this->shopId, (int) $raw);
        $this->resetPointsForm();
    }

    public function changePoints(): void
    {
        $this->editingPoints = true;
        $this->pointsInput = (string) (app(CartService::class)->pointsChoice($this->shopId) ?? '');
        $this->pointsError = null;
    }

    public function stopUsingPoints(): void
    {
        app(CartService::class)->stopUsingPoints($this->shopId);
        $this->resetPointsForm();
    }

    private function resetPointsForm(): void
    {
        $this->pointsInput = '';
        $this->pointsError = null;
        $this->editingPoints = false;
    }

    /**
     * Zalogowany klient TEGO sklepu — tylko on może płacić punktami (gość
     * zbiera je na e-mail, ale wydaje dopiero po rejestracji).
     */
    private function customer(): ?Customer
    {
        $customer = auth('customer')->user();

        return $customer instanceof Customer && $customer->shop_id === $this->shopId ? $customer : null;
    }

    /**
     * Aktywny produkt tego sklepu (dla kroku/jednostki), lub null gdy zdjęty.
     */
    private function product(int $productId): ?Product
    {
        return Product::where('shop_id', $this->shopId)->where('is_active', true)->find($productId);
    }

    public function render()
    {
        // Jedno uzgodnienie na render: pozycje + komunikaty o korektach (spadł
        // stan / produkt zniknął), żeby klient nie zobaczył cicho zmienionego
        // koszyka bez wyjaśnienia.
        ['lines' => $lines, 'notices' => $notices] = app(CartService::class)->reconcile($this->shopId);

        // Przyklejony kod sprawdzamy PRZY KAŻDYM renderze, z aktualnym koszykiem.
        // Dzięki temu zniżka sama znika, gdy klient zejdzie poniżej progu albo
        // wyjmie produkt, którego kod dotyczył — i sama wraca, gdy dołoży.
        // Kody to funkcja płatna (Pawilon). Sklep bez uprawnienia nie pokazuje
        // nawet pola — obiecywanie zniżek, których nie ma jak wystawić, jest
        // gorsze niż brak pola.
        $discountsEnabled = (bool) Shop::find($this->shopId)?->entitlement('discount_codes');

        $discount = $discountsEnabled ? $this->resolveStoredDiscount($lines) : null;
        $itemsTotal = (float) $lines->sum('line_total');
        $itemsDiscount = $discount?->accepted() ? $discount->itemsDiscount : 0.0;

        // Punkty: liczone od tego, co zostało po kodzie, przy każdym renderze —
        // w sesji jest tylko decyzja „użyj". Karta jest, gdy klient MA punkty,
        // także w sklepie, który je wyłączył (zebrane wydaje się do końca
        // ważności). Gość dostaje tylko zaproszenie do logowania.
        $shop = Shop::find($this->shopId);
        $customer = $this->customer();
        $redemption = $customer !== null && $shop !== null
            ? app(LoyaltyLedger::class)->redeemable($shop, $customer->email, $itemsTotal - $itemsDiscount, app(CartService::class)->pointsChoice($this->shopId))
            : null;
        $pointsApplied = $redemption?->usable() && app(CartService::class)->usesPoints($this->shopId);
        $pointsDiscount = $pointsApplied ? $redemption->amount : 0.0;

        return view('livewire.cart', [
            'lines' => $lines,
            'itemsTotal' => $itemsTotal,
            'discountsEnabled' => $discountsEnabled,
            'discount' => $discount,
            'discountCode' => $discountsEnabled ? app(CartService::class)->discountCode($this->shopId) : null,
            'discountIssue' => $this->discountIssue($discount),
            'discountNote' => $this->discountNote($discount),
            'loyalty' => $redemption !== null && $redemption->balance > 0 ? $redemption : null,
            'loyaltyInvite' => $customer === null && $shop?->loyaltyActive(),
            'pointsApplied' => $pointsApplied,
            'pointsDiscount' => $pointsDiscount,
            'total' => round($itemsTotal - $itemsDiscount - $pointsDiscount, 2),
            'notices' => $notices,
        ]);
    }

    /**
     * Powód, dla którego kod nie działa — z wpisania („nie znamy takiego kodu")
     * albo z ponownego sprawdzenia przyklejonego kodu („koszyk zszedł poniżej
     * progu"). Dla klienta to ta sama sytuacja, więc i komunikat jest jeden.
     */
    private function discountIssue(?DiscountResult $discount): ?string
    {
        return $this->discountError
            ?? ($discount !== null && ! $discount->accepted() ? $discount->error : null);
    }

    /**
     * Potwierdzenie, że kod zadziałał — pokazywane w tym samym miejscu i w tej
     * samej ramce co powód odmowy; różni je tylko ikona.
     */
    private function discountNote(?DiscountResult $discount): ?string
    {
        if ($this->discountIssue($discount) !== null || $discount === null || ! $discount->accepted()) {
            return null;
        }

        return match (true) {
            $discount->freeShipping => 'Darmowa wysyłka — uwzględnimy ją w kasie.',
            $discount->itemsDiscount > 0 => 'Zniżka '.Money::pln($discount->itemsDiscount).' — już policzona poniżej.',
            default => null,
        };
    }

    /**
     * Wynik dla kodu zapisanego w sesji (null, gdy żadnego nie ma). Kodu, który
     * przestał działać, NIE odklejamy po cichu — pokazujemy powód, bo klient
     * zwykle może go przywrócić, dokładając coś do koszyka.
     *
     * @param  \Illuminate\Support\Collection<int, array{product: Product, quantity: float, unit_price: float, line_total: float}>  $lines
     */
    private function resolveStoredDiscount(\Illuminate\Support\Collection $lines): ?DiscountResult
    {
        $code = app(CartService::class)->discountCode($this->shopId);
        $shop = $code !== null ? Shop::find($this->shopId) : null;

        if ($code === null || $shop === null) {
            return null;
        }

        return app(DiscountResolver::class)->resolve($shop, $code, $lines, auth('customer')->user());
    }
}
