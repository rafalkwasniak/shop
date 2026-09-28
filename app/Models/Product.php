<?php

namespace App\Models;

use App\Enums\SaleUnit;
use App\Enums\VatRate;
use App\Observers\ProductObserver;
use App\Support\OmnibusPrice;
use Carbon\CarbonInterface;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Produkt sklepu. Cena `price_gross` jest brutto; netto i kwotę VAT wyliczamy
 * z brutto i ułamka stawki. `track_stock` decyduje, czy `stock` ma znaczenie.
 * Zmiany produktu napędzają widoczność sklepu (ProductObserver).
 */
#[ObservedBy(ProductObserver::class)]
#[Fillable([
    'name', 'slug', 'description', 'price_gross', 'vat_rate',
    'track_stock', 'stock', 'sale_unit', 'is_active', 'show_on_homepage',
    'withdrawal_excluded', 'meta_description', 'meta_description_manual',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vat_rate' => VatRate::class,
            'sale_unit' => SaleUnit::class,
            'price_gross' => 'decimal:2',
            'track_stock' => 'boolean',
            'withdrawal_excluded' => 'boolean',
            'meta_description_manual' => 'boolean',
            'is_active' => 'boolean',
            // Ukryty PRZEZ SYSTEM (zamek limitu po wygaśnięciu abonamentu).
            // Nie jest mass-assignable — ustawia go wyłącznie zamek.
            'auto_hidden_at' => 'datetime',
            'show_on_homepage' => 'boolean',
            'stock' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Czy produkt faktycznie podlega kontroli stanu. Jedno źródło tej bramki dla
     * koszyka, składania, edycji i zwrotu przy anulowaniu — sam `track_stock` nie
     * wystarcza, bo bez `stock` nie ma czego liczyć.
     */
    public function tracksStock(): bool
    {
        return $this->track_stock && $this->stock !== null;
    }

    /**
     * Czy produkt podlega prawu odstąpienia od umowy (14 dni bez podania
     * przyczyny). Pytamy o to zdanie twierdząco, bo tak brzmi zasada — wyjątki
     * z art. 38 (kwiaty, żywność, rękodzieło na zamówienie) są odstępstwem,
     * które sprzedawca zaznacza świadomie.
     */
    public function isWithdrawable(): bool
    {
        return ! $this->withdrawal_excluded;
    }

    /**
     * Zdjęcia produktu w kolejności (najniższa pozycja = główne).
     *
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Zdjęcie główne (o najniższej pozycji) albo null.
     */
    public function mainImage(): ?ProductImage
    {
        return $this->images->first();
    }

    /**
     * Kanoniczny adres produktu na storefroncie: /produkt/{id}-{slug} (styl
     * PrestaShop — szukamy po ID, slug to ozdoba SEO). Względny, bo storefront
     * to jeden host; unika parametru {shop} przy generowaniu URLi.
     */
    public function storefrontPath(): string
    {
        return '/produkt/'.$this->id.'-'.$this->slug;
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Pozycje zamówień, w których ten produkt wystąpił. Relacja historyczna —
     * pozycja niesie własną migawkę nazwy i ceny, a `product_id` służy do
     * powiązania z katalogiem (bestsellery w analityce, flaga zwrotów art. 38).
     *
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Czy produkt wystąpił kiedykolwiek w zamówieniu — decyduje o tym, czy przy
     * usuwaniu wolno go skasować trwale, czy tylko miękko.
     *
     * Liczą się TAKŻE zamówienia anulowane: one również są historią, a `purge()`
     * zrywa powiązanie pozycji z katalogiem (FK `nullOnDelete`), przez co pozycja
     * przestaje wiedzieć, jakiego produktu dotyczyła.
     */
    public function hasBeenOrdered(): bool
    {
        return $this->orderItems()->exists();
    }

    /**
     * Co się stanie po usunięciu tego produktu — po jednym zdaniu na wiersz,
     * do potwierdzenia w panelu.
     *
     * Zdania są dwa, bo `ProductController::destroy()` robi dwie różne rzeczy:
     * produkt, który był zamawiany, zostaje ukryty (historia zamówień i
     * dokumenty muszą go widzieć), a nigdy niezamawiany znika razem ze
     * zdjęciami. Okienko przeglądarki nie umiało tego powiedzieć — stąd ta
     * treść siedzi tutaj, a nie w widoku: jest jedna i daje się przetestować.
     *
     * Lista produktów dokłada `order_items_exists` jednym podzapytaniem
     * (`withExists`), więc na liście nie ma pytania na kafelek; poza listą
     * spadamy na zwykłe sprawdzenie.
     *
     * @return list<string>
     */
    public function deletionConsequences(): array
    {
        $ordered = $this->getAttribute('order_items_exists');
        $ordered = $ordered === null ? $this->hasBeenOrdered() : (bool) $ordered;

        return $ordered
            ? [
                'Produkt był zamawiany, więc zostanie ukryty, a nie skasowany — zamówienia i dokumenty muszą go dalej widzieć.',
                'Zniknie z katalogu i ze sklepu.',
            ]
            : [
                'Produkt nigdy nie był zamawiany, więc zniknie na zawsze, razem ze zdjęciami.',
                'Tej operacji nie da się cofnąć.',
            ];
    }

    /**
     * Trwałe usunięcie produktu wraz ze sprzątaniem — dla produktów, które NIGDY
     * nie były zamówione (bez wartości historycznej; typowo śmieci po testach).
     * Zdjęcia kasujemy przez Eloquent, by odpalił się hook ProductImage::deleting
     * usuwający pliki z dysku (kaskada FK usuwa tylko wiersze). forceDelete sprząta
     * następnie wiersze zdjęć, historii cen i powiązań tagów (kaskada FK).
     */
    public function purge(): void
    {
        $this->images()->get()->each->delete();
        $this->forceDelete();
    }

    /**
     * Historia cen w kolejności chronologicznej (najstarsza pierwsza).
     *
     * @return HasMany<ProductPriceHistory, $this>
     */
    public function priceHistory(): HasMany
    {
        return $this->hasMany(ProductPriceHistory::class)->orderBy('recorded_at')->orderBy('id');
    }

    /**
     * Najniższa cena z 30 dni przed obniżką (Omnibus) — do pokazania obok ceny.
     * Zwraca null, gdy nie ma czego ujawniać: brak obniżki w ostatnich 30 dniach
     * albo bieżąca cena nie jest niższa od wcześniejszej.
     */
    public function lowestPriceLast30Days(?CarbonInterface $now = null): ?float
    {
        $reference = OmnibusPrice::lowestBeforeCurrent($this->priceHistory, $now ?? now());

        return ($reference !== null && $reference > (float) $this->price_gross) ? $reference : null;
    }

    /**
     * Cena netto wyliczona z brutto i stawki VAT.
     */
    public function priceNet(): float
    {
        return round((float) $this->price_gross / (1 + $this->vat_rate->fraction()), 2);
    }

    /**
     * Kwota VAT (brutto − netto).
     */
    public function vatAmount(): float
    {
        return round((float) $this->price_gross - $this->priceNet(), 2);
    }

    /**
     * Czy produkt jest dostępny do kupienia (brak kontroli stanu albo stan > 0).
     */
    public function inStock(): bool
    {
        return ! $this->track_stock || (float) $this->stock > 0;
    }
}
