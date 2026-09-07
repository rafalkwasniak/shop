<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Support\OrderFlow;
use Carbon\CarbonInterface;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Zamówienie sklepu. Wszystkie dane (kupujący, adres, ceny) to migawka z chwili
 * złożenia — nie odczytujemy ich z bieżących produktów/profilu. `number` to numer
 * per-sklep prezentowany klientom; `shop_id` nie jest mass-assignable (tworzymy
 * przez relację sklepu). Usuwanie wyłącznie logiczne (SoftDeletes).
 */
#[Fillable([
    'number', 'customer_id', 'status',
    'buyer_name', 'buyer_surname', 'buyer_email', 'buyer_phone',
    'is_company', 'company_name', 'company_nip',
    'company_street', 'company_building_number', 'company_apartment_number', 'company_postal_code', 'company_city',
    'ship_street', 'ship_building_number', 'ship_apartment_number', 'ship_postal_code', 'ship_city',
    'parcel_locker_code', 'parcel_locker_address',
    'delivery_method', 'delivery_cost', 'payment_method',
    'items_total', 'discount_code_id', 'discount_code', 'discount_amount',
    'total_net', 'total_vat', 'total_gross', 'note',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'delivery_method' => DeliveryMethod::class,
            'payment_method' => PaymentMethod::class,
            'is_company' => 'boolean',
            'delivery_cost' => 'decimal:2',
            'items_total' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_net' => 'decimal:2',
            'total_vat' => 'decimal:2',
            'total_gross' => 'decimal:2',
            'invoiced_at' => 'datetime',
            'invoice_status' => \App\Enums\InvoiceStatus::class,
            'shipment_size' => \App\Enums\ParcelSize::class,
            'shipment_sending_method' => \App\Enums\SendingMethod::class,
            'shipment_weight_kg' => 'decimal:2',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'shipment_queued_at' => 'datetime',
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
     * Konto klienta, do którego przypięto zamówienie (lub null dla gościa).
     * Przypięcie następuje po e-mailu w obrębie sklepu — przy aktywacji konta
     * lub w kasie, gdy e-mail ma już konto.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Kod rabatowy użyty przy składaniu zamówienia (lub null). Do liczenia użyć
     * kodu; to, CO klient dostał, opisuje migawka `discount_code` /
     * `discount_amount` — ona przeżyje skasowanie kodu przez sprzedawcę.
     *
     * @return BelongsTo<DiscountCode, $this>
     */
    public function discountCodeUsed(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class, 'discount_code_id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Oś czasu zmian statusu (od najstarszej). Pierwsza linia osi na widoku to
     * `created_at` samego zamówienia; tu są kolejne przejścia.
     *
     * @return HasMany<OrderStatusEvent, $this>
     */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class)->oldest('id');
    }

    /**
     * Zamówienia liczone jako ZAKUP — do wszystkich ilości i kwot (przychód,
     * liczba zamówień, liczba sztuk). Anulowane odpadają: zostają w systemie
     * wyłącznie informacyjnie, jako ślad, że tak było — bo zamówienie mogło być
     * opłacone i dopiero potem anulowane, więc nie wolno go wymazać. Ale zakupem
     * nie jest, więc nie może podbijać żadnej statystyki.
     *
     * Nie mylić z „czy pokazać na liście" — listy pokazują też anulowane. Ten
     * scope dotyczy wyłącznie liczenia.
     */
    #[Scope]
    protected function countedAsSale(Builder $query): void
    {
        $query->where('status', '!=', OrderStatus::Cancelled->value);
    }

    /**
     * Ścieżka statusów TEGO zamówienia — wynika z migawki metody płatności i
     * dostawy, więc jest stała przez całe życie zamówienia (zmiana ustawień
     * sklepu nie przestawia ścieżki już złożonym zamówieniom).
     */
    public function flow(): OrderFlow
    {
        return OrderFlow::forOrder($this);
    }

    /**
     * PRYMITYW: zmienia status i dopisuje zdarzenie do osi czasu. Zwraca to
     * zdarzenie albo null, gdy status się nie zmienia — pustym przejściem nie
     * zaśmiecamy historii. Jedyne miejsce, które modyfikuje `status`, więc oś
     * czasu zawsze jest kompletna.
     *
     * Nie sprawdza ścieżki, nie rusza magazynu i NIE WYSYŁA MAILI — z panelu
     * wołaj `OrderStatusChanger`, który dokłada te trzy rzeczy. Bezpośrednio
     * tylko tam, gdzie świadomie chcesz sam zapis (np. migracje danych).
     */
    public function changeStatus(OrderStatus $to, ?string $note = null, ?User $actor = null): ?OrderStatusEvent
    {
        if ($to === $this->status) {
            return null;
        }

        $from = $this->status;
        $this->status = $to;
        $this->save();

        $event = new OrderStatusEvent([
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
        ]);

        // Autor PRZEKAZYWANY, nie odczytywany z `auth()` w środku modelu.
        // Sięganie po zalogowanego użytkownika z modelu podpisywałoby zdarzenia
        // w miejscach, w których nikt świadomie o tym nie decyduje — a null
        // („Automatycznie") jest tu poprawną odpowiedzią dla webhooka i crona,
        // nie awarią do obejścia.
        $event->user_id = $actor?->getKey();

        $this->statusEvents()->save($event);

        return $event;
    }

    /**
     * Czy zamówienie ma już wystawioną fakturę VAT. `invoice_id` (identyfikator w
     * Fakturowni) jest zarazem gardem idempotencji — FV wystawiamy tylko raz.
     */
    public function hasInvoice(): bool
    {
        return filled($this->invoice_id);
    }

    /**
     * Publiczny link do PDF faktury w Fakturowni: `{konto}/invoice/{token}.pdf`.
     * Token nie wymaga naszej autoryzacji ani api_token — ściąga wprost. null,
     * gdy FV jeszcze nie ma tokenu albo sklep stracił konfigurację adresu.
     */
    public function invoicePdfUrl(): ?string
    {
        $accountUrl = $this->shop?->fakturowniaAccountUrl();

        if (blank($this->invoice_token) || blank($accountUrl)) {
            return null;
        }

        return rtrim($accountUrl, '/').'/invoice/'.$this->invoice_token.'.pdf';
    }

    /**
     * Czy w tej chwili trwa generowanie FV (job w kolejce/robocie). UI pokazuje
     * wtedy „FV w przygotowaniu" i blokuje przycisk, by nie zlecić drugi raz.
     */
    public function isInvoicePending(): bool
    {
        return $this->invoice_status === \App\Enums\InvoiceStatus::Pending;
    }

    /**
     * Nasz własny status „zlecone, czeka na kolejkę" — InPost takiego nie ma.
     * Wypełnia lukę między kliknięciem sprzedawcy a wykonaniem zadania w tle.
     */
    public const SHIPMENT_QUEUED = 'queued';

    /**
     * Czy przesyłka została już nadana w InPoście. `shipment_id` jest jednym
     * źródłem prawdy i gardem: nadajemy RAZ, bo każde nadanie kosztuje.
     */
    public function hasShipment(): bool
    {
        return filled($this->shipment_id);
    }

    /**
     * Czy przesyłka jest opłacona i etykieta jest gotowa do pobrania.
     * Lista statusów żyje w kliencie ShipX — tu tylko z niej korzystamy.
     */
    public function isShipmentReady(): bool
    {
        return $this->hasShipment()
            && \App\Services\Shipping\ShipxClient::isReady(['status' => $this->shipment_status]);
    }

    /**
     * Czy nadanie trwa. Obejmuje CAŁĄ drogę: nasze `queued` (zadanie czeka w
     * kolejce), a potem `created`/`offer_selected` po stronie InPostu, zanim
     * opłaci przesyłkę. UI pokazuje wtedy „Nadajemy przesyłkę…” i sam się
     * odświeża. Świadomie NIE opieramy tego na `shipment_id` — ten pojawia się
     * dopiero po nadaniu, więc pierwsza (najdłuższa) chwila zostałaby bez
     * sygnału dla sprzedawcy.
     */
    public function isShipmentPending(): bool
    {
        return filled($this->shipment_status)
            && ! $this->isShipmentReady()
            && blank($this->shipment_error);
    }

    /**
     * Adres śledzenia przesyłki dla klienta — publiczna strona InPostu.
     * null, gdy numeru jeszcze nie ma (przed opłaceniem przesyłki).
     */
    public function trackingUrl(): ?string
    {
        return filled($this->shipment_tracking_number)
            ? 'https://inpost.pl/sledzenie-przesylek?number='.$this->shipment_tracking_number
            : null;
    }

    /**
     * Czy zamówienie wciąż czeka na płatność online — jedyny stan, w którym ma sens
     * pokazać przycisk „Zapłać" (kasa, mail, „Moje konto", strona płatności).
     */
    public function isAwaitingOnlinePayment(): bool
    {
        return $this->payment_method === PaymentMethod::Online
            && $this->status === OrderStatus::AwaitingPayment;
    }

    /**
     * Czy w zamówieniu jest COKOLWIEK objętego prawem odstąpienia (14 dni).
     * Wyjątki z art. 38 sprzedawca ustawia per produkt, a w jednym koszyku
     * bywa i stroik z żywych kwiatów (wyłączony), i doniczki (objęte) — więc
     * pouczenie należy się zamówieniu, gdy choć jedna pozycja mu podlega.
     *
     * Pozycja bez produktu (skasowany z katalogu) liczy się jako OBJĘTA:
     * przy niepewności rozstrzygamy na korzyść konsumenta.
     */
    public function hasWithdrawableItems(): bool
    {
        return $this->items->contains(fn (OrderItem $item) => $item->isWithdrawable());
    }

    /**
     * Zgłoszenia zwrotu (od najnowszego). Jedno zamówienie może mieć ich wiele —
     * klient wolno oddawać partiami, byle w sumie nie więcej, niż kupił.
     *
     * @return HasMany<OrderReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class)->latest('id');
    }

    /**
     * Czy z tego zamówienia cokolwiek już wróciło.
     */
    public function hasReturns(): bool
    {
        return $this->items->contains(fn (OrderItem $item) => $item->hasReturns());
    }

    /**
     * Czy klient może TERAZ zgłosić zwrot z tego zamówienia: nie jest anulowane,
     * termin na odstąpienie jeszcze biegnie i została choć jedna sztuka objęta
     * prawem odstąpienia. Jedna bramka dla strony zwrotu, przycisku w „Moim
     * koncie" i linku w mailu — żeby nigdzie nie zaprosić klienta do formularza,
     * który i tak go odprawi.
     */
    public function acceptsReturns(): bool
    {
        return ! $this->status->isTerminal()
            && $this->hasBeenHandedOver()
            && $this->withinWithdrawalWindow()
            && $this->items->contains(fn (OrderItem $item) => $item->returnableQuantity() > 0);
    }

    /**
     * Czy towar trafił już do klienta — potwierdzonym odbiorem paczki albo
     * oznaczeniem zamówienia jako zrealizowane.
     *
     * Bramkuje FORMULARZ zwrotu, nie samo prawo. Prawo do odstąpienia istnieje
     * od zawarcia umowy, ale zwrot rzeczy, której klient jeszcze nie dostał,
     * nie ma sensu jako procedura: formularz pomniejsza zamówienie i pyta o
     * odesłanie towaru. Rezygnacja przed wysyłką to rozmowa ze sprzedawcą
     * (anulowanie), a nie oświadczenie o odstąpieniu.
     */
    public function hasBeenHandedOver(): bool
    {
        return $this->delivered_at !== null
            || $this->statusEvents->contains(fn (OrderStatusEvent $event) => $event->to_status === OrderStatus::Completed);
    }

    /**
     * Pełny adres publicznego formularza zwrotu na hoście sklepu — do maila i
     * „Mojego konta". Budujemy z `host()`, więc działa też z joba/CLI.
     */
    public function returnUrl(): string
    {
        return 'https://'.$this->shop->host().'/zwrot/'.$this->paymentToken();
    }

    /**
     * Czy wróciły WSZYSTKIE pozycje — moment, w którym sprzedawcy należy
     * przypomnieć, że oddaje również koszt dostawy (ustawa: najtańszą oferowaną).
     * Pieniądze w v1 idą z ręki, więc to podpowiedź, a nie automat.
     */
    public function isFullyReturned(): bool
    {
        return $this->items->isNotEmpty()
            && $this->items->every(fn (OrderItem $item) => $item->effectiveQuantity() <= 0);
    }

    /**
     * Ostatni dzień na odstąpienie od umowy. Ustawa liczy 14 dni od DORĘCZENIA.
     *
     * Gdy ZNAMY datę odbioru (InPost potwierdził, że klient wyjął paczkę ze
     * skrytki), liczymy dokładnie od niej — bez zapasu, bo nie ma czego
     * kompensować. To jedyny wariant zgodny z ustawą co do dnia.
     *
     * Bez tej daty (kurier, odbiór osobisty, sklep bez integracji) zostaje
     * dotychczasowy szacunek: od przejścia w „Zrealizowane" (a bez takiego
     * zdarzenia od złożenia zamówienia) plus zapas na dostawę. Zapas daje
     * konsumentowi więcej czasu, nie mniej — w tę stronę wolno.
     */
    public function withdrawalDeadline(): ?CarbonInterface
    {
        $days = (int) config('legal.withdrawal.days');

        if ($this->delivered_at !== null) {
            return $this->delivered_at->copy()->addDays($days)->endOfDay();
        }

        $completedAt = $this->statusEvents
            ->firstWhere('to_status', OrderStatus::Completed)?->created_at;

        // Zamówienie jeszcze niezrealizowane = towar nie dotarł do klienta, więc
        // termin NIE ZACZĄŁ BIEC i nie ma czego liczyć.
        //
        // Wcześniej liczyliśmy w tym miejscu od DATY ZŁOŻENIA zamówienia i było
        // to groźnie błędne: przy rzeczy robionej ręcznie przez trzy tygodnie
        // termin „mijał", zanim klient dostał paczkę — a ta sama metoda bramkuje
        // formularz zwrotu, więc zamykała prawo, które dopiero się zaczynało.
        if ($completedAt === null) {
            return null;
        }

        return $completedAt
            ->copy()
            ->addDays($days + (int) config('legal.withdrawal.delivery_buffer_days'))
            ->endOfDay();
    }

    /**
     * Czy termin na odstąpienie jeszcze biegnie. Brak terminu (zamówienie w
     * realizacji) znaczy „jeszcze się nie zaczął", czyli TAK — klient ma prawo
     * do odstąpienia tym bardziej.
     */
    public function withinWithdrawalWindow(): bool
    {
        $deadline = $this->withdrawalDeadline();

        return $deadline === null || $deadline->isFuture();
    }

    /**
     * Token do publicznych stron zamówienia — płatności ORAZ zwrotu. Świadomie
     * jeden klucz na zamówienie, nie osobny na każdą akcję: klient dostaje oba
     * linki tym samym mailem, a drugi mechanizm tokenów byłby drugim miejscem
     * do popsucia bez żadnego zysku (dostęp daje i tak sam token).
     *
     * Zaszyfrowany identyfikator zamówienia
     * (APP_KEY + MAC): nie do zgadnięcia i odporny na podmianę, więc link działa bez
     * logowania — dla kupującego z konta i gościa tak samo. Zero kolumn w bazie.
     * `strtr` na znaki bezpieczne w ścieżce URL (bez `+ / =`).
     */
    public function paymentToken(): string
    {
        return strtr(Crypt::encryptString((string) $this->getKey()), '+/=', '-_~');
    }

    /**
     * Pełny adres strony płatności na hoście sklepu — do maila, „Moje konto" i
     * powrotu z Paynow. Budujemy z `host()`, więc działa też z joba/CLI (bez requestu).
     */
    public function paymentUrl(): string
    {
        return 'https://'.$this->shop->host().'/platnosc/'.$this->paymentToken();
    }

    /**
     * Odwrotność `paymentToken()`: identyfikator zamówienia albo null, gdy token
     * jest uszkodzony/podrobiony. Wołający i tak scope'uje zamówienie do sklepu.
     */
    public static function decodePaymentToken(string $token): ?int
    {
        try {
            return (int) Crypt::decryptString(strtr($token, '-_~', '+/='));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Czy ostatnia próba wystawienia FV się nie powiodła. FV nie ma (`invoice_id`
     * pusty), więc sprzedawca może ponowić — `canBeInvoiced()` znów przepuszcza.
     */
    public function invoiceFailed(): bool
    {
        return $this->invoice_status === \App\Enums\InvoiceStatus::Failed;
    }

    /**
     * Oznacza zamówienie jako „FV w przygotowaniu" — ustawiane synchronicznie
     * przy zleceniu (zanim job ruszy), żeby UI od razu pokazało stan i nie dało
     * zlecić drugi raz. Job wyczyści to pole (sukces) albo zmieni na `failed`.
     */
    public function markInvoicePending(): void
    {
        $this->forceFill(['invoice_status' => \App\Enums\InvoiceStatus::Pending])->save();
    }

    /**
     * Zleca wystawienie faktury: oznacza „w przygotowaniu" i wrzuca job do
     * kolejki (robota w tle). Wspólny punkt dla obu wejść w UI (przycisk przy
     * danych kupującego i „Spróbuj ponownie" w karcie stanu), żeby zlecenie
     * wyglądało identycznie niezależnie skąd padło. Guard `canBeInvoiced()`
     * chroni przed dublem i zleceniem bez konfiguracji. Zwraca, czy zlecono.
     */
    public function requestInvoice(): bool
    {
        if (! $this->canBeInvoiced()) {
            return false;
        }

        $this->markInvoicePending();
        \App\Jobs\GenerateInvoice::dispatch($this);
        $this->refresh();

        return true;
    }

    /**
     * Zleca nadanie przesyłki w InPoście (pierwsza próba lub ponowienie po
     * błędzie). Bliźniak `requestInvoice()`. Zwraca false, gdy nie wolno —
     * widok i tak nie pokaże wtedy przycisku, ale nie ufamy widokowi.
     */
    public function requestShipment(\App\Services\Shipping\ParcelSpec $parcel, \App\Enums\SendingMethod $sending): bool
    {
        if (! $this->canBeShipped()) {
            return false;
        }

        // Ponowienie po błędzie zaczyna od CZYSTEJ KARTY: kasujemy ślad
        // nieudanej przesyłki, bo job ma guard `hasShipment()` i bez tego nie
        // zrobiłby nic. To bezpieczne — do błędu dochodzi wtedy, gdy przesyłka
        // NIE została opłacona (np. brak środków), więc nic nie przepada, a
        // oferta InPostu i tak wygasa po kilku minutach i trzeba nowej.
        $this->forceFill([
            'shipment_error' => null,
            'shipment_id' => null,
            // Własny status PRZED zleceniem zadania — inaczej między kliknięciem
            // a wykonaniem zadania (kolejkę drenuje cron, więc do minuty) panel
            // nie miałby po czym poznać, że coś się dzieje, i pokazywałby z
            // powrotem przycisk „Nadaj przesyłkę". Ten sam chwyt co przy FV
            // (`markInvoicePending`).
            'shipment_status' => self::SHIPMENT_QUEUED,
            'shipment_tracking_number' => null,
            // Własny znacznik, nie `updated_at`: ten podbija każda inna zmiana
            // zamówienia i przesuwałby wykrywanie zadań, które utknęły.
            'shipment_queued_at' => now(),
        ])->save();

        \App\Jobs\CreateInpostShipment::dispatch($this, $parcel, $sending);
        $this->refresh();

        return true;
    }

    /**
     * Zlecenie odbioru, którym kurier ma zabrać tę paczkę. Null, gdy paczkę
     * sprzedawca zanosi sam albo gdy kuriera jeszcze nie zamówił.
     *
     * @return BelongsTo<\App\Models\DispatchOrder, $this>
     */
    public function dispatchOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Models\DispatchOrder::class);
    }

    /**
     * Krótki opis nadanej paczki dla panelu i potwierdzeń — „Gabaryt A" albo
     * „30 × 20 × 10 cm, 2,5 kg". Dwie metody dostawy opisują paczkę innym
     * językiem (szablon skrytki vs realne wymiary), więc jedno miejsce zamienia
     * to na zdanie po polsku. null, gdy paczki jeszcze nie opisano.
     */
    public function shipmentParcelLabel(): ?string
    {
        if ($this->shipment_size !== null) {
            return 'Gabaryt '.$this->shipment_size->symbol();
        }

        if (blank($this->shipment_length_cm)) {
            return null;
        }

        $weight = rtrim(rtrim(number_format((float) $this->shipment_weight_kg, 2, ',', ''), '0'), ',');

        return $this->shipment_length_cm.' × '.$this->shipment_width_cm.' × '.$this->shipment_height_cm.' cm, '.$weight.' kg';
    }

    /**
     * Czy wiadomo, DOKĄD nadać przesyłkę. Paczkomat potrzebuje kodu skrytki,
     * kurier — adresu klienta. Jedno źródło prawdy dla bramki w panelu i dla
     * klienta ShipX: gdyby się rozjechały, przycisk obiecywałby nadanie, które
     * API i tak odrzuci.
     */
    public function hasShipmentDestination(): bool
    {
        if ($this->delivery_method?->isShipped() !== true) {
            return false;
        }

        if ($this->delivery_method->requiresParcelLocker()) {
            return filled($this->parcel_locker_code);
        }

        return filled($this->ship_street)
            && filled($this->ship_building_number)
            && filled($this->ship_postal_code)
            && filled($this->ship_city);
    }

    /**
     * Czy z tego zamówienia można teraz nadać przesyłkę InPost. Wymaga wysyłki
     * ze znanym celem (paczkomat z kodem albo kurier z adresem), włączonej
     * integracji i braku wcześniejszego nadania — poza sytuacją, gdy poprzednia
     * próba zapisała błąd, bo wtedy ponowienie jest właśnie tym, czego trzeba.
     */
    public function canBeShipped(): bool
    {
        if (! $this->hasShipmentDestination()) {
            return false;
        }

        // Nadana albo właśnie nadawana — nie ma czego zlecać drugi raz.
        if (($this->hasShipment() || $this->isShipmentPending()) && blank($this->shipment_error)) {
            return false;
        }

        return $this->shop?->shipxEnabled() === true;
    }

    /**
     * Czy z tego zamówienia można teraz wystawić fakturę VAT. Pełna bramka:
     * pakiet daje uprawnienie, Fakturownia jest włączona i skonfigurowana, FV
     * jeszcze nie było (idempotencja) i nie trwa już generowanie. Świadomie BEZ
     * progu statusu — sprzedawca decyduje sam (ustalone: „od dowolnego statusu").
     */
    public function canBeInvoiced(): bool
    {
        return ! $this->hasInvoice()
            && ! $this->isInvoicePending()
            && $this->shop?->entitlement('invoices') === true
            && $this->shop->invoicingEnabled();
    }
}
