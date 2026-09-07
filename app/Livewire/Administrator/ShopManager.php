<?php

namespace App\Livewire\Administrator;

use App\Models\PackageChange;
use App\Models\Shop;
use App\Services\ProductLimitLock;
use App\Support\Mode;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Konsola admina — edytor pojedynczego sklepu. Ręczne sterowanie snapshotem:
 * pakiet (preset), poszczególne uprawnienia, limit produktów, cena roczna,
 * data końca abonamentu i flaga `comped`.
 *
 * WAŻNE: zapis pisze wprost do snapshotu sklepu (`entitlements` + `price_yearly`
 * + `subscription_ends_at` + `comped` + `package`), NIE woła assignPackage —
 * dzięki temu ręczne nadpisania (np. moduł spoza pakietu dla dobrego klienta)
 * NIE są kasowane. „Nadaj pakiet" tylko WYPEŁNIA formularz wartościami presetu
 * z configu; dopiero „Zapisz" je zatwierdza. Odnowienie/lepkość uprawnień —
 * patrz plan pakietów (uprawnienia lepkie, cena idzie za cennikiem).
 */
class ShopManager extends Component
{
    public Shop $shop;

    /** Slug pakietu (naklejka) — ustawiany presetem, zapisywany jako etykieta. */
    public string $package = '';

    public int $max_products = 0;

    /** Tygodniowa pula zadań AI — uprawnienie liczbowe, jak limit produktów. */
    public int $ai_weekly_limit = 0;

    /** Ile kont pracowniczych mieści sklep. Zero = funkcji nie ma. */
    public int $max_employees = 0;

    public bool $online_payments = false;

    public bool $courier_shipping = false;

    public bool $invoices = false;

    public bool $ga_analytics = false;

    public bool $order_editing = false;

    public bool $discount_codes = false;

    public bool $bulk_mail = false;

    /** Cena roczna BRUTTO (zł). */
    public string $price_yearly = '0';

    /** Data końca abonamentu (Y-m-d) lub pusto = bezterminowo/nieustalone. */
    public string $subscription_ends_at = '';

    public bool $comped = false;

    /**
     * Presety pakietu, które wolno nadać W TYM WDROŻENIU.
     *
     * „Sklep dedykowany" opisuje instalację na SERWERZE KLIENTA, wykupioną raz i
     * z uprawnieniami bez limitów. Sklep na platformie nigdy nim nie jest, więc
     * w Kramio ten przycisk nie ma znaczenia — ma za to skutek: jedno kliknięcie
     * dawało dowolnemu sklepowi wszystko za 0 zł, bezterminowo. W instalacji
     * dedykowanej jest odwrotnie: to jedyny preset, który się tam nadaje.
     *
     * Pakiet, który sklep JUŻ MA, zostaje na liście niezależnie od trybu —
     * inaczej formularz nie miałby jak pokazać stanu faktycznego, a walidacja
     * `in:` odrzuciłaby zapis bez zmiany pakietu.
     *
     * @return array<string, array<string, mixed>>
     */
    public function assignablePackages(): array
    {
        $packages = config('shop.packages');

        if (Mode::dedicated()) {
            return $packages;
        }

        return array_filter(
            $packages,
            fn (array $package, string $slug): bool => ($package['available'] ?? true) !== false
                || $slug === $this->package,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Kanoniczne klucze uprawnień LICZBOWYCH + etykiety PL (do UI).
     *
     * Lista, a nie trzy wypisane z ręki miejsca — bo pominięcie uprawnienia w
     * KTÓRYMKOLWIEK z nich kończy się tak samo: zapis odbudowuje cały snapshot,
     * więc klucz spoza formularza po prostu z niego znika i ręczne nadanie
     * przepada. Zdarzyło się to raz przy puli AI i drugi raz przy kontach
     * pracowniczych, zanim w ogóle trafiły do konsoli. Nowe uprawnienie liczbowe
     * dopisuje się TU i w widoku — mount, preset, walidacja i zapis czytają stąd.
     *
     * @return array<string, string>
     */
    public function numericEntitlements(): array
    {
        return [
            'max_products' => 'Limit produktów',
            'ai_weekly_limit' => 'Zadania AI / tydzień',
            'max_employees' => 'Konta pracowników',
        ];
    }

    /**
     * Kanoniczne klucze uprawnień boolowskich + etykiety PL (do UI).
     *
     * @return array<string, string>
     */
    public function booleanEntitlements(): array
    {
        return [
            'online_payments' => 'Płatności online',
            'courier_shipping' => 'Wysyłka kurierska i paczkomat (InPost)',
            'invoices' => 'Faktury (Fakturownia)',
            'ga_analytics' => 'Google Analytics / Tag Manager',
            'order_editing' => 'Edycja zamówienia',
            'discount_codes' => 'Kody rabatowe',
            'bulk_mail' => 'Korespondencja seryjna',
        ];
    }

    public function mount(Shop $shop): void
    {
        $this->shop = $shop;
        $this->package = $shop->package ?? config('shop.default_package');
        // `rawEntitlement`, nie `entitlement`: konsola pokazuje, CO KLIENT KUPIŁ.
        // Po wygaśnięciu abonamentu odczyt efektywny dałby uprawnienia Kramu, a
        // zapis takiego formularza wykasowałby snapshot — i po opłacie nie byłoby
        // czego przywrócić.
        foreach (array_keys($this->numericEntitlements()) as $key) {
            $this->{$key} = (int) $shop->rawEntitlement($key);
        }

        foreach (array_keys($this->booleanEntitlements()) as $key) {
            $this->{$key} = (bool) $shop->rawEntitlement($key);
        }

        $this->price_yearly = (string) (int) round($shop->priceYearly());
        $this->subscription_ends_at = $shop->subscription_ends_at?->format('Y-m-d') ?? '';
        $this->comped = (bool) $shop->comped;
    }

    /**
     * „Nadaj pakiet" — wypełnia formularz wartościami presetu z configu (bez
     * zapisu). Admin może potem nadpisać pojedyncze pola i zatwierdzić „Zapisz".
     */
    public function applyPreset(string $slug): void
    {
        $package = config("shop.packages.{$slug}");

        // Brama także tutaj, nie tylko w widoku: preset spoza tego wdrożenia nie
        // może wejść bocznymi drzwiami przez żądanie Livewire.
        if ($package === null || ! array_key_exists($slug, $this->assignablePackages())) {
            return;
        }

        $this->package = $slug;
        foreach (array_keys($this->numericEntitlements()) as $key) {
            $this->{$key} = (int) ($package['entitlements'][$key] ?? 0);
        }

        foreach (array_keys($this->booleanEntitlements()) as $key) {
            $this->{$key} = (bool) ($package['entitlements'][$key] ?? false);
        }

        $this->price_yearly = (string) (int) round($package['price_yearly'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'package' => ['required', 'string', 'in:'.implode(',', array_keys($this->assignablePackages()))],
            'price_yearly' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'subscription_ends_at' => ['nullable', 'date'],
            'comped' => ['boolean'],
            // Sufit MUSI mieścić preset „Sklep dedykowany" (milion produktów,
            // sto tysięcy zadań AI). Te liczby znaczą tam „bez limitu" i są
            // celowo duże, a nie `null`: `(int) null` daje zero, a przy zerze
            // ProductLimitLock zablokowałby sklep na pierwszym produkcie.
            // Przy dawnym sufcie 100 000 sklepu na tym presecie NIE DAŁO SIĘ
            // zapisać w konsoli — walidacja odrzucała jego własny stan.
            ...collect(array_keys($this->numericEntitlements()))
                ->mapWithKeys(fn (string $key) => [$key => ['required', 'integer', 'min:0', 'max:10000000']])
                ->all(),
        ];
    }

    public function save(): void
    {
        $this->validate();

        // Snapshot musi nieść WSZYSTKIE uprawnienia, także liczbowe — klucz
        // pominięty tutaj znika ze snapshotu przy każdym „Zapisz". Stąd pętla
        // po zadeklarowanej liście zamiast wypisywania kluczy z ręki.
        $entitlements = [];

        foreach (array_keys($this->numericEntitlements()) as $key) {
            $entitlements[$key] = (int) $this->{$key};
        }

        foreach (array_keys($this->booleanEntitlements()) as $key) {
            $entitlements[$key] = (bool) $this->{$key};
        }

        $this->shop->forceFill([
            'package' => $this->package,
            'entitlements' => $entitlements,
            'price_yearly' => $this->price_yearly,
            'subscription_ends_at' => $this->subscription_ends_at !== ''
                ? Carbon::parse($this->subscription_ends_at)->endOfDay()
                : null,
            'comped' => $this->comped,
        ])->save();

        // Historia pakietu: ręczne nadanie musi być widoczne dla sprzedawcy,
        // inaczej pakiet w panelu wygląda, jakby wziął się z powietrza.
        // Metoda sama pomija wpis, gdy zmieniły się tylko uprawnienia.
        $this->shop->refresh()->recordPackageChange(PackageChange::SOURCE_ADMIN);

        // Zamek limitu w obie strony: przedłużenie terminu z ręki przywraca
        // schowane produkty, obniżenie limitu chowa nadwyżkę. Bez tego ręczna
        // zmiana zostawiałaby sklep w stanie niezgodnym z jego uprawnieniami.
        $lock = app(ProductLimitLock::class);
        $lock->restore($this->shop->fresh());
        $lock->enforce($this->shop->fresh());

        session()->flash('success', 'Zapisano ustawienia sklepu „'.$this->shop->name.'".');

        $this->redirect(route('administrator.shops.edit', $this->shop), navigate: false);
    }

    public function render()
    {
        return view('livewire.administrator.shop-manager');
    }
}
