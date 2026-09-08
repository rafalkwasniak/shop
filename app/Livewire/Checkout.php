<?php

namespace App\Livewire;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Exceptions\CartNeedsReviewException;
use App\Models\Customer;
use App\Models\Shop;
use App\Services\CartService;
use App\Services\CompanyLookup;
use App\Services\DiscountResolver;
use App\Services\NipService;
use App\Services\OrderService;
use App\Services\PhoneService;
use App\Support\DiscountAllocation;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Kasa (spec „Dane do zamówienia", „Rejestracja klienta → zakup bez
 * rejestracji"). Zbiera dane kupującego, wybór dostawy/płatności (tylko metody
 * włączone przez sprzedawcę) i składa zamówienie przez OrderService. Konto
 * klienta („Utwórz konto") oraz zgody z LegalDocument dojdą w module kont.
 */
class Checkout extends Component
{
    public int $shopId;

    // Dane kupującego.
    public string $buyer_name = '';

    public string $buyer_surname = '';

    public string $buyer_email = '';

    public string $buyer_phone = '';

    // Zakup jako firma (dane rozliczeniowe do FV — niezależne od dostawy).
    public bool $is_company = false;

    public string $company_name = '';

    public string $company_nip = '';

    public string $company_street = '';

    public string $company_building_number = '';

    public string $company_apartment_number = '';

    public string $company_postal_code = '';

    public string $company_city = '';

    // Adres dostawy (wypełniany tylko przy dostawie pod adres — addressDelivery()).
    public string $ship_street = '';

    public string $ship_building_number = '';

    public string $ship_apartment_number = '';

    public string $ship_postal_code = '';

    public string $ship_city = '';

    // Wskazany paczkomat. Kod klient wpisuje z palca; mapa (geowidget) dojdzie
    // jako nakładka wypełniająca to pole — nie jest warunkiem złożenia zamówienia.
    // `parcel_locker_address` uzupełni dopiero mapa (opis punktu); przy wpisie
    // ręcznym zostaje pusty i zamówienie niesie sam kod.
    public string $parcel_locker_code = '';

    public string $parcel_locker_address = '';

    // Metody i uwagi.
    public string $delivery_method = '';

    public string $payment_method = '';

    public string $note = '';

    public bool $accept_terms = false;

    public bool $accept_privacy = false;

    /** „Załóż konto" — działa tylko dla gościa z wolnym e-mailem (patrz resolveCustomer). */
    public bool $create_account = false;

    /** Komunikaty z finalnej weryfikacji (auto-korekta koszyka). */
    public array $reviewMessages = [];

    public function mount(int $shopId): void
    {
        $this->shopId = $shopId;

        $this->delivery_method = array_key_first($this->deliveryOptions()) ?? '';
        $this->payment_method = array_key_first($this->paymentOptions()) ?? '';

        // Zalogowany klient tego sklepu: uzupełnij dane z konta (edytowalne).
        if (($customer = $this->authCustomer()) !== null) {
            $this->buyer_name = $customer->name ?? '';
            $this->buyer_surname = $customer->surname ?? '';
            $this->buyer_email = $customer->email;
            $this->buyer_phone = $customer->phone ?? '';

            $this->prefillFromLastOrder($customer);
        }
    }

    /**
     * Podpowiedzi z OSTATNIEGO zamówienia klienta: dane do faktury (firma), adres
     * wysyłki oraz metody dostawy/płatności. To wygoda, nie ustalenie — klient może
     * wszystko zmienić. Bierzemy najświeższe zamówienie (także anulowane: adres i
     * dane firmy są w nim wciąż poprawne). Metodę podpowiadamy tylko, gdy sklep
     * nadal ją oferuje; adres wysyłki kopiujemy tylko, gdy WYBRANA dostawa jest
     * kurierska (czyli tamto zamówienie szło kurierem i sklep dalej to daje).
     */
    private function prefillFromLastOrder(Customer $customer): void
    {
        $last = $customer->orders()->latest()->first();

        if ($last === null) {
            return;
        }

        // Dane do faktury (FV).
        $this->is_company = $last->is_company;
        if ($last->is_company) {
            $this->company_name = $last->company_name ?? '';
            $this->company_nip = $last->company_nip ?? '';
            $this->company_street = $last->company_street ?? '';
            $this->company_building_number = $last->company_building_number ?? '';
            $this->company_apartment_number = $last->company_apartment_number ?? '';
            $this->company_postal_code = $last->company_postal_code ?? '';
            $this->company_city = $last->company_city ?? '';
        }

        // Metoda dostawy — tylko jeśli sklep dalej ją oferuje.
        $lastDelivery = $last->delivery_method?->value;
        if ($lastDelivery !== null && array_key_exists($lastDelivery, $this->deliveryOptions())) {
            $this->delivery_method = $lastDelivery;
        }

        // Adres wysyłki — po ustaleniu dostawy, tylko gdy metoda go potrzebuje.
        if ($this->addressDelivery()) {
            $this->ship_street = $last->ship_street ?? '';
            $this->ship_building_number = $last->ship_building_number ?? '';
            $this->ship_apartment_number = $last->ship_apartment_number ?? '';
            $this->ship_postal_code = $last->ship_postal_code ?? '';
            $this->ship_city = $last->ship_city ?? '';
        }

        // Paczkomat — ta sama zasada. Klient zwykle wraca do tego samego punktu
        // (pod domem, przy pracy), więc przepisywanie kodu za każdym razem to
        // czysta udręka. Adres punktu niesiemy razem z kodem, żeby podpowiedź
        // dało się pokazać po ludzku, a nie samym „KRA01A".
        if ($this->parcelDelivery()) {
            $this->parcel_locker_code = $last->parcel_locker_code ?? '';
            $this->parcel_locker_address = $last->parcel_locker_address ?? '';
        }

        // Metoda płatności — po dostawie (jej opcje zależą od wyboru dostawy).
        $lastPayment = $last->payment_method?->value;
        if ($lastPayment !== null && array_key_exists($lastPayment, $this->paymentOptions())) {
            $this->payment_method = $lastPayment;
        }
    }

    private function shop(): Shop
    {
        return Shop::findOrFail($this->shopId);
    }

    /**
     * Zalogowany klient TEGO sklepu (lub null). Do niego przypniemy zamówienie i
     * jego danymi wypełniamy kasę; guard `customer` jest scope'owany per sklep.
     */
    #[Computed]
    public function authCustomer(): ?Customer
    {
        $customer = Auth::guard('customer')->user();

        return $customer instanceof Customer && $customer->shop_id === $this->shopId ? $customer : null;
    }

    /**
     * Czy wpisany e-mail ma już konto w tym sklepie — wtedy zamiast „załóż konto"
     * pokazujemy informację, że zamówienie trafi do historii istniejącego konta.
     */
    #[Computed]
    public function accountExists(): bool
    {
        return filled($this->buyer_email)
            && $this->shop()->customers()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($this->buyer_email)])
                ->exists();
    }

    /**
     * Dostępne metody dostawy (tylko włączone i realnie gotowe) — z modelu, tym
     * samym źródłem, z którego storefront bierze decyzję o przycisku „Do koszyka".
     *
     * @return array<string, string>
     */
    public function deliveryOptions(): array
    {
        $options = [];

        foreach ($this->shop()->availableDeliveryMethods() as $method) {
            $options[$method->value] = $method->label();
        }

        return $options;
    }

    /**
     * WYBRANA metoda dostawy (null, gdy pole trzyma śmieć).
     */
    public function selectedDelivery(): ?DeliveryMethod
    {
        return DeliveryMethod::tryFrom($this->delivery_method);
    }

    /**
     * Czy WYBRANA metoda to wysyłka (a więc jest koszt dostawy i nie ma
     * płatności przy odbiorze). Obejmuje kuriera I paczkomat.
     */
    public function shippedDelivery(): bool
    {
        return $this->selectedDelivery()?->isShipped() ?? false;
    }

    /**
     * Czy WYBRANA metoda potrzebuje adresu klienta — rozstrzyga o widoczności
     * i wymagalności bloku adresu. Osobno od shippedDelivery(), bo paczkomat
     * jest wysyłką BEZ adresu (paczka jedzie do skrytki, nie pod dom).
     */
    public function addressDelivery(): bool
    {
        return $this->selectedDelivery()?->requiresShippingAddress() ?? false;
    }

    /**
     * Czy WYBRANA metoda potrzebuje wskazania paczkomatu — rozstrzyga o
     * widoczności i wymagalności pola z kodem punktu.
     */
    public function parcelDelivery(): bool
    {
        return $this->selectedDelivery()?->requiresParcelLocker() ?? false;
    }

    /**
     * Czy WYBRANA metoda to pobranie. Rozstrzyga o zniknięciu listy płatności
     * (nie ma czego wybierać) oraz o limicie kwoty.
     */
    public function cashOnDelivery(): bool
    {
        return $this->selectedDelivery()?->isCashOnDelivery() ?? false;
    }

    /**
     * Górny limit kwoty pobrania dla WYBRANEJ metody — paczkomat inkasuje przez
     * terminal skrytki, kurier przyjmuje też gotówkę, więc progi są różne.
     * null = wybrana metoda nie jest pobraniowa.
     */
    public function codLimit(): ?float
    {
        $method = $this->selectedDelivery();

        if ($method === null || ! $method->isCashOnDelivery()) {
            return null;
        }

        $key = $method->requiresParcelLocker() ? 'parcel_locker' : 'courier';

        return (float) config('shop.cash_on_delivery.max_amount.'.$key);
    }

    /**
     * Dostępne metody płatności (tylko włączone przez sprzedawcę).
     *
     * @return array<string, string>
     */
    public function paymentOptions(): array
    {
        // Pobranie nie daje wyboru: metoda płatności wynika wprost z dostawy,
        // więc lista zwija się do jednej pozycji. Klient nie zobaczy tu radia
        // (widok pokazuje zdanie zamiast listy), ale reguła walidacji i tak ma
        // się o co oprzeć, a `updatedDeliveryMethod()` samo przestawi wybór.
        if ($this->cashOnDelivery()) {
            return [PaymentMethod::CashOnDelivery->value => PaymentMethod::CashOnDelivery->label()];
        }

        $options = [];

        foreach ($this->shop()->availablePaymentMethods() as $method) {
            // Płatność przy odbiorze ma sens tylko przy odbiorze osobistym — przy
            // wysyłce nie ma „odbioru", pod którym klient zapłaci. Kurier ⇒ przelew.
            // To jedyne zawężenie zależne od WYBRANEJ dostawy, więc model listy
            // płatności nie zna, a kasa je tu dokłada.
            if ($method === PaymentMethod::PayOnPickup && $this->shippedDelivery()) {
                continue;
            }

            $options[$method->value] = $method->label();
        }

        return $options;
    }

    /**
     * Zmiana metody dostawy może zawęzić dostępne płatności (wybór kuriera zdejmuje
     * „płatność przy odbiorze"). Gdy bieżąca płatność wypadła z listy — przełącz na
     * pierwszą dostępną, żeby nie zostać z zaznaczeniem spoza opcji.
     */
    public function updatedDeliveryMethod(): void
    {
        $available = array_keys($this->paymentOptions());

        if (! in_array($this->payment_method, $available, true)) {
            $this->payment_method = $available[0] ?? '';
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [
            'buyer_name' => ['required', 'string', 'max:100'],
            'buyer_surname' => ['required', 'string', 'max:100'],
            'buyer_email' => ['required', 'email', 'max:255'],
            'buyer_phone' => ['required', 'string', PhoneService::RULE],
            'delivery_method' => ['required', Rule::in(array_keys($this->deliveryOptions()))],
            'payment_method' => ['required', Rule::in(array_keys($this->paymentOptions()))],
            'note' => ['nullable', 'string', 'max:1000'],
            'accept_terms' => ['accepted'],
            'accept_privacy' => ['accepted'],
            'is_company' => ['boolean'],
            'create_account' => ['boolean'],
        ];

        if ($this->addressDelivery()) {
            $rules['ship_street'] = ['required', 'string', 'max:255'];
            $rules['ship_building_number'] = ['required', 'string', 'max:30'];
            $rules['ship_apartment_number'] = ['nullable', 'string', 'max:30'];
            $rules['ship_postal_code'] = ['required', 'string', 'max:12'];
            $rules['ship_city'] = ['required', 'string', 'max:120'];
        }

        if ($this->parcelDelivery()) {
            // Kształt kodu celowo LUŹNY (litery i cyfry). InPost nie gwarantuje
            // formatu na piśmie, a zbyt ostra reguła odrzuciłaby istniejący
            // paczkomat i zablokowała zakup — gorzej niż przepuścić literówkę,
            // którą sprzedawca zobaczy przed nadaniem.
            $rules['parcel_locker_code'] = ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/'];
            $rules['parcel_locker_address'] = ['nullable', 'string', 'max:255'];
        }

        if ($this->cashOnDelivery()) {
            // Limit kwoty pobrania sprawdzamy TU, a nie dopiero przy nadawaniu.
            // Inaczej klient dostałby potwierdzenie zamówienia, którego sprzedawca
            // nie ma jak wysłać — a odkręcanie tego spadłoby na człowieka.
            $limit = (float) $this->codLimit();

            $rules['delivery_method'][] = function (string $attribute, mixed $value, \Closure $fail) use ($limit): void {
                if ($this->payableTotal() > $limit) {
                    $fail('Przy tej kwocie InPost nie przyjmie pobrania (limit to '.Money::pln($limit).'). Wybierz inny sposób dostawy.');
                }
            };
        }

        if ($this->is_company) {
            $rules['company_name'] = ['required', 'string', 'max:255'];
            $rules['company_nip'] = ['required', function (string $attr, mixed $value, \Closure $fail): void {
                if (! app(NipService::class)->isValid((string) $value)) {
                    $fail('Podaj poprawny NIP.');
                }
            }];
            // Adres firmy — opcjonalny (pod przyszłą FV; wypełniany z NIP).
            $rules['company_street'] = ['nullable', 'string', 'max:255'];
            $rules['company_building_number'] = ['nullable', 'string', 'max:30'];
            $rules['company_apartment_number'] = ['nullable', 'string', 'max:30'];
            $rules['company_postal_code'] = ['nullable', 'string', 'max:12'];
            $rules['company_city'] = ['nullable', 'string', 'max:120'];
        }

        return $rules;
    }

    /**
     * Auto-uzupełnienie danych firmy po NIP (GUS → Biała lista MF), analogicznie
     * do panelu — tylko wprost z komponentu, bez endpointu. Nie znaleziono →
     * komunikat i ręczne uzupełnienie.
     */
    public function lookupCompany(): void
    {
        $nip = app(NipService::class)->normalize($this->company_nip);
        $this->company_nip = $nip ?? $this->company_nip;

        if ($nip === null || ! app(NipService::class)->isValid($nip)) {
            $this->addError('company_nip', 'Podaj poprawny NIP, aby pobrać dane.');

            return;
        }

        $data = app(CompanyLookup::class)->byNip($nip);

        if ($data === null) {
            $this->addError('company_nip', 'Nie znaleziono firmy dla tego NIP. Uzupełnij dane ręcznie.');

            return;
        }

        $this->company_name = $data['company_name'] ?? $this->company_name;
        $this->company_street = $data['street'] ?? '';
        $this->company_building_number = $data['building_number'] ?? '';
        $this->company_apartment_number = $data['apartment_number'] ?? '';
        $this->company_postal_code = $data['postal_code'] ?? '';
        $this->company_city = $data['city'] ?? '';
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'accept_terms.accepted' => 'Zaakceptuj regulamin, aby złożyć zamówienie.',
            'accept_privacy.accepted' => 'Zaakceptuj politykę prywatności, aby złożyć zamówienie.',
            'buyer_phone.regex' => PhoneService::RULE_MESSAGE,
        ];
    }

    /**
     * Czytelne nazwy pól w komunikatach walidacji — bez tego Laravel wstawia
     * surową nazwę właściwości („buyer phone"). Konwencja: interfejs po polsku.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'buyer_name' => 'imię',
            'buyer_surname' => 'nazwisko',
            'buyer_email' => 'e-mail',
            'buyer_phone' => 'telefon',
            'delivery_method' => 'sposób dostawy',
            'payment_method' => 'sposób płatności',
            'ship_street' => 'ulica',
            'ship_building_number' => 'numer budynku',
            'ship_apartment_number' => 'numer lokalu',
            'ship_postal_code' => 'kod pocztowy',
            'ship_city' => 'miejscowość',
            'parcel_locker_code' => 'kod paczkomatu',
            'note' => 'uwagi',
            'accept_terms' => 'regulamin',
            'accept_privacy' => 'polityka prywatności',
            'company_name' => 'nazwa firmy',
            'company_nip' => 'NIP',
            'company_street' => 'ulica',
            'company_building_number' => 'numer budynku',
            'company_apartment_number' => 'numer lokalu',
            'company_postal_code' => 'kod pocztowy',
            'company_city' => 'miejscowość',
        ];
    }

    public function place(OrderService $orders)
    {
        $this->buyer_phone = app(PhoneService::class)->normalize($this->buyer_phone) ?? $this->buyer_phone;

        if ($this->is_company) {
            // normalize() zwraca null dla wejścia bez cyfr (pusty NIP, same
            // litery). Zostawiamy wtedy to, co wpisał klient — walidacja niżej
            // powie mu, co jest nie tak, zamiast wywracać się na typie.
            $this->company_nip = app(NipService::class)->normalize($this->company_nip) ?? $this->company_nip;
        }

        // Kod paczkomatu: InPost zapisuje go wersalikami (KRA01A), a klient
        // przepisuje z pamięci albo maila — bywa „kra01a" i ze spacjami.
        // Normalizujemy, zamiast odrzucać poprawny wybór za wielkość liter.
        $this->parcel_locker_code = strtoupper(str_replace(' ', '', trim($this->parcel_locker_code)));

        $data = $this->validate();

        try {
            $order = $orders->place($this->shop(), $data, $this->authCustomer());
        } catch (CartNeedsReviewException $e) {
            $this->reviewMessages = $e->messages;
            $this->dispatch('cart-updated');   // licznik i koszyk odświeżone

            return null;
        }

        // Numer zamówienia trzymamy w sesji — strona podziękowania czyta go stąd
        // (bez wystawiania cudzego zamówienia w URL).
        session()->put('recent_order_id', $order->id);

        return $this->redirect('/kasa/dziekujemy', navigate: false);
    }

    /**
     * Wyliczenie kwot koszyka dla BIEŻĄCEGO wyboru dostawy: pozycje, rabat z
     * kodu i koszt dostawy. Wydzielone z render(), bo tej samej kwoty potrzebuje
     * walidacja limitu pobrania — a limit liczony inną drogą niż podsumowanie
     * odrzucałby zamówienia bez widocznego dla klienta powodu.
     *
     * @return array<string, mixed>
     */
    private function pricing(): array
    {
        $cart = app(CartService::class);
        $lines = $cart->lines($this->shopId);
        $shop = $this->shop();
        $gross = $lines->sum('line_total');

        // Kod z koszyka sprawdzany na bieżącej zawartości — kasa MUSI pokazywać tę
        // samą kwotę, którą zapisze zamówienie (OrderService liczy to ponownie).
        $discountCode = $cart->discountCode($this->shopId);
        $discount = $discountCode !== null
            ? app(DiscountResolver::class)->resolve($shop, $discountCode, $lines, $this->authCustomer())
            : null;
        $discountApplies = $discount !== null && $discount->accepted();
        $itemsDiscount = $discountApplies ? $discount->itemsDiscount : 0.0;
        $freeShippingByCode = $discountApplies && $discount->freeShipping;

        // Koszt dostawy z cennika WYBRANEJ metody (każda ma własny koszt i próg
        // darmowej dostawy; odbiór: 0). Kod „darmowa wysyłka" zeruje go wprost.
        $method = $this->selectedDelivery();
        $deliveryCost = $freeShippingByCode ? 0.0 : ($method !== null ? $shop->deliveryCostFor($method, $gross) : 0.0);

        return [
            'lines' => $lines,
            'gross' => $gross,
            'discountCode' => $discountCode,
            'discountApplies' => $discountApplies,
            'itemsDiscount' => $itemsDiscount,
            'freeShippingByCode' => $freeShippingByCode,
            'deliveryCost' => $deliveryCost,
            'total' => round($gross - $itemsDiscount + $deliveryCost, 2),
        ];
    }

    /**
     * Kwota, którą klient realnie zapłaci — przy pobraniu to dokładnie ta suma,
     * którą InPost ma zainkasować.
     */
    public function payableTotal(): float
    {
        return (float) $this->pricing()['total'];
    }

    public function render()
    {
        $shop = $this->shop();
        $pricing = $this->pricing();

        $lines = $pricing['lines'];
        $gross = $pricing['gross'];
        $discountCode = $pricing['discountCode'];
        $discountApplies = $pricing['discountApplies'];
        $itemsDiscount = $pricing['itemsDiscount'];
        $freeShippingByCode = $pricing['freeShippingByCode'];

        // Netto liczone PO rabacie, rozbitym na pozycje proporcjonalnie — ten sam
        // podział co w OrderTotals, więc „netto" w kasie zgadza się z fakturą.
        $shares = DiscountAllocation::spread($itemsDiscount, $lines->pluck('line_total')->all());
        $net = $lines->values()->sum(fn (array $line, int $i): float => round(
            ($line['line_total'] - ($shares[$i] ?? 0.0)) / (1 + $line['product']->vat_rate->fraction()), 2
        ));

        $shipped = $this->shippedDelivery();
        $deliveryCost = $pricing['deliveryCost'];

        $termsPage = $shop->pages()->where('is_system', true)->first();

        // Cennik KAŻDEJ oferowanej metody wysyłki — kasa pokazuje przy opcji jej
        // koszt i próg darmowej dostawy. Liczone przez metodę, nie zaszyte pod
        // kuriera: kolejna metoda ma tu wejść bez dotykania widoku.
        $deliveryMeta = [];
        foreach (array_keys($this->deliveryOptions()) as $value) {
            $option = DeliveryMethod::from($value);

            if (! $option->isShipped()) {
                continue;
            }

            $deliveryMeta[$value] = [
                'cost' => $shop->deliveryCostFor($option, $gross),
                'free_from' => $shop->deliveryFreeFrom($option),
            ];
        }

        return view('livewire.checkout', [
            'lines' => $lines,
            'shippedDelivery' => $shipped,
            'addressDelivery' => $this->addressDelivery(),
            'parcelDelivery' => $this->parcelDelivery(),
            'deliveryCost' => $deliveryCost,
            'formattedDelivery' => $deliveryCost > 0 ? Money::pln($deliveryCost) : ($freeShippingByCode ? 'Gratis z kodem' : 'Gratis'),
            'deliveryMeta' => $deliveryMeta,
            'discountCode' => $discountApplies ? $discountCode : null,
            'formattedItems' => Money::pln($gross),
            'formattedDiscount' => $itemsDiscount > 0 ? Money::pln($itemsDiscount) : null,
            'formattedTotal' => Money::pln($pricing['total']),
            'cashOnDelivery' => $this->cashOnDelivery(),
            'codLimit' => $this->codLimit(),
            'formattedNet' => Money::pln($net),
            'bankName' => $shop->bank_name,
            'pickupAddress' => $this->pickupAddress($shop),
            'deliveryOptions' => $this->deliveryOptions(),
            'paymentOptions' => $this->paymentOptions(),
            // Token mapy paczkomatów. Na razie platformowy (`*.kramio.pl`); token
            // per-sklep z shop_integrations dojdzie osobno i weźmie pierwszeństwo.
            // Pusty = brak mapy, ale pole kodu zostaje, więc zakup się dokończy.
            'geowidgetToken' => config('services.inpost.geowidget_token'),
            'termsUrl' => $termsPage?->storefrontPath(),
            'privacyUrl' => $shop->privacyPath(),
        ]);
    }

    /**
     * Adres odbioru = adres sklepu w jednym wierszu (do pokazania przy odbiorze
     * osobistym i płatności przy odbiorze).
     */
    private function pickupAddress(Shop $shop): string
    {
        $line = trim($shop->street.' '.$shop->building_number.($shop->apartment_number ? '/'.$shop->apartment_number : ''));

        return trim($line.', '.trim($shop->postal_code.' '.$shop->city), ', ');
    }
}
