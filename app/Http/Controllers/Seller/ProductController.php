<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ProductRequest;
use App\Models\Product;
use App\Services\ProductImageService;
use App\Services\SlugService;
use App\Services\TagNormalizer;
use App\Support\MetaDescription;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Zarządzanie produktami sklepu (krok podstawowy: bez zdjęć i tagów). Wszystko
 * scope'owane do sklepu zalogowanego sprzedawcy; edycja/usuwanie tylko własnych
 * produktów. Limit liczby produktów wynika z pakietu (config).
 */
class ProductController extends Controller
{
    /**
     * Dozwolone sortowania listy: klucz w URL (po polsku) → [kolumna, kierunek].
     * Nieznany klucz spada na pierwszy wpis („Domyślnie" = najnowsze).
     */
    private const SORTS = [
        'domyslne' => ['label' => 'Domyślnie', 'column' => 'created_at', 'direction' => 'desc'],
        'cena' => ['label' => 'Cena', 'column' => 'price_gross', 'direction' => 'asc'],
        'nazwa' => ['label' => 'Nazwa', 'column' => 'name', 'direction' => 'asc'],
    ];

    public function index(Request $request): Renderable
    {
        $shop = $request->user()->currentShop();

        $filters = $this->filters($request);
        $sortKey = $this->resolveSort($request->query('sortowanie'));

        $products = null;
        if ($shop !== null) {
            $query = $shop->products()->with('images', 'priceHistory');
            $this->applyFilters($query, $filters);

            $sort = self::SORTS[$sortKey];
            $products = $query->orderBy($sort['column'], $sort['direction'])
                ->paginate(12)
                ->withQueryString();
        }

        return view('seller.products.index', [
            'shop' => $shop,
            'products' => $products,
            // Licznik pakietu = CAŁY katalog, nie zbiór po filtrze (nie może „kłamać").
            'total' => $shop ? $shop->products()->count() : 0,
            'max' => $shop ? (int) $shop->entitlement('max_products') : 0,
            'filters' => $filters,
            'sortKey' => $sortKey,
            'sortOptions' => $this->sortOptions($sortKey),
            'tagSuggestions' => $this->tagSuggestions($request),
            'hasFilters' => $this->hasActiveFilters($filters),
            'listQuery' => $this->listQuery($request),
        ]);
    }

    public function create(Request $request): Renderable|RedirectResponse
    {
        if ($this->limitReached($request)) {
            return redirect()->route('seller.products.index')
                ->with('error', $this->limitMessage($request));
        }

        return view('seller.products.form', [
            'product' => new Product,
            'tagSuggestions' => $this->tagSuggestions($request),
            'defaultVat' => $this->defaultVat($request),
            'defaultSaleUnit' => $this->defaultSaleUnit($request),
            'homepage' => $this->homepageInfo($request),
            // Nowy produkt nie ma kontekstu listy do odtworzenia.
            'listQuery' => [],
        ]);
    }

    public function store(ProductRequest $request, ProductImageService $images): RedirectResponse
    {
        if ($this->limitReached($request)) {
            return redirect()->route('seller.products.index')->with('error', $this->limitMessage($request));
        }

        $product = $request->user()->currentShop()->products()->create($this->data($request));
        $this->syncTags($product, $request);

        // Zdjęcia wybrane przy tworzeniu — zapis w kolejności dodania (0,1,2,…).
        foreach ($request->file('images', []) as $position => $file) {
            $product->images()->create([
                'path' => $images->store($file, $product),
                'position' => $position,
            ]);
        }

        return redirect()->route('seller.products.edit', $product)->with('success', 'Produkt został dodany.');
    }

    public function edit(Request $request, Product $product): Renderable
    {
        $this->authorizeProduct($request, $product);

        return view('seller.products.form', [
            'product' => $product,
            'tagSuggestions' => $this->tagSuggestions($request),
            'defaultVat' => $this->defaultVat($request),
            'defaultSaleUnit' => $this->defaultSaleUnit($request),
            'homepage' => $this->homepageInfo($request),
            // Kontekst listy (filtry + sort + strona) z query stringa — do odtworzenia
            // „Wróć do listy" i akcji zapisu, żeby wrócić na tę samą, przefiltrowaną stronę.
            'listQuery' => $this->listQuery($request),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        $product->update($this->data($request));
        $this->syncTags($product, $request);

        // Zachowujemy pełny kontekst listy (filtry + sort + strona), z którego przyszedł
        // sprzedawca — akcja formularza niesie go w query stringu.
        $params = ['product' => $product] + $this->listQuery($request);

        return redirect()->route('seller.products.edit', $params)->with('success', 'Zapisano zmiany w produkcie.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        if ($product->hasBeenOrdered()) {
            // Zamówiony — zachowujemy dla historii zamówień i dokumentów (soft-delete).
            $product->delete();
        } else {
            // Nigdy niezamówiony — trwałe usunięcie i sprzątanie (rekordy + pliki).
            $product->purge();
        }

        return redirect()->route('seller.products.index')->with('success', 'Produkt został usunięty.');
    }

    /**
     * Dane do zapisu: stan ma znaczenie tylko przy włączonej kontroli stanu.
     *
     * @return array<string, mixed>
     */
    private function data(ProductRequest $request): array
    {
        $data = $request->safe()->except(['tags', 'images']);
        $data['stock'] = $data['track_stock'] ? ($data['stock'] ?? 0) : null;

        // Opis SEO wpisany ręcznie należy do sprzedawcy — znacznik pilnuje, żeby
        // automat go nie nadpisał. Wyczyszczenie pola oddaje kontrolę automatowi.
        // (array_merge, nie `+=` — unia tablic NIE nadpisuje istniejącego klucza
        // `meta_description`, więc przycięta wartość by nie weszła.)
        $data = array_merge($data, MetaDescription::fields($data['meta_description'] ?? null));

        // Stan na sztuki to liczba całkowita; na wagę zostaje ułamkiem (2,50 kg).
        if ($data['stock'] !== null && ($data['sale_unit'] ?? 'piece') === \App\Enums\SaleUnit::Piece->value) {
            $data['stock'] = (int) round((float) $data['stock']);
        }

        return $data;
    }

    /**
     * Parsuje tagi z pola (po przecinku), tworzy brakujące w obrębie sklepu
     * (deduplikacja po slug) i przypina do produktu.
     */
    private function syncTags(Product $product, ProductRequest $request): void
    {
        $shop = $product->shop;

        $ids = collect(app(TagNormalizer::class)->parse($request->input('tags')))
            ->map(fn (string $name): int => $shop->tags()->firstOrCreate(
                ['name' => $name],
                ['slug' => app(SlugService::class)->make($name)],
            )->id)
            ->all();

        $product->tags()->sync($ids);

        // Edycja mogła odpiąć ostatni produkt od tagu — sprzątamy osierocone.
        $shop->pruneOrphanTags();
    }

    /**
     * Podpowiedzi tagów: własne tagi sklepu mające ≥1 produkt, najczęściej używane
     * na górze (przy remisie alfabetycznie). Osierocone tagi nie wchodzą.
     *
     * @return array<int, string>
     */
    private function tagSuggestions(Request $request): array
    {
        return $request->user()->currentShop()
            ?->tags()
            ->has('products')
            ->withCount('products')
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->pluck('name')
            ->all() ?? [];
    }

    /**
     * Domyślna stawka VAT do prefillu nowego produktu — z ustawień sklepu.
     */
    private function defaultVat(Request $request): string
    {
        return $request->user()->currentShop()?->default_vat_rate?->value ?? '23';
    }

    /**
     * Domyślna jednostka sprzedaży do prefillu nowego produktu — z ustawień sklepu.
     */
    private function defaultSaleUnit(Request $request): string
    {
        return $request->user()->currentShop()?->default_sale_unit?->value ?? 'piece';
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless($product->shop_id === $request->user()->currentShop()?->id, 403);
    }

    /**
     * Zajętość miejsc na stronie głównej (ile wyróżnionych / sufit) — do wskazówki
     * na formularzu produktu. Sufit dla wszystkich pakietów (config/shop.php).
     *
     * @return array{count: int, limit: int}
     */
    private function homepageInfo(Request $request): array
    {
        $shop = $request->user()->currentShop();

        return [
            'count' => $shop ? $shop->products()->where('show_on_homepage', true)->count() : 0,
            'limit' => (int) config('shop.homepage_promoted_limit'),
        ];
    }

    private function limitReached(Request $request): bool
    {
        $shop = $request->user()->currentShop();

        return $shop !== null && $shop->products()->count() >= (int) $shop->entitlement('max_products');
    }

    private function limitMessage(Request $request): string
    {
        $shop = $request->user()->currentShop();

        return 'Pakiet '.$shop->effectivePackageName().' pozwala na maksymalnie '.(int) $shop->entitlement('max_products').' produktów.';
    }

    /**
     * Filtry listy z query stringa, znormalizowane. Ceny akceptują przecinek lub
     * kropkę; puste/nieprawidłowe → brak filtra.
     *
     * @return array{cena_od: float|null, cena_do: float|null, szukaj: string, tag: string}
     */
    private function filters(Request $request): array
    {
        return [
            'cena_od' => $this->parsePrice($request->query('cena_od')),
            'cena_do' => $this->parsePrice($request->query('cena_do')),
            'szukaj' => $this->stringQuery($request, 'szukaj'),
            'tag' => $this->stringQuery($request, 'tag'),
        ];
    }

    /**
     * Nakłada aktywne filtry na zapytanie o produkty. Szukanie po nazwie i opisie
     * (znaki specjalne LIKE ekranowane); tag po dokładnej nazwie (z podpowiedzi).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Product>  $query
     * @param  array{cena_od: float|null, cena_do: float|null, szukaj: string, tag: string}  $f
     */
    private function applyFilters($query, array $f): void
    {
        if ($f['cena_od'] !== null) {
            $query->where('price_gross', '>=', $f['cena_od']);
        }

        if ($f['cena_do'] !== null) {
            $query->where('price_gross', '<=', $f['cena_do']);
        }

        if ($f['szukaj'] !== '') {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['szukaj']).'%';
            $query->where(fn ($q) => $q
                ->where('name', 'like', $term)
                ->orWhere('description', 'like', $term));
        }

        if ($f['tag'] !== '') {
            $query->whereHas('tags', fn ($q) => $q->where('name', $f['tag']));
        }
    }

    /**
     * Kontekst listy (filtry + sort + strona) jako parametry query — jedno źródło
     * dla linku edycji, akcji zapisu i „Wróć do listy", żeby po edycji wracać na tę
     * samą, przefiltrowaną stronę. Domyślne/puste pomijamy (czysty URL).
     *
     * @return array<string, string|int>
     */
    private function listQuery(Request $request): array
    {
        $filters = $this->filters($request);
        $sortKey = $this->resolveSort($request->query('sortowanie'));
        $page = (int) $request->query('page', 1);

        return array_filter([
            'sortowanie' => $sortKey !== array_key_first(self::SORTS) ? $sortKey : null,
            'cena_od' => $filters['cena_od'],
            'cena_do' => $filters['cena_do'],
            'szukaj' => $filters['szukaj'] !== '' ? $filters['szukaj'] : null,
            'tag' => $filters['tag'] !== '' ? $filters['tag'] : null,
            'page' => $page > 1 ? $page : null,
        ], fn ($v): bool => $v !== null);
    }

    /**
     * Czy jakikolwiek filtr jest aktywny (do pokazania „Wyczyść").
     *
     * @param  array{cena_od: float|null, cena_do: float|null, szukaj: string, tag: string}  $f
     */
    private function hasActiveFilters(array $f): bool
    {
        return $f['cena_od'] !== null
            || $f['cena_do'] !== null
            || $f['szukaj'] !== ''
            || $f['tag'] !== '';
    }

    /**
     * Normalizuje cenę z pola filtra: spacje out, przecinek → kropka. Ujemne i
     * nienumeryczne (oraz tablice z URL) → null (brak filtra).
     */
    private function parsePrice(mixed $raw): ?float
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
     * Wartość tekstowa z query stringa, przycięta; tablice/braki → pusty string.
     */
    private function stringQuery(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? trim($value) : '';
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
