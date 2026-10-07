<?php

namespace App\Services;

use App\Enums\DeliveryMethod;
use App\Enums\MailPriority;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\EmailMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderStatusEvent;
use App\Models\Shop;
use App\Support\Money;
use App\Support\Vocative;
use Throwable;

/**
 * Kolejkuje maile zamówienia (outbox → cron): potwierdzenie dla klienta,
 * powiadomienie dla sprzedawcy i informacja o każdej zmianie statusu. Priorytet
 * Mid (potwierdzenie, nie pilna sprawa jak reset hasła, ale nie newsletter).
 * Treść budowana w blokach — każda pozycja i sekcja w osobnej linii, dla
 * czytelności. `shop_id` niesie branding per-sklep.
 */
class OrderMailer
{
    public function __construct(private PhoneService $phone) {}

    public function confirmToCustomer(Order $order): void
    {
        // `items.product` — pouczenie o zwrotach pyta każdą pozycję o wyłączenie
        // z art. 38, więc produkty dociągamy jednym zapytaniem.
        $order->loadMissing(['items.product', 'shop']);
        $shop = $order->shop;

        EmailMessage::create($this->senderIdentity($shop) + [
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'to_email' => $order->buyer_email,
            'to_name' => trim($order->buyer_name.' '.$order->buyer_surname),
            'subject' => 'Potwierdzenie zamówienia #'.$order->number.' — '.$shop->name,
            'preheader' => 'Otrzymaliśmy Twoje zamówienie. Dziękujemy!',
            'heading' => 'Dziękujemy za zamówienie!',
            'greeting' => Vocative::greeting($order->buyer_name),
            'intro_lines' => $this->blocks([
                [
                    'Otrzymaliśmy Twoje **zamówienie #'.$order->number.'** i już się nim zajmujemy.',
                    'Dziękujemy za zakupy w **'.$shop->name.'**!',
                ],
                array_merge(
                    ['**Zamówione produkty:**'],
                    $this->productLines($order),
                    $this->amountLines($order, 'Razem do zapłaty'),
                ),
                $this->paymentBlock($order, $shop),
                $this->deliveryBlock($order, $shop),
                $this->companyBlock($order),
                $this->noteBlock($order, 'Uwagi:'),
                $this->withdrawalBlock($order),
            ]),
            // Nieopłacone online → przycisk prowadzi wprost do płatności (link po
            // tokenie, działa bez logowania i nie wygasa). Inaczej — powrót do sklepu.
            'action_text' => $order->isAwaitingOnlinePayment() ? 'Zapłać za zamówienie' : 'Wróć do sklepu',
            'action_url' => $order->isAwaitingOnlinePayment() ? $order->paymentUrl() : 'https://'.$shop->host(),
            'outro_lines' => [
                $order->isAwaitingOnlinePayment()
                    ? 'Jeśli zamówienie nie zostało jeszcze opłacone, dokończ płatność przyciskiem powyżej — możesz to zrobić także później, link nie wygasa.'
                    : 'O kolejnych krokach (przygotowanie, gotowość do odbioru) poinformujemy Cię osobnym e-mailem.',
            ],
        ]);
    }

    /**
     * Mail z gotową fakturą VAT — wysyłany przez NASZ system (nie przez
     * Fakturownię), dla spójności brandu. Przycisk prowadzi wprost do publicznego
     * PDF-a w Fakturowni (link tokenowy, bez logowania). Wołany przez job po
     * zapisaniu śladu FV, więc `invoicePdfUrl()` jest już zbudowany.
     */
    public function invoiceReady(Order $order): void
    {
        $order->loadMissing(['items', 'shop']);
        $shop = $order->shop;
        $numberSuffix = filled($order->invoice_number) ? ' nr '.$order->invoice_number : '';

        EmailMessage::create($this->senderIdentity($shop) + [
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'to_email' => $order->buyer_email,
            'to_name' => trim($order->buyer_name.' '.$order->buyer_surname),
            'subject' => 'Faktura VAT'.$numberSuffix.' do zamówienia #'.$order->number.' — '.$shop->name,
            'preheader' => 'Twoja faktura VAT do zamówienia #'.$order->number.' jest gotowa.',
            'heading' => 'Twoja Faktura VAT',
            'greeting' => Vocative::greeting($order->buyer_name),
            'intro_lines' => $this->blocks([
                [
                    'Do **zamówienia #'.$order->number.'** w sklepie **'.$shop->name.'** wystawiliśmy fakturę VAT'.($numberSuffix !== '' ? ' **'.trim($numberSuffix).'**' : '').'.',
                    'Kwota: **'.Money::pln($order->total_gross).'**.',
                ],
                ['Fakturę pobierzesz przyciskiem poniżej — to bezpośredni link do pliku PDF.'],
            ]),
            'action_text' => 'Pobierz fakturę VAT',
            'action_url' => $order->invoicePdfUrl(),
            'outro_lines' => [
                'Masz pytania do faktury? Odpowiedz na tego e-maila — trafi wprost do sklepu.',
            ],
        ]);
    }

    /**
     * „Paczka w drodze" — mail wysyłany, gdy InPost potwierdzi nadanie przesyłki.
     *
     * Osobny od maila o zmianie statusu, bo mówi o czym innym: status to etykieta
     * z panelu sprzedawcy, a to jest zdarzenie, które kupującego naprawdę
     * obchodzi — z numerem do śledzenia i celem dostawy (paczkomat albo adres).
     * Dotyczy wyłącznie sklepów nadających przez InPost; bez integracji nie
     * znamy tej chwili.
     */
    public function shipmentDispatched(Order $order): void
    {
        $order->loadMissing(['items', 'shop']);
        $shop = $order->shop;

        $toLocker = $order->delivery_method?->requiresParcelLocker() === true;

        EmailMessage::create($this->senderIdentity($shop) + [
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'to_email' => $order->buyer_email,
            'to_name' => trim($order->buyer_name.' '.$order->buyer_surname),
            'subject' => 'Paczka w drodze — zamówienie #'.$order->number.' ('.$shop->name.')',
            'preheader' => 'Nadaliśmy Twoją przesyłkę. Numer do śledzenia w środku.',
            'heading' => 'Paczka w drodze',
            'greeting' => Vocative::greeting($order->buyer_name),
            'intro_lines' => $this->blocks([
                ['Nadaliśmy przesyłkę z **zamówieniem #'.$order->number.'** w sklepie **'.$shop->name.'**.'],
                array_filter([
                    filled($order->shipment_tracking_number)
                        ? 'Numer przesyłki: **'.$order->shipment_tracking_number.'**'
                        : null,
                    $this->shipmentDestinationLine($order),
                ]),
                // Paczkomat i kurier kończą się dla klienta INACZEJ: przy skrytce
                // przychodzi SMS z kodem odbioru, przy kurierze nikt żadnego kodu
                // nie dostaje. Jedno zdanie dla obu byłoby po prostu nieprawdą.
                [$toLocker
                    ? 'InPost powiadomi Cię SMS-em i mailem, gdy paczka dotrze do paczkomatu — wtedy dostaniesz kod do odbioru.'
                    : 'Kurier InPost dostarczy paczkę pod wskazany adres. Warto mieć telefon pod ręką — kurierzy dzwonią przed doręczeniem.'],
            ]),
            // Śledzenie tylko z prawdziwym numerem: przesyłki z konta testowego
            // nie istnieją w wyszukiwarce InPostu i link prowadziłby donikąd.
            'action_text' => $order->trackingUrl() && $shop->shipxEnvironment() === 'production' ? 'Śledź przesyłkę' : null,
            'action_url' => $shop->shipxEnvironment() === 'production' ? $order->trackingUrl() : null,
            'outro_lines' => [
                'Masz pytania? Odpowiedz na tego e-maila — trafi wprost do sklepu.',
            ],
        ]);
    }

    /**
     * Dokąd jedzie paczka — jednym wierszem do maili o nadaniu. Paczkomat i
     * kurier opisują cel innym językiem (skrytka vs adres), więc zamiana na
     * zdanie po polsku mieszka w jednym miejscu.
     */
    private function shipmentDestinationLine(Order $order): ?string
    {
        if ($order->delivery_method?->requiresParcelLocker() === true) {
            if (blank($order->parcel_locker_code)) {
                return null;
            }

            $locker = filled($order->parcel_locker_address)
                ? $order->parcel_locker_code.' — '.$order->parcel_locker_address
                : $order->parcel_locker_code;

            return 'Odbiór w paczkomacie: **'.$locker.'**';
        }

        $address = trim(
            trim($order->ship_street.' '.$order->ship_building_number.($order->ship_apartment_number ? '/'.$order->ship_apartment_number : ''))
            .', '.trim($order->ship_postal_code.' '.$order->ship_city),
            ', '
        );

        return $address === '' ? null : 'Adres dostawy: **'.$address.'**';
    }

    /**
     * „Dziękujemy za zakupy" — mail po ODEBRANIU paczki przez klienta.
     *
     * Wysyłany, gdy InPost potwierdzi doręczenie. To najlepszy moment na
     * pouczenie o odstąpieniu od umowy: właśnie wtedy zaczyna biec ustawowe
     * 14 dni, a formularz zwrotu dopiero teraz się otwiera. Link podajemy
     * WYŁĄCZNIE, gdy w zamówieniu jest cokolwiek objętego tym prawem.
     */
    public function shipmentDelivered(Order $order): void
    {
        $order->loadMissing(['items.product', 'shop']);
        $shop = $order->shop;
        $withdrawable = $order->hasWithdrawableItems();
        $deadline = $order->withdrawalDeadline();
        // Przy skrytce klient paczkę ODBIERA, przy kurierze zostaje mu ona
        // DOSTARCZONA — to samo zdarzenie, ale nazwane tak, jak je przeżył.
        $handedOver = $order->delivery_method?->requiresParcelLocker() === true ? 'odebrana' : 'dostarczona';

        EmailMessage::create($this->senderIdentity($shop) + [
            'priority' => MailPriority::Low,
            'shop_id' => $shop->id,
            'to_email' => $order->buyer_email,
            'to_name' => trim($order->buyer_name.' '.$order->buyer_surname),
            'subject' => 'Dziękujemy za zakupy — zamówienie #'.$order->number.' ('.$shop->name.')',
            'preheader' => 'Paczka '.$handedOver.'. Dziękujemy za zakupy w '.$shop->name.'.',
            'heading' => 'Dziękujemy za zakupy',
            'greeting' => Vocative::greeting($order->buyer_name),
            'intro_lines' => $this->blocks([
                ['Paczka z **zamówieniem #'.$order->number.'** została '.$handedOver.'. Dziękujemy za zakupy w **'.$shop->name.'** i mamy nadzieję, że wszystko jest w porządku.'],
                $withdrawable
                    ? array_filter([
                        'Gdyby jednak coś nie pasowało — masz **'.config('legal.withdrawal.days').' dni** na odstąpienie od umowy, bez podania przyczyny.',
                        $deadline !== null ? 'Termin upływa **'.$deadline->format('d.m.Y').'**.' : null,
                    ])
                    : [],
                ['Jeśli towar okazałby się wadliwy, to osobne uprawnienie (reklamacja) i nie zależy od tego terminu.'],
            ]),
            'action_text' => $withdrawable ? 'Zgłoś zwrot' : null,
            'action_url' => $withdrawable ? $order->returnUrl() : null,
            'outro_lines' => [
                'Masz pytania? Odpowiedz na tego e-maila — trafi wprost do sklepu.',
            ],
        ]);
    }

    /**
     * Mail do kupującego o KAŻDEJ zmianie statusu — bez wyjątków i bez opcji
     * wyłączenia. Także przy cofnięciu statusu: klient musi wiedzieć, bo inaczej
     * przyjedzie odebrać coś, czego nie ma. Niesie całe zamówienie, nowy status,
     * datę jego ustawienia i notatkę sprzedawcy (jeśli była).
     */
    public function statusChanged(Order $order, OrderStatusEvent $event): void
    {
        $order->loadMissing(['items', 'shop']);
        $shop = $order->shop;
        $status = $event->to_status;

        EmailMessage::create($this->senderIdentity($shop) + [
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'to_email' => $order->buyer_email,
            'to_name' => trim($order->buyer_name.' '.$order->buyer_surname),
            'subject' => 'Zamówienie #'.$order->number.': '.$status->label().' — '.$shop->name,
            'preheader' => 'Nowy status Twojego zamówienia: '.$status->label().'.',
            'heading' => $status->label(),
            'greeting' => Vocative::greeting($order->buyer_name),
            'intro_lines' => $this->blocks([
                [
                    'Status Twojego **zamówienia #'.$order->number.'** w sklepie **'.$shop->name.'** zmienił się na: **'.$status->label().'**.',
                    'Data zmiany: '.$event->created_at->format('d.m.Y, H:i').'.',
                ],
                $this->eventNoteBlock($event),
                array_merge(
                    ['**Twoje zamówienie:**'],
                    $this->productLines($order),
                    $this->amountLines($order, 'Razem'),
                ),
                $status === OrderStatus::Completed ? $this->loyaltyBlock($order, $shop) : [],
                // Pełne dane do przelewu tylko wtedy, gdy pieniądze wciąż są
                // oczekiwane — w mailu o „Zrealizowane" numer konta to szum.
                $status === OrderStatus::AwaitingPayment
                    ? $this->paymentBlock($order, $shop)
                    : ['Sposób płatności: '.$order->payment_method->label()],
                $this->deliveryBlock($order, $shop),
            ]),
            'action_text' => 'Wróć do sklepu',
            'action_url' => 'https://'.$shop->host(),
        ]);
    }

    /**
     * Mail o anulowaniu — osobny od `statusChanged`, choć anulowanie też jest
     * zmianą statusu. Powód: tamten niesie „co dalej" (adres odbioru, dane do
     * przelewu), a tu nie ma żadnego „dalej". Zapraszanie po odbiór zamówienia,
     * które właśnie anulowaliśmy, byłoby okrutne. Zostaje sucha informacja: co
     * anulowano, za ile i dlaczego — plus wskazanie, gdzie pytać, gdy to pomyłka.
     */
    public function cancelled(Order $order, OrderStatusEvent $event): void
    {
        $order->loadMissing(['items', 'shop']);
        $shop = $order->shop;

        EmailMessage::create($this->senderIdentity($shop) + [
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'to_email' => $order->buyer_email,
            'to_name' => trim($order->buyer_name.' '.$order->buyer_surname),
            'subject' => 'Zamówienie #'.$order->number.' zostało anulowane — '.$shop->name,
            'preheader' => 'Twoje zamówienie #'.$order->number.' zostało anulowane.',
            'heading' => 'Zamówienie anulowane',
            'greeting' => Vocative::greeting($order->buyer_name),
            'intro_lines' => $this->blocks([
                [
                    'Twoje **zamówienie #'.$order->number.'** w sklepie **'.$shop->name.'** zostało anulowane.',
                    'Data anulowania: '.$event->created_at->format('d.m.Y, H:i').'.',
                ],
                $this->cancelReasonBlock($event),
                array_merge(
                    ['**Anulowane zamówienie obejmowało:**'],
                    $this->productLines($order),
                    $this->amountLines($order, 'Na kwotę'),
                ),
            ]),
            'outro_lines' => [
                'Jeśli to pomyłka lub masz pytania — odpowiedz na tego e-maila, trafi wprost do sklepu.',
            ],
        ]);
    }

    /**
     * Wiadomość od sprzedawcy do kupującego, pisana z ręki w panelu. Do treści
     * dokładamy pozycje zamówienia z kwotami: wiadomość zwykle dotyczy któregoś
     * z produktów, a klient nie musi wtedy szukać po skrzynce potwierdzenia,
     * żeby wiedzieć, o czym mowa.
     *
     * Reply-To niesie adres kontaktowy sklepu (`senderIdentity`), więc zachęta
     * do odpowiadania na końcu jest obietnicą z pokryciem — odpowiedź faktycznie
     * trafi do sprzedawcy, a nie w próżnię.
     */
    public function messageToCustomer(Order $order, string $body): void
    {
        $order->loadMissing(['items', 'shop']);
        $shop = $order->shop;

        EmailMessage::create($this->senderIdentity($shop) + [
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'to_email' => $order->buyer_email,
            'to_name' => trim($order->buyer_name.' '.$order->buyer_surname),
            'subject' => 'Wiadomość w sprawie zamówienia #'.$order->number.' — '.$shop->name,
            'preheader' => 'Masz wiadomość od sklepu '.$shop->name.'.',
            'heading' => 'Wiadomość od sklepu',
            'greeting' => Vocative::greeting($order->buyer_name),
            'intro_lines' => $this->blocks($this->messageBlocks(
                $order,
                $body,
                'Twoje zamówienie #'.$order->number.':',
            )),
            'outro_lines' => [
                'Chcesz o coś dopytać? Odpowiedz na tę wiadomość przyciskiem „Odpowiedz" w swojej skrzynce — dzięki temu cała nasza rozmowa zostanie w jednym wątku.',
            ],
        ]);
    }

    /**
     * Kopia wiadomości na skrzynkę sprzedawcy — tylko na jego wyraźne życzenie
     * („Wyślij kopię do mnie"). Od pierwszej linii mówi, że to kopia: bez tego
     * sprzedawca zobaczyłby w skrzynce własny tekst i musiał zgadywać, czy to
     * przypadkiem nie odpowiedź klienta.
     */
    public function messageCopyToSeller(Order $order, string $body): void
    {
        $order->loadMissing(['items', 'shop.owner']);
        $shop = $order->shop;
        $owner = $shop->owner;

        if ($owner === null) {
            return;
        }

        $buyer = trim($order->buyer_name.' '.$order->buyer_surname);

        EmailMessage::create($this->senderIdentity($shop) + [
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'to_email' => $owner->email,
            'to_name' => trim($owner->name.' '.$owner->surname),
            'subject' => 'Kopia: wiadomość do klienta — zamówienie #'.$order->number,
            'preheader' => 'Kopia wiadomości wysłanej do '.$buyer.'.',
            'heading' => 'Kopia wysłanej wiadomości',
            'greeting' => Vocative::greeting($owner->name),
            'intro_lines' => $this->blocks(array_merge(
                [[
                    'To kopia wiadomości wysłanej do **'.$buyer.'** ('.$order->buyer_email.') w sprawie **zamówienia #'.$order->number.'** w sklepie **'.$shop->name.'**.',
                    'Odpowiedź klienta trafi na adres kontaktowy sklepu.',
                ]],
                $this->messageBlocks($order, $body, 'Zamówienie #'.$order->number.':'),
            )),
        ]);
    }

    /**
     * Wspólny trzon wiadomości od sprzedawcy: jego tekst, a pod nim pozycje
     * zamówienia z sumą. Kopia dla sprzedawcy używa tego samego trzonu co mail
     * klienta — kopia ma pokazywać to, co klient dostał, a nie streszczenie.
     *
     * @return list<list<string>>
     */
    private function messageBlocks(Order $order, string $body, string $productsLabel): array
    {
        return array_merge(
            $this->bodyBlocks($body),
            [array_merge(
                ['**'.$productsLabel.'**'],
                $this->productLines($order),
                $this->amountLines($order, 'Razem'),
            )],
        );
    }

    /**
     * Tekst z textarei na bloki maila: pusta linia rozdziela akapity, a pojedyncze
     * złamanie zostaje wewnątrz akapitu (komponent sklei je `<br>`). Dzięki temu
     * wiadomość dociera w takim kształcie, w jakim sprzedawca ją napisał.
     *
     * Treść jest escapowana dopiero przy renderowaniu (`MailMarkup::inline`), więc
     * tekst sprzedawcy nie wstrzyknie HTML-u — najwyżej pokaże dosłowne `**`.
     *
     * @return list<list<string>>
     */
    private function bodyBlocks(string $body): array
    {
        $paragraphs = preg_split('/\R\s*\R/', trim($body)) ?: [];

        return array_values(array_filter(array_map(
            fn (string $paragraph): array => array_values(array_filter(
                array_map(trim(...), preg_split('/\R/', $paragraph) ?: []),
                fn (string $line): bool => $line !== '',
            )),
            $paragraphs,
        ), fn (array $block): bool => $block !== []));
    }

    public function notifySeller(Order $order): void
    {
        $order->loadMissing(['items', 'shop.owner']);
        $shop = $order->shop;
        $owner = $shop->owner;

        if ($owner === null) {
            return;
        }

        EmailMessage::create($this->senderIdentity($shop) + [
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'to_email' => $owner->email,
            'to_name' => trim($owner->name.' '.$owner->surname),
            'subject' => 'Nowe zamówienie #'.$order->number.' w '.$shop->name,
            'preheader' => 'Masz nowe zamówienie na kwotę '.Money::pln($order->total_gross).'.',
            'heading' => 'Nowe zamówienie #'.$order->number,
            'greeting' => Vocative::greeting($owner->name),
            'intro_lines' => $this->blocks([
                ['W Twoim sklepie **'.$shop->name.'** pojawiło się nowe **zamówienie #'.$order->number.'**.'],
                array_merge(
                    ['**Dane kupującego:**'],
                    ['Imię i nazwisko: '.trim($order->buyer_name.' '.$order->buyer_surname)],
                    ['E-mail: '.$order->buyer_email],
                    $order->buyer_phone ? ['Telefon: '.$this->phone->format($order->buyer_phone)] : [],
                ),
                $this->companyBlock($order),
                array_merge(
                    ['**Zamówione produkty:**'],
                    $this->productLines($order),
                    $this->amountLines($order, 'Wartość zamówienia'),
                ),
                [
                    'Dostawa: '.$order->delivery_method->label(),
                    'Płatność: '.$order->payment_method->label(),
                ],
                $this->noteBlock($order, 'Uwagi klienta:'),
            ]),
            'outro_lines' => [
                'Zamówienie znajdziesz w panelu, w zakładce Zamówienia — tam ustawisz jego status.',
            ],
        ]);
    }

    /**
     * Dane do faktury (gdy zakup firmowy): nazwa, NIP i adres firmy — każde w
     * osobnej linii. Adres tylko, gdy podany.
     *
     * @return list<string>
     */
    private function companyBlock(Order $order): array
    {
        if (! $order->is_company) {
            return [];
        }

        $address = trim(
            trim($order->company_street.' '.$order->company_building_number.($order->company_apartment_number ? '/'.$order->company_apartment_number : ''))
            .', '.trim($order->company_postal_code.' '.$order->company_city),
            ', '
        );

        return array_values(array_filter([
            '**Dane do faktury:**',
            'Firma: '.$order->company_name,
            'NIP: '.$order->company_nip,
            $address !== '' ? 'Adres: '.$address : null,
        ]));
    }

    /**
     * Blok „Uwagi" z notatką klienta (gdy podana). Nagłówek pogrubiony, treść
     * notatki z zachowaniem akapitów. Etykieta różni się między mailem klienta a
     * sprzedawcy.
     *
     * @return list<string>
     */
    private function noteBlock(Order $order, string $label): array
    {
        return $this->multilineBlock($label, $order->note);
    }

    /**
     * Notatka sprzedawcy dopięta do konkretnej zmiany statusu (nie mylić z
     * uwagami klienta z kasy — `noteBlock`). Pomijana, gdy pusta.
     *
     * @return list<string>
     */
    private function eventNoteBlock(OrderStatusEvent $event): array
    {
        return $this->multilineBlock('Wiadomość od sklepu:', $event->note);
    }

    /**
     * Powód anulowania (notatka zdarzenia). Sprzedawca może go nie podać —
     * wtedy blok znika i mail nie udaje, że wyjaśnia.
     *
     * @return list<string>
     */
    private function cancelReasonBlock(OrderStatusEvent $event): array
    {
        return $this->multilineBlock('Powód anulowania:', $event->note);
    }

    /**
     * Blok „nagłówek + wieloliniowa treść użytkownika" (uwagi, wiadomość sklepu,
     * powód anulowania). Treść bez wpisu → blok znika. Kluczowe: rozbijamy tekst
     * na osobne linie, bo komponent skleja je przez `<br>` — inaczej znaki nowej
     * linii zjada HTML i wielolinijkowa notatka zlewa się w jedną ścianę tekstu.
     *
     * @return list<string>
     */
    private function multilineBlock(string $label, ?string $text): array
    {
        if (! filled($text)) {
            return [];
        }

        return array_merge(['**'.$label.'**'], $this->textLines((string) $text));
    }

    /**
     * Wieloliniowy tekst użytkownika → linie bloku maila. Pojedyncze przejścia do
     * nowej linii i JEDNA pusta linia między akapitami zostają (pusta linia daje
     * `<br><br>` = odstęp akapitu); nadmiarowe puste linie i te na brzegach
     * zwijamy, żeby mail nie dostał wielkich dziur.
     *
     * @return list<string>
     */
    private function textLines(string $text): array
    {
        $normalized = preg_replace(["/\r\n?/", "/\n{3,}/"], ["\n", "\n\n"], trim($text));

        return explode("\n", (string) $normalized);
    }

    /**
     * Odfiltrowuje puste bloki (np. brak danych firmy czy notatki), by nie
     * zostawić pustego akapitu w mailu. Każdy pozostały blok to tablica linii,
     * którą komponent renderuje jako jeden akapit (linie sklejone <br>).
     *
     * @param  list<list<string>>  $blocks
     * @return list<list<string>>
     */
    /**
     * Pouczenie o prawie odstąpienia od umowy (14 dni) wraz z wzorem
     * oświadczenia — obowiązek informacyjny z ustawy z 30 maja 2014 o prawach
     * konsumenta. Brak pouczenia wydłuża termin odstąpienia z 14 dni do
     * 12 miesięcy, więc ten blok jest tańszy niż jego brak.
     *
     * Nie dokładamy go do zamówień złożonych wyłącznie z towarów wyłączonych
     * (art. 38) — informowanie o prawie, które nie przysługuje, wprowadza
     * w błąd. Gdy zamówienie jest mieszane, wymieniamy wyłączone pozycje
     * z nazwy, żeby klient wiedział, czego pouczenie NIE obejmuje.
     *
     * @return list<string>
     */
    private function withdrawalBlock(Order $order): array
    {
        if (! $order->hasWithdrawableItems()) {
            return [];
        }

        $days = (int) config('legal.withdrawal.days');
        $contact = $order->shop?->contact_email;

        $lines = [
            '**Prawo odstąpienia od umowy**',
            'Możesz odstąpić od tej umowy w ciągu '.$days.' dni od otrzymania zamówienia, bez podania przyczyny.',
            // Formularz najpierw: wypełniony online od razu trafia do sklepu i
            // pomniejsza zamówienie. Droga mailowa zostaje jako równorzędna —
            // ustawa nie pozwala narzucić konsumentowi jednej formy.
            // Odnośnik, nie goły adres: token zwrotu to długi ciąg, który w
            // wielu skrzynkach nie łamie się na spacji i rozpycha całą wiadomość
            // na szerokość (poziomy przewijak nawet na telefonie).
            'Najprościej: [**wypełnij formularz zwrotu**]('.$order->returnUrl().')',
            'Możesz też przesłać oświadczenie'
                .($contact !== null ? ' na adres '.$contact.' albo w odpowiedzi na tego e-maila.' : ' w odpowiedzi na tego e-maila.'),
            'Wzór oświadczenia: „Niniejszym odstępuję od umowy sprzedaży następujących rzeczy: … '
                .'(zamówienie #'.$order->number.'). Imię i nazwisko, adres, data."',
            'Towar odeślij w ciągu 14 dni od złożenia oświadczenia. Zwrócimy Ci zapłatę wraz z kosztem '
                .'najtańszej oferowanej przez nas dostawy; koszt odesłania towaru ponosisz Ty.',
        ];

        $excluded = $order->items
            ->filter(fn (OrderItem $item) => $item->product !== null && ! $item->product->isWithdrawable())
            ->pluck('name');

        if ($excluded->isNotEmpty()) {
            $lines[] = 'Prawo odstąpienia nie obejmuje: **'.$excluded->implode(', ').'** — '
                .'to towary wyłączone ze zwrotu na podstawie art. 38 ustawy o prawach konsumenta.';
        }

        return $lines;
    }

    /**
     * Powiadomienie sprzedawcy o zgłoszonym zwrocie. Zamówienie jest już
     * pomniejszone (odstąpienie działa z mocy prawa, nie czeka na zgodę), więc
     * mail jest informacją i listą zadań, a nie prośbą o decyzję.
     */
    public function returnSubmitted(Order $order, OrderReturn $return): void
    {
        $order->loadMissing(['items', 'shop.owner']);
        $shop = $order->shop;
        $owner = $shop->owner;

        if ($owner === null) {
            return;
        }

        EmailMessage::create($this->senderIdentity($shop) + [
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'to_email' => $owner->email,
            'to_name' => trim($owner->name.' '.$owner->surname),
            'subject' => 'Zwrot z zamówienia #'.$order->number.' — '.$shop->name,
            'preheader' => 'Klient odstąpił od umowy. Do zwrotu '.Money::pln($return->refund_gross).'.',
            'heading' => 'Zgłoszono zwrot z zamówienia #'.$order->number,
            'greeting' => Vocative::greeting($owner->name),
            'intro_lines' => $this->blocks([
                [
                    'Klient odstąpił od umowy — **zamówienie #'.$order->number.'**.',
                    'Zamówienie zostało już pomniejszone o zwrócone pozycje.',
                ],
                array_merge(
                    ['**Zwracane pozycje:**'],
                    $this->returnLines($return),
                    ['Do zwrotu klientowi: **'.Money::pln($return->refund_gross).'**'],
                ),
                [
                    '**Dane osoby odstępującej:**',
                    'Imię i nazwisko: '.$return->customer_name,
                    'Adres: '.$return->customer_address,
                ],
                filled($return->bank_account) ? ['Numer konta do zwrotu: **'.$return->bank_account.'**'] : [],
                // Ustawa każe oddać także najtańszą OFEROWANĄ dostawę — ale tylko
                // przy odstąpieniu od całości. Której dostawy dotyczy „najtańsza",
                // wie sprzedawca, więc podpowiadamy kwotę, a nie liczymy za niego.
                $order->isFullyReturned() && (float) $order->delivery_cost > 0
                    ? ['Zwrot obejmuje **całe zamówienie** — oddaj również koszt dostawy (zapłacono '.Money::pln($order->delivery_cost).'; ustawa nakazuje zwrot najtańszej oferowanej przez Ciebie opcji).']
                    : [],
                filled($return->note) ? array_merge(['**Wiadomość od klienta:**'], $this->textLines($return->note)) : [],
            ]),
            'outro_lines' => [
                'Pieniądze zwróć **do '.$return->refundDeadline()->format('d.m.Y').'** (14 dni od otrzymania oświadczenia). '
                    .'Możesz wstrzymać wypłatę do chwili otrzymania towaru albo dowodu jego odesłania — to zawiesza wykonanie, nie przesuwa terminu.',
                'Stan magazynowy nie został zmieniony — o tym, czy towar wraca do sprzedaży, decydujesz sam po jego obejrzeniu.',
            ],
        ]);
    }

    /**
     * Potwierdzenie dla klienta, że oświadczenie do nas dotarło. To nie jest
     * uprzejmość, tylko OBOWIĄZEK z art. 30 ust. 2 ustawy o prawach konsumenta:
     * odstąpienie złożone drogą elektroniczną przedsiębiorca musi niezwłocznie
     * potwierdzić na trwałym nośniku.
     */
    public function returnAcknowledged(Order $order, OrderReturn $return): void
    {
        $order->loadMissing(['items', 'shop']);
        $shop = $order->shop;
        $days = (int) config('legal.withdrawal.days');

        EmailMessage::create($this->senderIdentity($shop) + [
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'to_email' => $order->buyer_email,
            'to_name' => trim($order->buyer_name.' '.$order->buyer_surname),
            'subject' => 'Potwierdzenie odstąpienia od umowy — zamówienie #'.$order->number,
            'preheader' => 'Przyjęliśmy Twoje oświadczenie o odstąpieniu od umowy.',
            'heading' => 'Przyjęliśmy Twoje oświadczenie',
            'greeting' => Vocative::greeting($order->buyer_name),
            'intro_lines' => $this->blocks([
                [
                    'Potwierdzamy otrzymanie Twojego oświadczenia o odstąpieniu od umowy — **zamówienie #'.$order->number.'** w sklepie **'.$shop->name.'**.',
                ],
                array_merge(
                    ['**Zwracasz:**'],
                    $this->returnLines($return),
                    ['Do zwrotu: **'.Money::pln($return->refund_gross).'**'],
                ),
                [
                    '**Co dalej**',
                    'Odeślij towar w ciągu '.$days.' dni od złożenia tego oświadczenia. Koszt odesłania ponosisz Ty, chyba że sklep ustalił inaczej.',
                    'Sklep zwróci pieniądze w ciągu '.$days.' dni od otrzymania oświadczenia — może wstrzymać zwrot do chwili otrzymania towaru albo dowodu jego odesłania.',
                ],
            ]),
            'action_text' => 'Zobacz swoje zwroty',
            'action_url' => $order->returnUrl(),
            'outro_lines' => [
                'Masz pytania? Odpowiedz na tego e-maila — trafi wprost do sklepu.',
            ],
        ]);
    }

    /**
     * Punkty za zrealizowane zamówienie. Porcja jest już naliczona — obserwator
     * zamówienia działa przy zatwierdzeniu zmiany statusu, a mail powstaje po
     * nim. Bez porcji (sklep bez punktów, zamówienie za 0 zł) bloku nie ma.
     *
     * Klient z aktywnym kontem dostaje odnośnik do salda. Gość — łączną liczbę
     * punktów i zaproszenie do konta: punkty są zapisane na jego adres e-mail
     * i czekają, ale wydać je można dopiero po rejestracji (decyzja Rafała).
     *
     * @return list<string>
     */
    private function loyaltyBlock(Order $order, Shop $shop): array
    {
        // Mail o statusie jest ważniejszy niż wzmianka o punktach: awaria
        // punktów trafia do raportu, a mail wychodzi bez tego bloku.
        try {
            return $this->loyaltyLines($order, $shop);
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function loyaltyLines(Order $order, Shop $shop): array
    {
        $ledger = app(LoyaltyLedger::class);
        $entry = $ledger->earnedFor($order);

        if ($entry === null || $entry->points <= 0) {
            return [];
        }

        $lines = [
            // Same punkty, bez wartości w zł (decyzja Rafała 07.10).
            '**Punkty za to zamówienie: '.$entry->points.' pkt**.',
            $entry->available_at->isFuture()
                ? 'Będą do wykorzystania od '.$entry->available_at->format('d.m.Y').' — wcześniej trwa czas na ewentualny zwrot.'
                : 'Możesz z nich skorzystać już teraz.',
        ];

        $account = $shop->customers()->whereRaw('LOWER(email) = ?', [$entry->email])->first();

        if ($account?->isActivated()) {
            $lines[] = 'Saldo i historię znajdziesz w [**Moim koncie**](https://'.$shop->host().'/moje-konto/punkty).';
        } else {
            $total = $ledger->balance($shop, $entry->email) + $ledger->pending($shop, $entry->email);
            $lines[] = 'Masz już łącznie **'.$total.' pkt**. [**Załóż konto w sklepie**](https://'.$shop->host().'/rejestracja) na ten adres e-mail, żeby z nich skorzystać — punkty na Ciebie czekają.';
        }

        return $lines;
    }

    /**
     * Pozycje zgłoszenia zwrotu jako linie: „• 2 szt. × Nazwa — 100,00 zł".
     * Nazwa i jednostka z migawki pozycji zamówienia, więc pozostają wierne.
     *
     * @return list<string>
     */
    private function returnLines(OrderReturn $return): array
    {
        $return->loadMissing('items.orderItem');

        return $return->items
            ->map(function ($line): string {
                $item = $line->orderItem;
                $quantity = $item !== null
                    ? $item->sale_unit->formatQuantity((float) $line->quantity)
                    : rtrim(rtrim((string) $line->quantity, '0'), '.');

                return '• '.$quantity.' × '.($item->name ?? 'pozycja zamówienia').' — '.Money::pln($line->refund_gross);
            })
            ->values()
            ->all();
    }

    private function blocks(array $blocks): array
    {
        return array_values(array_filter($blocks, fn (array $block): bool => $block !== []));
    }

    /**
     * Tożsamość nadawcy „od sklepu", wspólna dla maili zamówienia: display-name
     * koperty = nazwa sklepu, Reply-To = e-mail kontaktowy sklepu. From-address
     * zostaje nasz (deliverability) — renderer składa to w `OutboxMailable`.
     *
     * @return array<string, string|null>
     */
    private function senderIdentity(Shop $shop): array
    {
        return [
            'from_name' => $shop->name,
            'reply_to' => $shop->contact_email,
        ];
    }

    /**
     * Pozycje jako osobne linie: „• 2 szt. × Nazwa — 40,00 zł" (albo „2,50 kg ×…").
     *
     * @return list<string>
     */
    private function productLines(Order $order): array
    {
        // Ilość efektywna (bez tego, co klient już oddał) i bez pozycji zwróconych
        // w całości — mail o zamówieniu ma pokazywać jego bieżący stan, zgodny
        // z kwotą na dole. Historia zwrotów jest w panelu, nie tutaj.
        return $order->items
            ->filter(fn ($item): bool => $item->effectiveQuantity() > 0)
            ->map(fn ($item): string => '• '.$item->sale_unit->formatQuantity($item->effectiveQuantity()).' × '.$item->name.' — '.Money::pln($item->line_total_gross))
            ->values()
            ->all();
    }

    /**
     * Rachunek pod listą pozycji: rabat i dostawa POKAZANE, gdy istnieją, a na
     * końcu suma z podanym nagłówkiem. Bez tych linii mail nie zgadzał się
     * arytmetycznie — suma pozycji różniła się od kwoty do zapłaty i wyglądało
     * to na pomyłkę sklepu, choć doliczona była dostawa.
     *
     * @return list<string>
     */
    private function amountLines(Order $order, string $totalLabel): array
    {
        $discount = (float) $order->discount_amount;
        $delivery = (float) $order->delivery_cost;
        $lines = [];

        // Wiersz „Produkty" ma sens dopiero, gdy pod spodem coś od niego odejmujemy
        // albo do niego dodajemy — inaczej powtarzałby sumę pozycji.
        if ($discount > 0 || $delivery > 0) {
            $lines[] = 'Produkty: '.Money::pln($order->items_total);
        }

        if ($discount > 0) {
            $lines[] = 'Rabat'.(filled($order->discount_code) ? ' '.$order->discount_code : '').': −'.Money::pln($discount);
        }

        if ($delivery > 0) {
            $lines[] = 'Dostawa: '.Money::pln($delivery);
        }

        $lines[] = $totalLabel.': **'.Money::pln($order->total_gross).'**';

        return $lines;
    }

    /**
     * Blok płatności jako osobne linie (metoda + dane do przelewu / info o odbiorze).
     *
     * @return list<string>
     */
    private function paymentBlock(Order $order, mixed $shop): array
    {
        if ($order->payment_method === PaymentMethod::BankTransfer) {
            return array_values(array_filter([
                'Sposób płatności: Przelew na konto'.(filled($shop->bank_name) ? ' ('.$shop->bank_name.')' : ''),
                $shop->formattedBankAccountNumber() ? 'Numer konta: '.$shop->formattedBankAccountNumber() : null,
                'Tytuł przelewu: **Zamówienie #'.$order->number.'**',
                'Kwota: **'.Money::pln($order->total_gross).'**',
            ]));
        }

        if ($order->payment_method === PaymentMethod::Online) {
            // Online = przedpłata: dopóki nie ma potwierdzenia (webhook przenosi na
            // „Opłacone"), zamówienie czeka na wpłatę. Bez „przy odbiorze" — to był błąd.
            return ['Sposób płatności: '.$order->payment_method->label().
                ($order->status === OrderStatus::AwaitingPayment
                    ? ' — zamówienie czeka na opłacenie.'
                    : ' — opłacone.'),
            ];
        }

        if ($order->payment_method === PaymentMethod::CashOnDelivery) {
            // Kwota MUSI tu być: klient płaci ją komuś obcemu, przy skrytce albo
            // w drzwiach, i to jest jedyne miejsce, gdzie może ją wcześniej
            // sprawdzić. Sposób zapłaty różni się między paczkomatem a kurierem
            // i nie wolno go uogólnić — paczkomat gotówki nie przyjmie, a klient,
            // który się o tym dowie przy skrytce, po prostu nie odbierze paczki.
            return [
                'Sposób płatności: '.$order->payment_method->label(),
                'Do zapłaty przy odbiorze: **'.Money::pln($order->total_gross).'**',
                $order->delivery_method?->requiresParcelLocker() === true
                    ? 'W paczkomacie zapłacisz BLIK-iem, kartą lub w aplikacji InPost — gotówki paczkomat nie przyjmuje.'
                    : 'Kurierowi zapłacisz gotówką lub kartą.',
            ];
        }

        return ['Sposób płatności: '.$order->payment_method->label().' — zapłacisz na miejscu przy odbiorze.'];
    }

    /**
     * Blok dostawy jako osobne linie (metoda + adres odbioru, jeśli odbiór).
     *
     * @return list<string>
     */
    private function deliveryBlock(Order $order, mixed $shop): array
    {
        $lines = ['Sposób dostawy: '.$order->delivery_method->label()];

        if ($order->delivery_method === DeliveryMethod::Pickup) {
            $address = trim(
                trim($shop->street.' '.$shop->building_number.($shop->apartment_number ? '/'.$shop->apartment_number : ''))
                .', '.trim($shop->postal_code.' '.$shop->city),
                ', '
            );

            if ($address !== '') {
                $lines[] = 'Adres odbioru: '.$address;
            }

            return $lines;
        }

        if ($order->delivery_method->requiresShippingAddress()) {
            $address = trim(
                trim($order->ship_street.' '.$order->ship_building_number.($order->ship_apartment_number ? '/'.$order->ship_apartment_number : ''))
                .', '.trim($order->ship_postal_code.' '.$order->ship_city),
                ', '
            );

            if ($address !== '') {
                $lines[] = 'Adres dostawy: '.$address;
            }
        }

        if ($order->delivery_method->requiresParcelLocker() && filled($order->parcel_locker_code)) {
            // Kod pogrubiony: to jedyna dana, po której sprzedawca nadaje paczkę,
            // a klient odbiera. Opis punktu tylko gdy jest — przy wpisie z palca
            // (bez mapy) zamówienie niesie sam kod i nie ma czego dopowiadać.
            $lines[] = 'Paczkomat: **'.$order->parcel_locker_code.'**';

            if (filled($order->parcel_locker_address)) {
                $lines[] = $order->parcel_locker_address;
            }
        }

        if ($order->delivery_method->isShipped()) {
            $lines[] = 'Koszt dostawy: '.((float) $order->delivery_cost > 0 ? Money::pln($order->delivery_cost) : 'gratis');
        }

        return $lines;
    }
}
