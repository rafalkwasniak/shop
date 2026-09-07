<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\IntegrationType;
use App\Enums\PaymentMethod;
use App\Enums\SaleUnit;
use App\Enums\SendingMethod;
use App\Enums\ShopStatus;
use App\Enums\VatRate;
use App\Observers\ShopObserver;
use App\Services\PhoneService;
use App\Support\Color;
use App\Support\Excerpt;
use Carbon\CarbonInterface;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sklep jednego sprzedawcy. `slug` = etykieta subdomeny ({slug}.{central_domain}).
 * `domain` = opcjonalna dedykowana domena (np. mojsklep.pl). `owner_id` nie jest
 * mass-assignable — sklep tworzymy przez relację usera.
 */
#[ObservedBy(ShopObserver::class)]
#[Fillable([
    'name', 'slug', 'domain', 'status', 'description', 'meta_description', 'meta_description_manual', 'company_name', 'nip', 'logo_path',
    'contact_email', 'contact_phone',
    'template', 'theme',
    'country', 'province', 'city', 'postal_code', 'street', 'building_number', 'apartment_number',
    'default_vat_rate', 'default_sale_unit',
    'bank_account_number', 'bank_account_holder', 'bank_name', 'bank_transfer_enabled',
    'pickup_enabled', 'pay_on_pickup_enabled',
    'courier_enabled', 'courier_cost', 'courier_free_from',
    'parcel_locker_enabled', 'parcel_locker_cost', 'parcel_locker_free_from',
    'courier_cod_enabled', 'courier_cod_cost', 'courier_cod_free_from',
    'parcel_locker_cod_enabled', 'parcel_locker_cod_cost', 'parcel_locker_cod_free_from',
    'shipment_sending_method',
    'courier_parcel_length_cm', 'courier_parcel_width_cm', 'courier_parcel_height_cm', 'courier_parcel_weight_kg',
    'package', 'entitlements', 'price_yearly', 'subscription_ends_at', 'comped',
])]
class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShopStatus::class,
            'theme' => 'array',
            'meta_description_manual' => 'boolean',
            'default_vat_rate' => VatRate::class,
            'default_sale_unit' => SaleUnit::class,
            'bank_transfer_enabled' => 'boolean',
            'pickup_enabled' => 'boolean',
            'pay_on_pickup_enabled' => 'boolean',
            'courier_enabled' => 'boolean',
            'courier_cost' => 'decimal:2',
            'courier_free_from' => 'decimal:2',
            'parcel_locker_enabled' => 'boolean',
            'parcel_locker_cost' => 'decimal:2',
            'parcel_locker_free_from' => 'decimal:2',
            'courier_cod_enabled' => 'boolean',
            'courier_cod_cost' => 'decimal:2',
            'courier_cod_free_from' => 'decimal:2',
            'parcel_locker_cod_enabled' => 'boolean',
            'parcel_locker_cod_cost' => 'decimal:2',
            'parcel_locker_cod_free_from' => 'decimal:2',
            'shipment_sending_method' => SendingMethod::class,
            'courier_parcel_weight_kg' => 'decimal:2',
            'entitlements' => 'array',
            'price_yearly' => 'decimal:2',
            'subscription_ends_at' => 'datetime',
            'comped' => 'boolean',
            'deletion_scheduled_at' => 'datetime',
            'unseen_orders_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<Tag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * Strony tekstowe sklepu („Informacje") — Regulamin i strony sprzedawcy.
     * Jedna wspólna kolejność (position) dla menu i stopki.
     *
     * @return HasMany<Page, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /**
     * Integracje sklepu (GA, w przyszłości PayU/InPost…). Jeden wiersz na typ.
     *
     * @return HasMany<ShopIntegration, $this>
     */
    public function integrations(): HasMany
    {
        return $this->hasMany(ShopIntegration::class);
    }

    /**
     * Pracownicy sklepu — wszystkie członkostwa, także odebrane i nieprzyjęte.
     * Ekran „Pracownicy" pokazuje jedno i drugie: zaproszenie, na które nikt
     * nie odpowiedział, jest informacją, a nie brakiem wiersza.
     *
     * @return HasMany<ShopEmployee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(ShopEmployee::class);
    }

    /**
     * Ilu pracowników mieści jeszcze pakiet. Liczą się WSZYSTKIE zajęte
     * miejsca — także zaproszenia bez odpowiedzi, bo inaczej limit dałoby się
     * obejść, zapraszając dwadzieścia osób i czekając, aż któraś kliknie.
     *
     * Odebrane członkostwa miejsca nie zajmują: wiersz zostaje dla śladu, ale
     * ta osoba już nie pracuje.
     */
    public function employeeSlotsLeft(): int
    {
        $used = $this->employees()->whereNull('revoked_at')->count();

        return max(0, (int) $this->entitlement('max_employees') - $used);
    }

    /**
     * Czy pakiet w ogóle daje konta pracowników. Zero miejsc = funkcji nie ma,
     * więc ekran ma pokazać zachętę zamiast narzędzia.
     */
    public function allowsEmployees(): bool
    {
        return (int) $this->entitlement('max_employees') > 0;
    }

    /**
     * Wiersz integracji danego typu (lub null). Czyta z załadowanej relacji,
     * żeby nie odpytywać bazy przy każdym wywołaniu w obrębie jednego requestu.
     */
    public function integration(IntegrationType $type): ?ShopIntegration
    {
        return $this->integrations->firstWhere('type', $type);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Zlecenia odbioru paczek przez kuriera InPostu.
     *
     * @return HasMany<DispatchOrder, $this>
     */
    public function dispatchOrders(): HasMany
    {
        return $this->hasMany(DispatchOrder::class);
    }

    /**
     * Klienci tego sklepu (konta storefrontu). Odseparowani między sklepami —
     * ten sam e-mail bywa klientem wielu sklepów niezależnie.
     *
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Kody rabatowe sklepu (uprawnienie `discount_codes`, pakiet Pawilon).
     * Najnowsze pierwsze — sprzedawca zwykle pracuje na tym, co właśnie dodał.
     *
     * @return HasMany<DiscountCode, $this>
     */
    public function discountCodes(): HasMany
    {
        return $this->hasMany(DiscountCode::class)->latest('id');
    }

    /**
     * Korespondencja seryjna sklepu (uprawnienie `bulk_mail`, pakiet Pawilon).
     * Najnowsze pierwsze — szkic, nad którym sprzedawca właśnie pracuje, ma być
     * na wierzchu. Z tej relacji liczy się też karencja między wysyłkami.
     *
     * @return HasMany<BulkMailing, $this>
     */
    public function bulkMailings(): HasMany
    {
        return $this->hasMany(BulkMailing::class)->latest('id');
    }

    /**
     * Opłaty za pakiety Kramio (płatności do platformy), najnowsze pierwsze.
     *
     * @return HasMany<PackagePayment, $this>
     */
    public function packagePayments(): HasMany
    {
        return $this->hasMany(PackagePayment::class)->latest('id');
    }

    /**
     * Historia pakietu — zmiany z płatności ORAZ nadane ręcznie, najnowsze
     * pierwsze.
     *
     * @return HasMany<PackageChange, $this>
     */
    public function packageChanges(): HasMany
    {
        return $this->hasMany(PackageChange::class)->latest('id');
    }

    /**
     * Zapisuje BIEŻĄCY stan pakietu do historii. Wołane po każdej zmianie —
     * z konsoli admina i po zaksięgowanej wpłacie.
     *
     * Nie zapisuje wpisu, gdy stan jest identyczny z ostatnim (ten sam pakiet,
     * cena, termin i `comped`): admin klika „Zapisz" także po zmianie samych
     * uprawnień, a wtedy historia pakietu nie ma o czym mówić.
     */
    public function recordPackageChange(string $source, ?PackagePayment $payment = null): ?PackageChange
    {
        $last = $this->packageChanges()->first();

        $unchanged = $last !== null
            && $last->package === $this->package
            && (float) $last->price_yearly === (float) $this->price_yearly
            && $last->ends_at?->format('Y-m-d H:i') === $this->subscription_ends_at?->format('Y-m-d H:i')
            && $last->comped === (bool) $this->comped;

        if ($unchanged) {
            return null;
        }

        return $this->packageChanges()->create([
            'package_payment_id' => $payment?->id,
            'package' => $this->package,
            'price_yearly' => $this->price_yearly ?? 0,
            'ends_at' => $this->subscription_ends_at,
            'source' => $source,
            'comped' => (bool) $this->comped,
        ]);
    }

    /**
     * Alokuje kolejny numer zamówienia tego sklepu — atomowo, przez blokadę
     * wiersza sklepu (unika kolizji przy równoczesnych zamówieniach). Numeracja
     * jest ciągła i nieodzyskiwana: licznik rośnie niezależnie od anulowania czy
     * usunięcia logicznego zamówień. Wołane w transakcji składania zamówienia.
     */
    public function allocateOrderNumber(): int
    {
        return DB::transaction(function (): int {
            $locked = self::whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $next = $locked->last_order_number + 1;
            $locked->last_order_number = $next;
            $locked->save();

            $this->last_order_number = $next;

            return $next;
        });
    }

    /**
     * Tagi sklepu mające przynajmniej jeden aktywny produkt, z liczbą tych
     * produktów (`products_count`), najpopularniejsze najpierw. Wejście do
     * przeglądania po tagach (główna, chmura na wykazie bez filtra).
     *
     * @return Collection<int, Tag>
     */
    public function activeTagsByPopularity(): Collection
    {
        return $this->tags()
            ->whereHas('products', fn ($query) => $query->where('is_active', true))
            ->withCount(['products' => fn ($query) => $query->where('is_active', true)])
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->get();
    }

    /**
     * Usuwa osierocone tagi sklepu — takie, do których nie odnosi się już żaden
     * produkt (również usunięty miękko). Tag powstaje przy produkcie i ma żyć tak
     * długo, jak używa go choć jeden produkt; po usunięciu ostatniego znika, żeby
     * nie zaśmiecał podpowiedzi ani chmury tagów. Kasowanie tagu usuwa też jego
     * powiązania (kaskada FK product_tag). Trashed produkty liczą się jako
     * używające tagu — tag zamówionego, miękko usuniętego produktu zostaje.
     */
    public function pruneOrphanTags(): void
    {
        $this->tags()
            ->whereDoesntHave('products', fn ($query) => $query->withTrashed())
            ->each(fn (Tag $tag) => $tag->delete());
    }

    /**
     * Host storefrontu: dedykowana domena, jeśli ustawiona, w przeciwnym razie
     * subdomena {slug}.{central_domain}.
     */
    public function host(): string
    {
        return $this->domain ?: $this->slug.'.'.config('tenancy.central_domain');
    }

    /**
     * Czy adres sklepu jest kompletny (wszystkie wymagane pola wypełnione).
     * Używane m.in. na pulpicie (postęp konfiguracji) i przy publikacji.
     */
    public function addressComplete(): bool
    {
        foreach (['street', 'building_number', 'postal_code', 'city', 'province'] as $field) {
            if (blank($this->{$field})) {
                return false;
            }
        }

        return true;
    }

    /**
     * Dane nabywcy NASZEJ faktury za pakiet (Kramio → sprzedawca). Bierzemy je
     * z danych sklepu, bo sprzedawca już je tam wpisał — nie pytamy drugi raz
     * o to samo przy zakupie.
     *
     * Bez NIP-u i nazwy firmy wychodzi **faktura imienna** na właściciela konta:
     * część sprzedawców to osoby fizyczne (rękodzieło) i one też muszą móc kupić
     * pakiet. Adres jest jednak OBOWIĄZKOWY — faktura bez niego jest nieważna,
     * stąd `canBeInvoicedForPackage()`.
     *
     * @return array{name: string, nip: string|null, street: string|null, postal_code: string|null, city: string|null, country: string|null, personal: bool}
     */
    public function packageInvoiceRecipient(): array
    {
        $owner = $this->owner;
        $company = trim((string) $this->company_name);
        $nip = trim((string) $this->nip);

        return [
            'name' => $company !== ''
                ? $company
                : trim(($owner?->name ?? '').' '.($owner?->surname ?? '')),
            'nip' => $nip !== '' ? $nip : null,
            'street' => $this->streetLine(),
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'country' => $this->country ?: 'PL',
            // Faktura imienna = brak danych firmowych. UI mówi o tym wprost,
            // żeby nikt nie odkrył tego dopiero na dokumencie.
            'personal' => $company === '' || $nip === '',
        ];
    }

    /**
     * Czy da się wystawić sprzedawcy fakturę za pakiet. Wymagany jest adres
     * (ulica z numerem, kod, miasto) oraz nazwa nabywcy — firmowa albo imię
     * i nazwisko właściciela. Bez tego blokujemy ZAKUP, nie tylko fakturę:
     * lepiej zatrzymać przed płatnością niż wziąć pieniądze i nie móc
     * udokumentować sprzedaży.
     */
    public function canBeInvoicedForPackage(): bool
    {
        $recipient = $this->packageInvoiceRecipient();

        return filled($recipient['name'])
            && filled($recipient['street'])
            && filled($recipient['postal_code'])
            && filled($recipient['city']);
    }

    /**
     * Ulica z numerem domu i lokalu, BEZ kodu i miasta — faktura trzyma je
     * w osobnych polach. Nie mylić z `addressLine()`, które składa cały adres
     * w jedną linię do wyświetlenia.
     */
    private function streetLine(): ?string
    {
        if (blank($this->street) || blank($this->building_number)) {
            return null;
        }

        $house = trim((string) $this->building_number)
            .(filled($this->apartment_number) ? '/'.trim((string) $this->apartment_number) : '');

        return trim($this->street).' '.$house;
    }

    /**
     * Czy dane kontaktowe są kompletne (e-mail i telefon). Oba wymagane w panelu;
     * używane na pulpicie (postęp konfiguracji). E-mail bywa backfillowany z konta
     * właściciela, więc realnym „brakiem" jest zwykle telefon.
     */
    public function contactComplete(): bool
    {
        return filled($this->contact_email) && filled($this->contact_phone);
    }

    /**
     * Czy sklep ma przynajmniej jeden aktywny produkt. To jedyny wyznacznik
     * publicznej widoczności sklepu — pozostałe dane (adres, NIP, opis, logo) są
     * opcjonalne. Stan magazynowy pojedynczego produktu nie ma tu znaczenia
     * (wyczerpany produkt to sprawa produktu, nie całego sklepu).
     */
    public function hasActiveProducts(): bool
    {
        return $this->products()->where('is_active', true)->exists();
    }

    /**
     * Czy sklep jest publicznie widoczny (na żywo). Status w bazie jest
     * odzwierciedleniem tej widoczności — utrzymywanym automatycznie przez
     * App\Observers\ProductObserver — więc admin i storefront czytają gotową
     * kolumnę, bez liczenia produktów per sklep.
     */
    public function isVisible(): bool
    {
        return $this->status === ShopStatus::Active;
    }

    /**
     * Opis sklepu jako czysty tekst (bez HTML, ze zwiniętymi białymi znakami) —
     * do liczenia długości progu „O sklepie". `<br>` i tagi nie zawyżają wyniku.
     */
    public function aboutPlainText(): string
    {
        return Excerpt::plainText($this->description);
    }

    /**
     * Zajawka „O sklepie" do kafelka na stronie głównej. Dokładnie ta sama reguła
     * co dla promowanych stron (`Page::excerpt()`) — „O sklepie" jest tam zwykłym
     * kafelkiem, różni się tylko tym, skąd bierze treść i że idzie pierwsze.
     */
    public function aboutExcerpt(): Excerpt
    {
        return Excerpt::fromHtml($this->description, (int) config('pages.excerpt_length'));
    }

    /**
     * Czy istnieje wirtualna strona „O sklepie" — tzn. opis sklepu jest niepusty.
     * Strona renderuje zawsze, gdy jest jakakolwiek treść (długość rządzi tylko
     * obecnością w menu, nie istnieniem strony).
     */
    public function hasAbout(): bool
    {
        return $this->aboutPlainText() !== '';
    }

    /**
     * Czy „O sklepie" zasługuje na własną pozycję w menu „Informacje": opis
     * dłuższy niż próg czystego tekstu z configu. Poniżej progu adres nadal
     * działa, brakuje tylko pozycji w menu.
     *
     * Dotyczy WYŁĄCZNIE menu. Kafelkiem na stronie głównej rządzi `hasAbout()`
     * (czy jest treść) i `aboutExcerpt()` (co pokazać) — próg nie ma tam nic
     * do rzeczy, mimo że `pages.excerpt_length` ma dziś tę samą wartość.
     */
    public function aboutInMenu(): bool
    {
        return mb_strlen($this->aboutPlainText()) >= (int) config('pages.about.menu_threshold');
    }

    /**
     * Kanoniczny adres wirtualnej strony „O sklepie" na storefroncie (względny —
     * storefront to jeden host). Slug z configu, w rodzinie /informacje/….
     */
    public function aboutPath(): string
    {
        return '/informacje/'.config('pages.about.slug');
    }

    /**
     * Kanoniczny adres Polityki prywatności na storefroncie (w rodzinie
     * /informacje/…). Treść jest NASZA (Kramio), ale adres i prezentacja spójne
     * z resztą działu „Informacje".
     */
    public function privacyPath(): string
    {
        return '/informacje/'.config('pages.privacy.slug');
    }

    /**
     * Pozycje menu „Informacje" — jedno źródło dla nagłówka, skorupy stron i
     * stopki. Kolejność: wirtualna „O sklepie" jako PIERWSZA (tylko gdy opis
     * przekracza próg), potem opublikowane strony wg `position` (Regulamin też —
     * to strona), a Polityka prywatności ZAWSZE jako OSTATNIA. Jedna lista, jedna
     * kolejność.
     *
     * @return list<array{label: string, url: string}>
     */
    public function informationMenu(): array
    {
        $items = [];

        if ($this->aboutInMenu()) {
            $items[] = ['label' => config('pages.about.title'), 'url' => $this->aboutPath()];
        }

        foreach ($this->pages()->published()->get() as $page) {
            $items[] = ['label' => $page->title, 'url' => $page->storefrontPath()];
        }

        $items[] = ['label' => config('pages.privacy.title'), 'url' => $this->privacyPath()];

        return $items;
    }

    /**
     * Przelicza i zapisuje status sklepu z liczby aktywnych produktów:
     * ≥1 → Aktywny (widoczny), 0 → Szkic (ukryty). Wołane automatycznie przy
     * każdej zmianie produktu. Zapis tylko gdy status faktycznie się zmienia.
     */
    public function refreshVisibility(): void
    {
        $target = $this->hasActiveProducts() ? ShopStatus::Active : ShopStatus::Draft;

        if ($this->status !== $target) {
            $this->status = $target;
            $this->save();
        }
    }

    /**
     * Czy sklep udostępnia w kasie płatność przelewem na konto. Dwa warunki:
     * fiszka „Przelew na konto" włączona (Ustawienia) ORAZ numer konta wypełniony
     * (dane w „Mój sklep") — bez numeru nie ma dokąd przelać. Wykorzystane później
     * przy checkoucie (widoczność/wybór metody płatności).
     */
    public function bankTransferAvailable(): bool
    {
        return $this->bank_transfer_enabled && filled($this->bank_account_number);
    }

    /**
     * Czy sklep oferuje odbiór osobisty. Dwa warunki: fiszka włączona
     * (Ustawienia) ORAZ kompletny adres sklepu (adres odbioru bierze się z danych
     * sklepu — bez niego nie ma dokąd przyjść). Analogia do bankTransferAvailable.
     */
    public function pickupAvailable(): bool
    {
        return $this->pickup_enabled && $this->addressComplete();
    }

    /**
     * Czy sklep przyjmuje płatność przy odbiorze. Metoda płatności zależna od
     * dostawy: wymaga realnie dostępnego odbioru osobistego ORAZ włączonej
     * fiszki. Bez odbioru nie ma gdzie zapłacić „na miejscu".
     */
    public function payOnPickupAvailable(): bool
    {
        return $this->pay_on_pickup_enabled && $this->pickupAvailable();
    }

    /**
     * Czy sklep oferuje dostawę kurierem. Wymaga uprawnienia pakietu
     * `courier_shipping` (Stragan+; wysyłka InPost to funkcja płatna —
     * Kram ma tylko odbiór osobisty) ORAZ włączonej fiszki. Nie wymaga adresu
     * sklepu: paczkę wysyła sprzedawca do klienta. Koszt bywa 0 (kurier gratis),
     * dlatego warunkiem jest włącznik, a nie „koszt > 0". Stan efektywny.
     */
    public function courierAvailable(): bool
    {
        return $this->entitlement('courier_shipping') === true && $this->courier_enabled;
    }

    /**
     * Efektywny koszt dostawy kurierem dla danej wartości koszyka (brutto). Zwraca
     * 0, gdy sklep ustawił próg darmowej dostawy i wartość produktów go osiąga —
     * w innym wypadku ustalony koszt kuriera. Próg NULL = brak darmowej dostawy.
     */
    public function courierCostFor(float $itemsGross): float
    {
        $freeFrom = $this->courier_free_from;

        if ($freeFrom !== null && $itemsGross >= (float) $freeFrom) {
            return 0.0;
        }

        return (float) ($this->courier_cost ?? 0);
    }

    /**
     * Czy sklep oferuje dostawę do paczkomatu InPost. Jak kurier: wymaga
     * uprawnienia `courier_shipping` (Stragan+; paczkomat i kurier siedzą w tym
     * samym płatnym uprawnieniu) ORAZ włączonej fiszki. Nie wymaga adresu
     * sklepu ani konta w InPoście (koszt bywa 0 = gratis). Mapa NIE jest
     * warunkiem — gdy jej nie ma, klient wpisuje kod z palca.
     */
    public function parcelLockerAvailable(): bool
    {
        return $this->entitlement('courier_shipping') === true && $this->parcel_locker_enabled;
    }

    /**
     * Efektywny koszt dostawy do paczkomatu dla danej wartości koszyka (brutto).
     * Bliźniak courierCostFor(): próg osiągnięty → 0, inaczej ustalony koszt.
     * Próg NULL = brak darmowej dostawy. Progi kuriera i paczkomatu są NIEZALEŻNE
     * — sprzedawca może dać gratis w paczkomacie, a kuriera liczyć zawsze.
     */
    public function parcelLockerCostFor(float $itemsGross): float
    {
        $freeFrom = $this->parcel_locker_free_from;

        if ($freeFrom !== null && $itemsGross >= (float) $freeFrom) {
            return 0.0;
        }

        return (float) ($this->parcel_locker_cost ?? 0);
    }

    /**
     * Czy sklep oferuje kuriera ZA POBRANIEM. Trzy warunki: to samo uprawnienie
     * co zwykła wysyłka (`courier_shipping`), własna fiszka ORAZ skonfigurowany
     * i włączony InPost.
     *
     * Ten trzeci warunek jest tym, co odróżnia pobranie od reszty dostaw i
     * dlatego nie da się go pominąć: zwykłego kuriera sprzedawca może obsłużyć
     * sam (własne auto, cena 0 zł), a pobranie z definicji wymaga kogoś, kto
     * odbierze pieniądze od klienta i przeleje je sprzedawcy — czyli InPostu.
     * Bez integracji ta metoda nie miałaby jak zadziałać, więc nie pokazujemy
     * jej ani w kasie, ani w regulaminie.
     *
     * Fiszka jest NIEZALEŻNA od `courier_enabled`: sprzedawca może chcieć
     * wyłącznie pobraniowe albo wyłącznie przedpłacone (decyzja Rafała 17.08).
     */
    public function courierCodAvailable(): bool
    {
        return $this->entitlement('courier_shipping') === true
            && $this->courier_cod_enabled
            && $this->shipxEnabled();
    }

    /**
     * Efektywny koszt kuriera za pobraniem. Cennik jest WŁASNY, nie dopłatą do
     * `courier_cost` — sprzedawca ustala go osobno, bo osobno płaci InPostowi
     * za obsługę pobrania. Próg darmowej dostawy też własny i niezależny.
     */
    public function courierCodCostFor(float $itemsGross): float
    {
        $freeFrom = $this->courier_cod_free_from;

        if ($freeFrom !== null && $itemsGross >= (float) $freeFrom) {
            return 0.0;
        }

        return (float) ($this->courier_cod_cost ?? 0);
    }

    /**
     * Czy sklep oferuje paczkomat ZA POBRANIEM. Bliźniak
     * {@see courierCodAvailable()} — te same trzy warunki, własna fiszka.
     */
    public function parcelLockerCodAvailable(): bool
    {
        return $this->entitlement('courier_shipping') === true
            && $this->parcel_locker_cod_enabled
            && $this->shipxEnabled();
    }

    /**
     * Efektywny koszt paczkomatu za pobraniem. Jak wyżej: własna cena, własny próg.
     */
    public function parcelLockerCodCostFor(float $itemsGross): float
    {
        $freeFrom = $this->parcel_locker_cod_free_from;

        if ($freeFrom !== null && $itemsGross >= (float) $freeFrom) {
            return 0.0;
        }

        return (float) ($this->parcel_locker_cod_cost ?? 0);
    }

    /**
     * Czy sklep w ogóle przyjmuje pobranie (którakolwiek z dwóch metod).
     * JEDNO źródło dla kasy (ustawia metodę płatności), regulaminu (wylicza
     * sposoby zapłaty) i Ustawień.
     */
    public function cashOnDeliveryAvailable(): bool
    {
        return $this->courierCodAvailable() || $this->parcelLockerCodAvailable();
    }

    /**
     * Efektywny koszt dostawy dla danej metody i wartości koszyka (brutto).
     * JEDNO źródło dla kasy i OrderService — wcześniej koszt liczyło się jako
     * „wysyłka → courierCostFor()", co przy paczkomacie kazałoby mu płacić
     * cenę kuriera. Nowa metoda dostawy wywali tu UnhandledMatchError i dobrze:
     * cennik trzeba dopisać świadomie, a nie odziedziczyć po cichu (ta sama
     * zasada, co przy ścieżce statusów w OrderFlow).
     */
    public function deliveryCostFor(DeliveryMethod $method, float $itemsGross): float
    {
        return match ($method) {
            DeliveryMethod::Pickup => 0.0,
            DeliveryMethod::Courier => $this->courierCostFor($itemsGross),
            DeliveryMethod::ParcelLocker => $this->parcelLockerCostFor($itemsGross),
            DeliveryMethod::CourierCod => $this->courierCodCostFor($itemsGross),
            DeliveryMethod::ParcelLockerCod => $this->parcelLockerCodCostFor($itemsGross),
        };
    }

    /**
     * Próg darmowej dostawy dla danej metody (null = brak progu). Lustro
     * deliveryCostFor() — kasa pokazuje z tego podpowiedź „Darmowa dostawa od…".
     */
    public function deliveryFreeFrom(DeliveryMethod $method): ?float
    {
        $value = match ($method) {
            DeliveryMethod::Pickup => null,
            DeliveryMethod::Courier => $this->courier_free_from,
            DeliveryMethod::ParcelLocker => $this->parcel_locker_free_from,
            DeliveryMethod::CourierCod => $this->courier_cod_free_from,
            DeliveryMethod::ParcelLockerCod => $this->parcel_locker_cod_free_from,
        };

        return $value !== null ? (float) $value : null;
    }

    /**
     * Metody dostawy realnie oferowane przez sklep, w kolejności prezentacji.
     * JEDNO źródło dla kasy i dla storefrontu — kolejność ma znaczenie, bo kasa
     * zaznacza pierwszą z brzegu.
     *
     * @return list<DeliveryMethod>
     */
    public function availableDeliveryMethods(): array
    {
        $methods = [];

        if ($this->pickupAvailable()) {
            $methods[] = DeliveryMethod::Pickup;
        }

        if ($this->courierAvailable()) {
            $methods[] = DeliveryMethod::Courier;
        }

        if ($this->parcelLockerAvailable()) {
            $methods[] = DeliveryMethod::ParcelLocker;
        }

        // Pobraniowe na końcu: przedpłata jest dla sprzedawcy tańsza i pewniejsza,
        // a kasa zaznacza pierwszą z brzegu.
        if ($this->courierCodAvailable()) {
            $methods[] = DeliveryMethod::CourierCod;
        }

        if ($this->parcelLockerCodAvailable()) {
            $methods[] = DeliveryMethod::ParcelLockerCod;
        }

        return $methods;
    }

    /**
     * Metody płatności realnie oferowane przez sklep, w kolejności prezentacji
     * (online na czele — przedpłata działa przy każdej dostawie). Lista jest
     * BEZ kontekstu wybranej dostawy: zawężenie „kurier ⇒ nie ma płatności przy
     * odbiorze" robi kasa, bo tylko ona wie, co klient zaznaczył.
     *
     * @return list<PaymentMethod>
     */
    public function availablePaymentMethods(): array
    {
        $methods = [];

        if ($this->onlinePaymentsEnabled()) {
            $methods[] = PaymentMethod::Online;
        }

        if ($this->bankTransferAvailable()) {
            $methods[] = PaymentMethod::BankTransfer;
        }

        if ($this->payOnPickupAvailable()) {
            $methods[] = PaymentMethod::PayOnPickup;
        }

        return $methods;
    }

    /**
     * Czy w sklepie da się DOKOŃCZYĆ zakup: jest czym dostarczyć i czym zapłacić.
     * Bez tego storefront nie pokazuje „Do koszyka" — sklep zostaje wykazem
     * oferty (sprzedaż np. telefoniczna), a nie zepsutą kasą. Stan wynika wprost
     * z ustawień i liczy się na żywo: sprzedawca odznacza metodę i przyciski
     * znikają, włącza — wracają. Żadnej flagi w bazie, bo ta mogłaby się rozjechać
     * z rzeczywistością.
     *
     * Para zawsze się złoży: jedyna płatność zależna od dostawy (przy odbiorze)
     * wymaga dostępnego odbioru osobistego, więc gdy jest w liście płatności, to
     * odbiór jest w liście dostaw.
     *
     * POBRANIE JEST DRUGĄ DROGĄ DO ZAPŁATY, obok listy metod płatności. Nie ma
     * go w `availablePaymentMethods()`, bo klient go nie wybiera — wynika z
     * dostawy — więc bez tego warunku sklep oferujący WYŁĄCZNIE pobranie
     * zostałby uznany za niezdolny do sprzedaży i wylądował w trybie katalogu.
     * A to jest dokładnie ten sprzedawca, dla którego pobranie zrobiliśmy: taki,
     * który nie chce zakładać konta u operatora płatności ani czekać na przelewy.
     */
    public function acceptsOrders(): bool
    {
        return $this->availableDeliveryMethods() !== []
            && ($this->availablePaymentMethods() !== [] || $this->cashOnDeliveryAvailable());
    }

    /**
     * Skonfigurowany identyfikator Google Analytics / Tag Manager (G-… lub
     * GTM-…), niezależnie od włącznika. Do sprawdzenia „czy skonfigurowane"
     * (Integracje, Ustawienia). null, gdy integracji nie ma lub nie ma ID.
     */
    public function googleAnalyticsId(): ?string
    {
        return $this->integration(IntegrationType::GoogleAnalytics)?->config['tracking_id'] ?? null;
    }

    /**
     * Kod weryfikacyjny Google Search Console (zawartość `content` z meta tagu).
     *
     * BEZ bramki pakietu — świadomie, inaczej niż GA. To nie analityka, tylko
     * jedyna droga, jaką sprzedawca może potwierdzić Google własność swojej
     * subdomeny: pliku na serwer nie wgra, rekordu DNS w `*.kramio.pl` nie doda,
     * a weryfikacja przez GA jest płatna od Straganu. Bez tego pola mapa strony
     * (którą dostają WSZYSTKIE sklepy) byłaby dla darmowego Kramu nie do zgłoszenia.
     */
    public function googleSiteVerification(): ?string
    {
        return $this->integration(IntegrationType::SearchConsole)?->config['verification_code'] ?? null;
    }

    /**
     * Czy storefront ma faktycznie wstrzyknąć GA: uprawnienie pakietu
     * `ga_analytics` (Stragan+; GA/GTM to funkcja płatna — NASZA analityka zostaje
     * dla wszystkich osobno) ORAZ fiszka włączona (Ustawienia) ORAZ identyfikator
     * wpisany (Integracje). Stan efektywny, nie sam włącznik.
     */
    public function tracksWithGoogleAnalytics(): bool
    {
        if ($this->entitlement('ga_analytics') !== true) {
            return false;
        }

        $integration = $this->integration(IntegrationType::GoogleAnalytics);

        return $integration?->enabled === true && filled($integration->config['tracking_id'] ?? null);
    }

    /**
     * Adres konta Fakturowni (np. „https://twojadomena.fakturownia.pl") — baza
     * zarówno dla wywołań API, jak i publicznego linku do PDF faktury. null, gdy
     * integracji nie ma lub nie ma adresu.
     */
    public function fakturowniaAccountUrl(): ?string
    {
        return $this->integration(IntegrationType::Invoicing)?->config['account_url'] ?? null;
    }

    /**
     * Token API Fakturowni (sekret, przechowywany zaszyfrowany w config). Używany
     * przez usługę wystawiającą FV; w UI nigdy nie odbijamy go z powrotem. null,
     * gdy brak integracji lub tokenu.
     */
    public function fakturowniaToken(): ?string
    {
        return $this->integration(IntegrationType::Invoicing)?->config['api_token'] ?? null;
    }

    /**
     * Czy Fakturownia jest w pełni skonfigurowana (adres + token). Nie sprawdza
     * uprawnienia pakietu ani statusu zamówienia — to sama gotowość integracji.
     * Pełna bramka „czy można wystawić FV" łączy to z entitlement('invoices').
     */
    public function invoicingConfigured(): bool
    {
        return filled($this->fakturowniaAccountUrl()) && filled($this->fakturowniaToken());
    }

    /**
     * Stan efektywny Fakturowni: włącznik z Ustawień ORAZ komplet konfiguracji
     * (adres + token). To warunek, pod którym w karcie zamówienia pokazujemy
     * przycisk „Stwórz fakturę VAT" — w połączeniu z entitlement('invoices').
     * Analogia do tracksWithGoogleAnalytics(): sam włącznik bez konfiguracji nic
     * nie znaczy.
     */
    public function invoicingEnabled(): bool
    {
        return $this->integration(IntegrationType::Invoicing)?->enabled === true && $this->invoicingConfigured();
    }

    /**
     * Klucz dostępu do API Paynow (nagłówek Api-Key). Sam w sobie bezużyteczny
     * bez klucza podpisu — nie da się nim podpisać żądania — dlatego, inaczej niż
     * klucz podpisu, wolno go pokazać w formularzu sprzedawcy. null, gdy brak.
     */
    public function paynowApiKey(): ?string
    {
        return $this->integration(IntegrationType::Payments)?->config['api_key'] ?? null;
    }

    /**
     * Klucz obliczania podpisu Paynow (sekret: podpisuje żądania i weryfikuje
     * webhooki). Przechowywany zaszyfrowany; w UI nigdy nie odbijany. null, gdy brak.
     */
    public function paynowSignatureKey(): ?string
    {
        return $this->integration(IntegrationType::Payments)?->config['signature_key'] ?? null;
    }

    /**
     * Środowisko Paynow: 'sandbox' (domyślnie) albo 'production'. Rozstrzyga, który
     * adres API z config('services.paynow.base_url') wybrać przy wywołaniach.
     */
    public function paynowEnvironment(): string
    {
        return $this->integration(IntegrationType::Payments)?->config['environment'] ?? 'sandbox';
    }

    /**
     * Czy Paynow jest w pełni skonfigurowany (oba klucze). Nie sprawdza włącznika
     * ani uprawnienia pakietu — sama gotowość integracji. Analogia do
     * invoicingConfigured().
     */
    public function onlinePaymentsConfigured(): bool
    {
        return filled($this->paynowApiKey()) && filled($this->paynowSignatureKey());
    }

    /**
     * Stan efektywny płatności online: uprawnienie pakietu ORAZ włącznik
     * (Ustawienia) ORAZ komplet kluczy. To warunek, pod którym metoda „Płatność
     * online" pojawia się w kasie. Uprawnienie na pierwszym miejscu = centralna
     * brama: sklep bez `online_payments` (np. Kram lub po zejściu z pakietu) nie
     * oferuje płatności online nigdzie, choćby integracja była skonfigurowana.
     */
    public function onlinePaymentsEnabled(): bool
    {
        return $this->entitlement('online_payments') === true
            && $this->integration(IntegrationType::Payments)?->enabled === true
            && $this->onlinePaymentsConfigured();
    }

    /**
     * Czy zamówienie ZŁOŻONE WCZEŚNIEJ może jeszcze dokończyć płatność online,
     * choć uprawnienie pakietu już zgasło.
     *
     * Wyjątek celowy: gdyby bramka zamknęła się z dnia na dzień, klient nie
     * dokończyłby przelewu za wczorajsze zamówienie, pieniądze utknęłyby w
     * pół drogi, a winnym byłby sklep. Data nie jest tu potrzebna — zamówienie
     * czekające na płatność online mogło powstać TYLKO przy otwartej bramce,
     * więc samo jego istnienie jest dowodem. Czytamy `rawEntitlement`, bo pytamy
     * o to, co klient kupił, nie o stan po wygaśnięciu.
     */
    public function canFinishOnlinePayment(): bool
    {
        return $this->rawEntitlement('online_payments') === true
            && $this->integration(IntegrationType::Payments)?->enabled === true
            && $this->onlinePaymentsConfigured();
    }

    /**
     * Czy po opłaceniu online sklep chce automatycznie wystawić FV. Wygodny skrót
     * dla widoku Ustawień (checkbox pod włącznikiem Paynow) — deleguje do bramki
     * płatności, bo to na jej wierszu żyje flaga `auto_invoice`. Miejsce wyzwalania
     * (webhook) pyta integrację wprost, więc druga bramka nie wymaga tu zmian. Sama
     * flaga — realne wystawienie i tak przechodzi przez `Order::canBeInvoiced()`
     * (Fakturownia włączona, uprawnienie, brak dubla), więc zaznaczona bez włączonej
     * Fakturowni po prostu nic nie robi.
     */
    public function autoInvoiceAfterPayment(): bool
    {
        return $this->integration(IntegrationType::Payments)?->autoInvoiceAfterPayment() === true;
    }

    /**
     * Adres powiadomień (webhooka) Paynow dla tego sklepu — do przeklejenia w
     * panelu operatora. Na własnym hoście sklepu (subdomena/domena), bo tam
     * kieruje kupującego i tam sprzedawca konfiguruje Paynow. Powiadomień NIE da
     * się ustawić z naszego systemu — sprzedawca wkleja ten adres ręcznie.
     */
    public function paynowWebhookUrl(): string
    {
        return 'https://'.$this->host().'/platnosci/paynow/webhook';
    }

    /**
     * Token ShipX sprzedawcy — klucz do NADAWANIA przesyłek na jego koncie
     * InPost. Sekret: żyje zaszyfrowany w `shop_integrations` i wolno go używać
     * WYŁĄCZNIE po stronie serwera (patrz komentarz w config/services.php).
     */
    public function shipxToken(): ?string
    {
        return $this->integration(IntegrationType::Shipping)?->config['token'] ?? null;
    }

    /**
     * Organization ID sprzedawcy w ShipX — część adresu przy tworzeniu przesyłki.
     */
    public function shipxOrganizationId(): ?string
    {
        $id = $this->integration(IntegrationType::Shipping)?->config['organization_id'] ?? null;

        return filled($id) ? (string) $id : null;
    }

    /**
     * Środowisko ShipX sklepu: `sandbox` albo `production`. Steruje wyborem
     * adresu API z config('services.inpost.shipx.base_url'). Domyślnie sandbox —
     * pomyłka w tę stronę nic nie kosztuje, w drugą nadaje prawdziwe paczki.
     */
    public function shipxEnvironment(): string
    {
        return $this->integration(IntegrationType::Shipping)?->config['environment'] ?? 'sandbox';
    }

    /**
     * Czy sklep ma komplet danych do nadawania (token + Organization ID).
     * „Skonfigurowane" ≠ „włączone" — włącznik jest w Ustawieniach.
     */
    public function shipxConfigured(): bool
    {
        return filled($this->shipxToken()) && filled($this->shipxOrganizationId());
    }

    /**
     * Czy sklep może nadawać przesyłki: komplet danych + włącznik integracji
     * + uprawnienie pakietu. Bramkujemy ISTNIEJĄCYM `courier_shipping` („Wysyłka
     * kurierem i przez InPost”), a nie nowym kluczem: etykiety to część wysyłki,
     * którą ten pakiet już sprzedaje, a nowy klucz musiałby jeszcze dojechać do
     * LEPKICH snapshotów istniejących sklepów.
     */
    public function shipxEnabled(): bool
    {
        return $this->entitlement('courier_shipping')
            && $this->integration(IntegrationType::Shipping)?->enabled === true
            && $this->shipxConfigured();
    }

    /**
     * Adres API ShipX dla środowiska sklepu. null, gdy sklep nie jest
     * skonfigurowany — wtedy nie ma czego wołać.
     */
    public function shipxBaseUrl(): ?string
    {
        if (! $this->shipxConfigured()) {
            return null;
        }

        return config('services.inpost.shipx.base_url.'.$this->shipxEnvironment());
    }

    /**
     * Jak sprzedawca oddaje paczki InPostowi. Fallback na darmowy paczkomat
     * dotyczy sklepów sprzed tej funkcji — nigdy nie domyślamy się opcji
     * PŁATNEJ, bo wybór jest wiążący dla każdej nadanej przesyłki.
     */
    public function sendingMethod(): SendingMethod
    {
        return $this->shipment_sending_method ?? SendingMethod::default();
    }

    /**
     * Adres, spod którego kurier InPostu odbiera paczki — w kształcie ShipX.
     * To adres sklepu (pracowni), bo stamtąd wyjeżdżają przesyłki. null, gdy
     * sprzedawca nie uzupełnił adresu: bez niego zlecenie odbioru nie ma sensu.
     *
     * @return array<string, string>|null
     */
    public function pickupAddress(): ?array
    {
        if (blank($this->street) || blank($this->building_number) || blank($this->postal_code) || blank($this->city)) {
            return null;
        }

        $building = (string) $this->building_number;

        // ShipX nie ma pola na numer lokalu — doklejamy do numeru budynku,
        // tak samo jak przy adresie odbiorcy.
        if (filled($this->apartment_number)) {
            $building .= '/'.$this->apartment_number;
        }

        return [
            'street' => (string) $this->street,
            'building_number' => $building,
            'city' => (string) $this->city,
            'post_code' => (string) $this->postal_code,
            'country_code' => 'PL',
        ];
    }

    /**
     * Domyślna paczka kurierska (centymetry i kilogramy) albo null, gdy
     * sprzedawca nie uzupełnił KOMPLETU. Połowa wymiarów jest bezużyteczna —
     * ShipX potrzebuje wszystkich trzech i wagi, więc lepiej nie podpowiadać
     * nic, niż podpowiedzieć wpół.
     *
     * @return array{length: int, width: int, height: int, weight: float}|null
     */
    public function courierParcelDefaults(): ?array
    {
        $values = [
            'length' => $this->courier_parcel_length_cm,
            'width' => $this->courier_parcel_width_cm,
            'height' => $this->courier_parcel_height_cm,
            'weight' => $this->courier_parcel_weight_kg,
        ];

        foreach ($values as $value) {
            if (blank($value) || (float) $value <= 0) {
                return null;
            }
        }

        return [
            'length' => (int) $values['length'],
            'width' => (int) $values['width'],
            'height' => (int) $values['height'],
            'weight' => (float) $values['weight'],
        ];
    }

    /**
     * Telefon kontaktowy w czytelnej postaci („+48 668 196 229"). Przechowujemy
     * kanonicznie (48 + 9 cyfr); formatujemy dopiero do wyświetlenia — stopka,
     * maile, storefront. Null, gdy sklep nie ma jeszcze numeru.
     */
    public function formattedContactPhone(): ?string
    {
        return app(PhoneService::class)->format($this->contact_phone);
    }

    /**
     * Adres siedziby w jednej linii: „Okrzei 73/5, 42-582 Rogoźnik". Null, gdy nie
     * ma z czego złożyć — dane firmowe są OPCJONALNE (wymagany jest tylko kontakt,
     * patrz ShopProfileRequest), więc sklep bez adresu to normalny stan, nie błąd.
     *
     * Kod pocztowy bierzemy jak stoi: obie ścieżki zapisu (formularz profilu oraz
     * podpowiedź z GUS) normalizują go do postaci NN-NNN.
     */
    public function addressLine(): ?string
    {
        $house = trim((string) $this->building_number);

        if (filled($this->apartment_number)) {
            $house .= '/'.trim((string) $this->apartment_number);
        }

        $parts = array_filter([
            trim(trim((string) $this->street).' '.$house),
            trim(trim((string) $this->postal_code).' '.trim((string) $this->city)),
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Numer konta w czytelnej postaci — grupy po 4 cyfry (NN NNNN NNNN …).
     * Przechowujemy same cyfry; formatujemy dopiero do wyświetlenia.
     */
    public function formattedBankAccountNumber(): ?string
    {
        if (blank($this->bank_account_number)) {
            return null;
        }

        $number = $this->bank_account_number;

        // Polski NRB (26 cyfr) czyta się jako: 2 cyfry kontrolne + grupy po 4
        // (XX XXXX XXXX XXXX XXXX XXXX XXXX). Krótszy/nietypowy — grupujemy po 4 od startu.
        if (strlen($number) < 3) {
            return $number;
        }

        return substr($number, 0, 2).' '.trim(chunk_split(substr($number, 2), 4, ' '));
    }

    /**
     * Odbiorca przelewu do pokazania klientowi: jawnie ustawiony odbiorca, a gdy
     * pusty — nazwa firmy sklepu (domyślny odbiorca). Może być null, gdy sklep nie
     * uzupełnił jeszcze żadnej z tych danych.
     */
    public function bankAccountHolderName(): ?string
    {
        return filled($this->bank_account_holder)
            ? $this->bank_account_holder
            : ($this->company_name ?: null);
    }

    /**
     * Czy dany użytkownik może oglądać niepubliczną treść sklepu (sklep-szkic
     * przed publikacją, nieaktywny produkt): tylko właściciel i administrator.
     * Gość i obcy sprzedawca — nie. Wspólny predykat bramek storefrontu.
     */
    public function canBePreviewedBy(?User $user): bool
    {
        return $user !== null && ($user->id === $this->owner_id || $user->isAdmin());
    }

    /**
     * Slug aktywnego szablonu storefrontu, z siatką bezpieczeństwa: gdy sklep nie
     * ma wyboru albo trzyma slug, którego już nie ma w configu (szablon wycofany),
     * spadamy na domyślny szablon. Kod pyta o motyw przez te metody, nie o kolumnę.
     */
    public function templateSlug(): string
    {
        $slug = $this->template ?: config('themes.default_template');

        return config("themes.templates.{$slug}") !== null
            ? $slug
            : config('themes.default_template');
    }

    /**
     * Nazwa szablonu (PL, widoczna) rozwiązywana ze sluga — jak przy pakietach,
     * zmienialna w configu bez ruszania bazy.
     */
    public function templateName(): string
    {
        return config("themes.templates.{$this->templateSlug()}.name", $this->templateSlug());
    }

    /**
     * Styl „chrome" szablonu — czym storefront maluje pasek górny i stopkę:
     * `neutral` (szary tint z `ink`), `brand_tint` (delikatny tint z `brand`)
     * albo `brand` (pełny kolor marki). Nieznana wartość spada na `neutral`,
     * więc szablon bez tego klucza zostaje przy dotychczasowym wyglądzie.
     */
    public function templateChrome(): string
    {
        $chrome = config("themes.templates.{$this->templateSlug()}.chrome");

        return in_array($chrome, ['neutral', 'brand_tint', 'brand'], true) ? $chrome : 'neutral';
    }

    /**
     * Faktura na pasku i stopce (chrome) — rysowana czystym CSS w kolorze tła,
     * żeby kolorowy chrome nie był gładką aplą. Nieznana wartość = bez wzoru.
     */
    public function templateChromeTexture(): string
    {
        $texture = config("themes.templates.{$this->templateSlug()}.chrome_texture");

        return in_array($texture, ['awning', 'dots', 'pinpoint', 'stripes'], true) ? $texture : 'none';
    }

    /**
     * O ile procent boxy (st-card) są ciemniejsze od tła strony — per szablon
     * (`card_mix` w definicji), z globalnym domyślnym `themes.card_mix`.
     * Skalowanie w procentach: sprzedawca „ustawia tło", boxy idą za nim.
     */
    public function templateCardMix(): int
    {
        $mix = config("themes.templates.{$this->templateSlug()}.card_mix", config('themes.card_mix', 4));

        return max(0, min(100, (int) $mix));
    }

    /**
     * Krój nagłówków i tytułów kart: `decorative` (ozdobny serif) albo `plain`
     * (ten sam sans co treść). Oś NIEZALEŻNA od szablonu — patrz „Charakter
     * sklepu" w config/themes.php. Nieznana wartość spada na domyślną, więc
     * sklep bez wyboru zostaje przy dotychczasowym wyglądzie.
     */
    public function themeFont(): string
    {
        $font = $this->theme['font'] ?? null;

        return is_string($font) && config("themes.fonts.{$font}") !== null
            ? $font
            : config('themes.default_font', 'decorative');
    }

    /**
     * Korekta stopni nagłówków dla wybranego kroju — mapa końcówka → rozmiar
     * (`4xl` → `1.9375rem`), do wypisania jako `--text-4xl` NA elementach
     * `.font-serif`. Krój dekoracyjny nie koryguje nic (pusta tablica): to pod
     * niego dobrane są rozmiary w widokach.
     *
     * @return array<string, string>
     */
    public function themeFontSizes(): array
    {
        return config("themes.fonts.{$this->themeFont()}.sizes", []);
    }

    /**
     * Stopień zaokrąglenia boxów: `small` / `medium` / `large`. Druga oś
     * charakteru, też niezależna od szablonu.
     */
    public function themeRadius(): string
    {
        $radius = $this->theme['radius'] ?? null;

        return is_string($radius) && config("themes.radii.{$radius}") !== null
            ? $radius
            : config('themes.default_radius', 'large');
    }

    /**
     * Zmienne CSS zaokrągleń dla wybranego stopnia — mapa końcówka → rozmiar
     * (`lg` → `0.5rem`), gotowa do wypisania jako `--radius-lg` w :root
     * storefrontu. To one stoją w regułach `.rounded-*` zbudowanego Tailwinda.
     *
     * @return array<string, string>
     */
    public function themeRadiusVars(): array
    {
        return config("themes.radii.{$this->themeRadius()}.vars", []);
    }

    /**
     * Kolor własny sklepu („kolor przewodni") w postaci kanonicznej „#RRGGBB",
     * lub null gdy nieustawiony/niepoprawny. Nadpisuje TYLKO token `brand`
     * (akcent); reszta kolorów dziedziczy z bazowej palety szablonu. Trzymany
     * w JSON `theme` pod kluczem `brand_color`.
     */
    public function brandColor(): ?string
    {
        $color = $this->theme['brand_color'] ?? null;

        return is_string($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $color)
            ? strtoupper($color)
            : null;
    }

    /**
     * Klucz wybranej palety w ramach szablonu. Sprzedawca wybiera gotowca; brak
     * wyboru lub paleta nieobecna w szablonie (np. po zmianie szablonu) → domyślna
     * paleta szablonu. Wybór trzymany w JSON `theme` pod kluczem `palette`.
     *
     * „custom" to paleta WIRTUALNA — istnieje tylko wtedy, gdy realnie ustawiono
     * kolor własny. Gdy koloru brak (sprzedawca go wyczyścił), a wybór został na
     * „custom", spadamy na domyślną paletę szablonu (siatka bezpieczeństwa).
     */
    public function themePalette(): string
    {
        $slug = $this->templateSlug();
        $chosen = $this->theme['palette'] ?? null;

        if ($chosen === 'custom') {
            return $this->brandColor() !== null
                ? 'custom'
                : config("themes.templates.{$slug}.default_palette");
        }

        if ($chosen !== null && config("themes.templates.{$slug}.palettes.{$chosen}") !== null) {
            return $chosen;
        }

        return config("themes.templates.{$slug}.default_palette");
    }

    /**
     * Gęstość wykazu dobrana do SKALI katalogu: {columns, per_page}. Bierzemy
     * najmniejszy układ z drabinki (config themes.listing.steps), przy którym
     * wszystkie aktywne produkty mieszczą się w `max_pages` podstronach — rosnąc
     * najpierw wierszami przy 3 kolumnach, a dopiero potem skokiem na 4 kolumny.
     * Wielkość kafla robią kolumny (3 = duże/wyraziste, 4 = gęstsze); `rows`
     * steruje tylko długością strony (per_page = columns × rows).
     *
     * Liczba liczona z aktywnych produktów CAŁEGO sklepu, nie z przefiltrowanego
     * widoku — dzięki temu skala (i układ) są stałe niezależnie od tagów/sortu:
     * treść nie „pływa" przy filtrowaniu.
     *
     * @return array{columns: int, per_page: int}
     */
    public function listingDensity(): array
    {
        $count = $this->products()->where('is_active', true)->count();
        $steps = config('themes.listing.steps');
        $maxPages = (int) config('themes.listing.max_pages', 3);

        foreach ($steps as $step) {
            $perPage = $step['columns'] * $step['rows'];
            if ($count <= $maxPages * $perPage) {
                return ['columns' => $step['columns'], 'per_page' => $perPage];
            }
        }

        // Powyżej ostatniego stopnia zostaje sufit — podstron po prostu przybywa.
        $last = end($steps);

        return ['columns' => $last['columns'], 'per_page' => $last['columns'] * $last['rows']];
    }

    /**
     * Rozwiązane tokeny kolorów (brand/brand_ink/surface/ink) dla aktywnego
     * szablonu + palety. To jedyne źródło, z którego storefront wyliczy zmienne
     * CSS (:root) — reszta odcieni powstaje z tych w CSS, nie jest wybierana.
     *
     * @return array<string, string>
     */
    public function themeTokens(): array
    {
        $slug = $this->templateSlug();
        $palette = $this->themePalette();

        // Kolor własny: baza = domyślna paleta szablonu (surface/ink dziedziczą,
        // więc sklep zostaje czytelny — jasny albo ciemny wg szablonu), a token
        // `brand` nadpisany kolorem sprzedawcy; `brand_ink` liczony dla kontrastu.
        if ($palette === 'custom') {
            $default = config("themes.templates.{$slug}.default_palette");
            $base = config("themes.templates.{$slug}.palettes.{$default}.tokens", []);
            $brand = $this->brandColor();

            return array_merge($base, [
                'brand' => $brand,
                'brand_ink' => Color::readableInkOn($brand),
            ]);
        }

        return config("themes.templates.{$slug}.palettes.{$palette}.tokens", []);
    }

    /**
     * Przypisuje pakiet i robi SNAPSHOT jego uprawnień: kopiuje `entitlements` z
     * configu do kolumny sklepu. Od tej chwili sklep żyje własnym zestawem —
     * późniejsza zmiana definicji pakietu w configu go nie dotyka („kupiłeś,
     * masz"). Wołane przy rejestracji, zakupie/zmianie pakietu i z konsoli admina.
     */
    public function assignPackage(string $slug): void
    {
        $package = config("shop.packages.{$slug}");

        if ($package === null || ! isset($package['entitlements'])) {
            throw new \InvalidArgumentException("Nieznany pakiet: {$slug}");
        }

        $this->package = $slug;
        $this->entitlements = $package['entitlements'];
        $this->price_yearly = $package['price_yearly'] ?? 0;
        $this->save();
    }

    /**
     * Cena roczna (BRUTTO, zł) sklepu: wygrywa zapisany snapshot per sklep,
     * a gdy go brak (legacy) — fallback do aktualnego cennika pakietu w configu.
     * Analogicznie do entitlement(): źródłem prawdy jest to, co zapisane na sklepie
     * (deal per klient, cena zamrożona), nie definicja pakietu.
     */
    public function priceYearly(): float
    {
        return (float) ($this->price_yearly
            ?? config("shop.packages.{$this->package}.price_yearly", 0));
    }

    /**
     * Czy abonament sklepu jest opłacony i biegnie.
     *
     * Trzy drogi do „tak": dostęp gratisowy (`comped` — nie wygasa nigdy),
     * pakiet darmowy (nie ma czego opłacać) albo termin (wraz z karencją)
     * jeszcze nie minął.
     *
     * PUSTA DATA przy płatnym pakiecie = BEZTERMINOWO. Świadomie na korzyść
     * sklepu: konsola admina pozwala jej nie ustawić, a gdyby brak daty znaczył
     * „wygasło", wszystkie dotychczasowe ręczne nadania zgasłyby z dnia na dzień.
     */
    public function subscriptionActive(): bool
    {
        if ($this->comped) {
            return true;
        }

        // Pakiet bez opłaty nie ma jak wygasnąć.
        if ((float) config("shop.packages.{$this->package}.price_yearly", 0) <= 0) {
            return true;
        }

        $locksAt = $this->subscriptionLocksAt();

        return $locksAt === null || $locksAt->isFuture();
    }

    /**
     * Moment, w którym funkcje płatnego pakietu FAKTYCZNIE gasną: termin
     * opłacenia plus karencja. Rozdzielenie „termin zapłaty" od „chwili
     * wyłączenia" jest sednem karencji — data na fakturze zostaje ta sama, a
     * sklep dostaje kilka dni na przelew. null = nie ma czego gasić.
     */
    public function subscriptionLocksAt(): ?CarbonInterface
    {
        if ($this->comped || $this->subscription_ends_at === null) {
            return null;
        }

        if ((float) config("shop.packages.{$this->package}.price_yearly", 0) <= 0) {
            return null;
        }

        return $this->subscription_ends_at
            ->copy()
            ->addDays((int) config('shop.subscription.grace_days'));
    }

    /**
     * Czy sklep jest PO terminie, ale jeszcze w karencji — pełne funkcje plus
     * baner „opłać do…". Stan przejściowy, o którym trzeba mówić głośno; cicha
     * karencja byłaby dla sprzedawcy nieodróżnialna od „wszystko w porządku".
     */
    public function inSubscriptionGrace(): bool
    {
        return $this->subscription_ends_at !== null
            && $this->subscription_ends_at->isPast()
            && $this->subscriptionActive()
            && ! $this->comped
            && (float) config("shop.packages.{$this->package}.price_yearly", 0) > 0;
    }

    /**
     * Ile dni karencji zostało (0, gdy właśnie się kończy). Do banera i maila.
     */
    public function graceDaysLeft(): int
    {
        $locksAt = $this->subscriptionLocksAt();

        if ($locksAt === null) {
            return 0;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($locksAt->copy()->startOfDay(), false));
    }

    /**
     * Slug pakietu, którego zasady FAKTYCZNIE obowiązują: po wygaśnięciu to
     * pakiet darmowy, choć w bazie dalej stoi kupiony (snapshot nietknięty —
     * odnowienie to zmiana jednej daty). Do wszystkich miejsc, gdzie mówimy
     * sprzedawcy o limitach; konsola admina używa `package` wprost, bo ma
     * pokazywać, co klient kupił.
     */
    public function effectivePackage(): string
    {
        return $this->subscriptionActive()
            ? $this->package
            : config('shop.default_package');
    }

    /**
     * Nazwa pakietu, którego zasady faktycznie obowiązują — patrz
     * `effectivePackage()`.
     */
    public function effectivePackageName(): string
    {
        return config("shop.packages.{$this->effectivePackage()}.name", $this->effectivePackage());
    }

    /**
     * Resolver uprawnień: aplikacja pyta „ile/czy X?", nie „jaki pakiet?".
     *
     * Po wygaśnięciu abonamentu czytamy uprawnienia pakietu DARMOWEGO, ale
     * SNAPSHOTU NIE RUSZAMY. To celowe: nadpisanie snapshotu skasowałoby ręczne
     * nadania (moduł dany komuś gestem poza pakietem) i po opłacie nie byłoby
     * jak ich odtworzyć. Dzięki temu odnowienie = zmiana jednej daty i wraca
     * wszystko, łącznie z dodatkami.
     *
     * Wygrywa zapisany snapshot sklepu; gdy brak klucza (sklep bez snapshotu —
     * legacy, albo nowe uprawnienie dodane po zakupie) — fallback do definicji
     * aktualnego pakietu w configu jako siatka bezpieczeństwa.
     */
    public function entitlement(string $key): mixed
    {
        if (! $this->subscriptionActive()) {
            return config('shop.packages.'.config('shop.default_package').".entitlements.{$key}");
        }

        return $this->rawEntitlement($key);
    }

    /**
     * Uprawnienie WPROST ZE SNAPSHOTU, bez patrzenia na abonament — „co klient
     * kupił". Do konsoli admina, która musi pokazać stan zakupu także wtedy, gdy
     * abonament właśnie wygasł, oraz wszędzie, gdzie edytujemy uprawnienia.
     */
    public function rawEntitlement(string $key): mixed
    {
        $snapshot = $this->entitlements ?? [];

        if (array_key_exists($key, $snapshot)) {
            return $snapshot[$key];
        }

        return config("shop.packages.{$this->package}.entitlements.{$key}");
    }

    /**
     * Nazwa pakietu (PL, naklejka widoczna dla klienta) rozwiązywana ze sluga.
     * Slug w bazie jest stały; nazwę można zmieniać w configu bez ruszania danych.
     */
    public function packageName(): string
    {
        return config("shop.packages.{$this->package}.name", $this->package);
    }
}
