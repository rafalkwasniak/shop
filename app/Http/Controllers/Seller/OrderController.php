<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shop;
use App\Services\Shipping\CourierPickup;
use App\Services\Shipping\ShipxClient;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Zamówienia sklepu w panelu sprzedawcy. Wszystko scope'owane do sklepu
 * zalogowanego sprzedawcy; sprzedawca widzi wyłącznie własne zamówienia.
 * Dane zamówienia to migawka z chwili złożenia (OrderService) — tu tylko
 * je pokazujemy i zmieniamy status.
 */
class OrderController extends Controller
{
    /**
     * Dozwolone sortowania listy: klucz w URL (po polsku) → [kolumna, kierunek].
     * Nieznany klucz spada na pierwszy wpis („Domyślnie" = najnowsze).
     */
    private const SORTS = [
        'domyslne' => ['label' => 'Domyślnie', 'column' => 'created_at', 'direction' => 'desc'],
        'kwota' => ['label' => 'Kwota', 'column' => 'total_gross', 'direction' => 'asc'],
    ];

    public function index(Request $request): Renderable
    {
        $shop = $request->user()->currentShop();

        $filters = $this->filters($request);
        $sortKey = $this->resolveSort($request->query('sortowanie'));

        $orders = null;
        $total = 0;
        $productOptions = [];
        $stats = ['orders' => 0, 'revenue' => 0.0, 'products' => 0.0];

        if ($shop !== null) {
            // Wejście na listę = „przejrzane": zerujemy powiadomienie o nowych
            // zamówieniach. Zerujemy na już-załadowanej instancji, więc badge w
            // nawigacji gaśnie od razu na tej stronie. Zapis tylko gdy jest co gasić.
            if ($shop->unseen_orders_count > 0) {
                $shop->forceFill(['unseen_orders_count' => 0])->save();
            }

            $total = $shop->orders()->count();
            $productOptions = $this->productOptions($shop);

            $sort = self::SORTS[$sortKey];
            $orders = $this->filteredOrders($shop, $filters)
                ->withCount('items')
                ->orderBy($sort['column'], $sort['direction'])
                ->orderByDesc('id') // stabilny tie-break (równe kwoty/sekundy)
                ->paginate(10)
                ->withQueryString();

            // Statystyki z CAŁEGO przefiltrowanego zbioru (wszystkie strony, nie tylko
            // bieżąca) — „Twoja sprzedaż" pokazuje to, co realnie wybrały filtry.
            // Anulowane odpadają (`countedAsSale`), mimo że LISTA je pokazuje: to
            // karta sprzedaży, a anulowane zamówienie zakupem nie jest. Dlatego
            // liczba zamówień to własne zapytanie, a nie `$orders->total()` z
            // paginatora — ten liczy wiersze listy, więc razem z anulowanymi.
            $orderIds = $this->filteredOrders($shop, $filters)->countedAsSale()->pluck('orders.id');
            $stats = [
                'orders' => $this->filteredOrders($shop, $filters)->countedAsSale()->count(),
                'revenue' => (float) $this->filteredOrders($shop, $filters)->countedAsSale()->sum('total_gross'),
                'products' => (float) DB::table('order_items')->whereIn('order_id', $orderIds)->sum('quantity'),
            ];
        }

        return view('seller.orders.index', [
            'shop' => $shop,
            'orders' => $orders,
            'total' => $total,
            'filters' => $filters,
            'sortKey' => $sortKey,
            'sortOptions' => $this->sortOptions($sortKey),
            'productOptions' => $productOptions,
            'stats' => $stats,
            'hasFilters' => $this->hasActiveFilters($filters),
            'listQuery' => $this->listQuery($request),
            // Paczki zadeklarowane do odbioru przez kuriera, na które nikt
            // jeszcze nie zamówił przyjazdu. Liczymy tylko przy włączonym
            // nadawaniu — bez integracji nie ma czego odbierać. Zapytanie idzie
            // po indeksowanym `shipment_id`, więc jest tanie.
            'awaitingPickup' => $shop->shipxEnabled()
                ? app(CourierPickup::class)->awaiting($shop)->count()
                : 0,
        ]);
    }

    public function show(Request $request, Order $order): Renderable
    {
        $this->authorizeOrder($request, $order);

        return view('seller.orders.show', [
            'order' => $order->load('items'),
            // Kontekst listy (filtry + sort + strona) z query stringa — do „Wróć do listy".
            'listQuery' => $this->listQuery($request),
        ]);
    }

    /**
     * Etykieta przesyłki w PDF. Pobieramy ją z InPostu po stronie serwera i
     * podajemy dalej jako plik — token ShipX nie ma prawa trafić do przeglądarki.
     * Nie zapisujemy PDF-a u siebie: etykieta jest zawsze dostępna w API, a
     * kopia oznaczałaby kolejne pliki do backupu i usuwania wraz ze sklepem.
     */
    public function label(Request $request, Order $order, ShipxClient $shipx): Response
    {
        $this->authorizeOrder($request, $order);

        abort_unless($order->isShipmentReady(), 404);

        $pdf = $shipx->label($order->shop, (int) $order->shipment_id);

        abort_if($pdf === null, 404);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="etykieta-'.$order->number.'.pdf"',
        ]);
    }

    /**
     * Tylko własne zamówienia sklepu zalogowanego sprzedawcy.
     */
    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless($order->shop_id === $request->user()->currentShop()?->id, 403);
    }

    /**
     * Świeże, przefiltrowane zapytanie o zamówienia sklepu — wołane wielokrotnie
     * (paginacja + osobne agregaty), więc za każdym razem budujemy je od nowa.
     *
     * @param  array{status: string, data_od: string, data_do: string, kwota_od: float|null, kwota_do: float|null, produkt: int|null}  $filters
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Order, Shop>
     */
    private function filteredOrders(Shop $shop, array $filters)
    {
        $query = $shop->orders();
        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Filtry listy z query stringa, znormalizowane. Daty w formacie Y-m-d; kwoty
     * akceptują przecinek lub kropkę; produkt to id. Puste/nieprawidłowe → brak filtra.
     *
     * @return array{status: string, data_od: string, data_do: string, kwota_od: float|null, kwota_do: float|null, produkt: int|null}
     */
    private function filters(Request $request): array
    {
        return [
            'status' => $this->parseStatus($request->query('status')),
            'data_od' => $this->parseDate($request->query('data_od')),
            'data_do' => $this->parseDate($request->query('data_do')),
            'kwota_od' => $this->parseAmount($request->query('kwota_od')),
            'kwota_do' => $this->parseAmount($request->query('kwota_do')),
            'produkt' => $this->parseProduct($request->query('produkt')),
        ];
    }

    /**
     * Nakłada aktywne filtry na zapytanie. Daty po dacie utworzenia (włącznie);
     * kwota po wartości brutto; produkt po pozycji zamówienia z danym product_id.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\HasMany<Order, Shop>  $query
     * @param  array{status: string, data_od: string, data_do: string, kwota_od: float|null, kwota_do: float|null, produkt: int|null}  $f
     */
    private function applyFilters($query, array $f): void
    {
        if ($f['status'] !== '') {
            $query->where('status', $f['status']);
        }

        if ($f['data_od'] !== '') {
            $query->whereDate('created_at', '>=', $f['data_od']);
        }

        if ($f['data_do'] !== '') {
            $query->whereDate('created_at', '<=', $f['data_do']);
        }

        if ($f['kwota_od'] !== null) {
            $query->where('total_gross', '>=', $f['kwota_od']);
        }

        if ($f['kwota_do'] !== null) {
            $query->where('total_gross', '<=', $f['kwota_do']);
        }

        if ($f['produkt'] !== null) {
            $query->whereHas('items', fn ($q) => $q->where('product_id', $f['produkt']));
        }
    }

    /**
     * Czy jakikolwiek filtr jest aktywny (do „Wyczyść" i podpisu statystyk).
     *
     * @param  array{status: string, data_od: string, data_do: string, kwota_od: float|null, kwota_do: float|null, produkt: int|null}  $f
     */
    private function hasActiveFilters(array $f): bool
    {
        return $f['status'] !== ''
            || $f['data_od'] !== ''
            || $f['data_do'] !== ''
            || $f['kwota_od'] !== null
            || $f['kwota_do'] !== null
            || $f['produkt'] !== null;
    }

    /**
     * Produkty do selecta filtra — tylko te faktycznie występujące w zamówieniach
     * sklepu (nazwa = migawka z pozycji). Osierocone (product_id NULL po twardym
     * usunięciu produktu) nie wchodzą.
     *
     * @return array<int, string>  [product_id => nazwa]
     */
    private function productOptions(Shop $shop): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.shop_id', $shop->id)
            ->whereNull('orders.deleted_at')
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->select('order_items.product_id', DB::raw('MAX(order_items.name) as name'))
            ->orderBy('name')
            ->pluck('name', 'order_items.product_id')
            ->all();
    }

    /**
     * Kontekst listy (filtry + sort + strona) jako parametry query — jedno źródło
     * dla linku do zamówienia i „Wróć do listy", żeby po podglądzie wracać na tę
     * samą, przefiltrowaną stronę. Domyślne/puste pomijamy (czysty URL).
     *
     * @return array<string, string|int|float>
     */
    private function listQuery(Request $request): array
    {
        $filters = $this->filters($request);
        $sortKey = $this->resolveSort($request->query('sortowanie'));
        $page = (int) $request->query('page', 1);

        return array_filter([
            'sortowanie' => $sortKey !== array_key_first(self::SORTS) ? $sortKey : null,
            'status' => $filters['status'] !== '' ? $filters['status'] : null,
            'data_od' => $filters['data_od'] !== '' ? $filters['data_od'] : null,
            'data_do' => $filters['data_do'] !== '' ? $filters['data_do'] : null,
            'kwota_od' => $filters['kwota_od'],
            'kwota_do' => $filters['kwota_do'],
            'produkt' => $filters['produkt'],
            'page' => $page > 1 ? $page : null,
        ], fn ($v): bool => $v !== null);
    }

    /**
     * Status z URL sprowadzony do wartości enuma (whitelist). Nieznany/tablica → ''.
     */
    private function parseStatus(mixed $raw): string
    {
        return is_string($raw) && OrderStatus::tryFrom($raw) !== null ? $raw : '';
    }

    /**
     * Waliduje datę z URL do formatu Y-m-d (round-trip). Śmieci/tablice → ''.
     */
    private function parseDate(mixed $raw): string
    {
        if (! is_string($raw)) {
            return '';
        }

        $raw = trim($raw);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

        return ($date !== false && $date->format('Y-m-d') === $raw) ? $raw : '';
    }

    /**
     * Normalizuje kwotę z pola filtra: spacje out, przecinek → kropka. Ujemne,
     * nienumeryczne oraz tablice z URL → null (brak filtra).
     */
    private function parseAmount(mixed $raw): ?float
    {
        if (! is_string($raw) && ! is_int($raw) && ! is_float($raw)) {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', '.'], trim((string) $raw));

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        $value = (float) $normalized;

        return $value >= 0 ? $value : null;
    }

    /**
     * Id produktu z URL: dodatnia liczba całkowita albo null. Zamówienia i tak są
     * scope'owane do sklepu, więc obce id po prostu nic nie znajdzie.
     */
    private function parseProduct(mixed $raw): ?int
    {
        if (! is_string($raw) && ! is_int($raw)) {
            return null;
        }

        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }

    /**
     * Sprowadza wartość z URL do dozwolonego klucza sortowania (whitelist).
     */
    private function resolveSort(mixed $key): string
    {
        return is_string($key) && isset(self::SORTS[$key]) ? $key : array_key_first(self::SORTS);
    }

    /**
     * Opcje sortowania dla selecta: klucz, etykieta, flaga aktywności.
     *
     * @return list<array{key: string, label: string, active: bool}>
     */
    private function sortOptions(string $current): array
    {
        return collect(self::SORTS)->map(fn (array $sort, string $key): array => [
            'key' => $key,
            'label' => $sort['label'],
            'active' => $key === $current,
        ])->values()->all();
    }
}
