<?php

use App\Enums\LegalDocumentType;
use App\Http\Controllers\Administrator\AnalyticsController as AdministratorAnalyticsController;
use App\Http\Controllers\Administrator\ContentReportController as AdministratorContentReportController;
use App\Http\Controllers\Administrator\DashboardController as AdministratorDashboard;
use App\Http\Controllers\Administrator\MailingController as AdministratorMailingController;
use App\Http\Controllers\Administrator\MailPreviewController;
use App\Http\Controllers\Administrator\OrderController as AdministratorOrderController;
use App\Http\Controllers\Administrator\PackageController as AdministratorPackageController;
use App\Http\Controllers\Administrator\SellerController as AdministratorSellerController;
use App\Http\Controllers\Administrator\SettingsController as AdministratorSettingsController;
use App\Http\Controllers\Administrator\ShopController as AdministratorShopController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResendActivationController;
use App\Http\Controllers\Consent\ConsentController;
use App\Http\Controllers\ContentReportController;
use App\Http\Controllers\CookieConsentController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\PackagePaymentWebhookController;
use App\Http\Controllers\PaynowWebhookController;
use App\Http\Controllers\PlatformUnsubscribeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\Seller\AnalyticsController;
use App\Http\Controllers\Seller\AppearanceController;
use App\Http\Controllers\Seller\BulkMailingController;
use App\Http\Controllers\Seller\CompanyLookupController;
use App\Http\Controllers\Seller\CustomerController;
use App\Http\Controllers\Seller\DashboardController as SellerDashboard;
use App\Http\Controllers\Seller\DiscountCodeController;
use App\Http\Controllers\Seller\EmployeeController;
use App\Http\Controllers\Seller\IntegrationController;
use App\Http\Controllers\Seller\OrderController;
use App\Http\Controllers\Seller\PackageController;
use App\Http\Controllers\Seller\PageController;
use App\Http\Controllers\Seller\ProductController;
use App\Http\Controllers\Seller\ProductImageController;
use App\Http\Controllers\Seller\ShipmentPickupController;
use App\Http\Controllers\Seller\ShopDeletionController;
use App\Http\Controllers\Seller\ShopProfileController;
use App\Http\Controllers\Seller\ShopSettingsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Storefront\AccountController as StorefrontAccount;
use App\Http\Controllers\Storefront\ActivationController as StorefrontActivation;
use App\Http\Controllers\Storefront\AuthController as StorefrontAuth;
use App\Http\Controllers\Storefront\CartController as StorefrontCart;
use App\Http\Controllers\Storefront\CheckoutController as StorefrontCheckout;
use App\Http\Controllers\Storefront\HomeController as StorefrontHome;
use App\Http\Controllers\Storefront\OrderReturnController as StorefrontOrderReturn;
use App\Http\Controllers\Storefront\PageController as StorefrontPage;
use App\Http\Controllers\Storefront\PasswordResetController as StorefrontPasswordReset;
use App\Http\Controllers\Storefront\PaymentController as StorefrontPayment;
use App\Http\Controllers\Storefront\ProductController as StorefrontProduct;
use App\Http\Controllers\Storefront\RegisterController as StorefrontRegister;
use App\Http\Controllers\Storefront\UnsubscribeController as StorefrontUnsubscribe;
use App\Support\Mode;
use Illuminate\Support\Facades\Route;

/*
|==============================================================================
| CENTRALA — domena platformy (config('tenancy.central_domain'))
|==============================================================================
| Zarządzanie: strona główna platformy, logowanie/rejestracja, panel
| administratora i sprzedawcy. Sprzedawca zarządza sklepem TUTAJ, nie na
| swojej subdomenie.
|
| Architektura wielonajemcza: storefronty sprzedawców są serwowane z subdomen
| {shop}.{central_domain} (np. ilikemybike.kramio.pl) — sekcja STOREFRONT na
| dole pliku, DZIAŁAJĄCA (wildcard DNS + SSL na serwerze, Route::domain +
| middleware ResolveShop). Trasy w tej sekcji nie są wiązane z domeną, więc
| centrala odpowiada na każdym hoście, który nie jest subdomeną sklepu.
*/

// Strona główna PLATFORMY (landing z cennikiem). W trybie dedykowanym nie jest
// rejestrowana w ogóle, a nie tylko zamykana middlewarem — adres `/` musi tam
// trafić do strony głównej SKLEPU, a Laravel wybiera pierwszą pasującą trasę.
// Zamknięta 404 przechwyciłaby `/` i sklep nie miałby jak się pokazać.
// Trasa nie ma nazwy, więc nic jej nie woła przez `route()`.
if (Mode::saas()) {
    Route::get('/', function () {
        return view('welcome');
    });
}

// Zapis decyzji o ciasteczkach. Zwykły formularz, nie żądanie w tle: storefront
// celowo nie ładuje JavaScriptu, a po zgodzie i tak trzeba przeładować stronę,
// bo skrypt pomiaru dokłada SERWER. Trasa jest poza grupą domeny sklepu, więc
// odpowiada i na centrali, i na subdomenach — ciasteczko przypina się do hosta,
// z którego przyszło żądanie.
Route::post('/zgoda-cookies', [CookieConsentController::class, 'store'])->name('cookies.store');

/*
|--------------------------------------------------------------------------
| Uwierzytelnianie (wspólne dla admina i sprzedawcy)
|--------------------------------------------------------------------------
| Jedno wejście logowania; po zalogowaniu przekierowanie zależne od roli
| (UserRole::homeRoute()).
*/
// PREFIKS LOGOWANIA DO PANELU.
//
// W Kramio centrala i sklepy siedzą na osobnych hostach, więc `/logowanie`
// znaczy co innego na każdym z nich i nic się nie zderza. W sklepie dedykowanym
// jest jeden host, a adres `/logowanie` należy się KLIENTOWI SKLEPU — to jego
// sklep i jego konto. Właściciel przenosi się pod `/sprzedawca/logowanie`,
// czyli tam, gdzie i tak mieszka cały jego panel.
//
// NAZWY TRAS (`login`, `logout`) zostają bez zmian — woła je siedem widoków
// oraz mechanizm przekierowania gościa w Laravelu. Gdyby zniknęły, aplikacja
// wywracałaby się przy każdej próbie wejścia na chroniony adres.
$panelPrefix = Mode::dedicated() ? '/sprzedawca' : '';

Route::get($panelPrefix.'/logowanie', [AuthController::class, 'create'])->name('login');
Route::post($panelPrefix.'/logowanie', [AuthController::class, 'store'])->name('login.attempt');
Route::post($panelPrefix.'/wyloguj', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

// Odzyskiwanie hasła. Prośba o link jest dławiona per IP: ten formularz wysyła
// maila na podany adres, więc bez limitu byłby drugą — obok rejestracji —
// maszynką do zalewania cudzej skrzynki.
Route::get('/odzyskiwanie-hasla', [PasswordResetController::class, 'create'])->name('password.request');
Route::post('/odzyskiwanie-hasla', [PasswordResetController::class, 'store'])
    ->middleware('throttle:password_reset')
    ->name('password.email');
Route::get('/nowe-haslo/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
Route::post('/nowe-haslo', [PasswordResetController::class, 'update'])
    ->middleware('throttle:password_reset')
    ->name('password.update');

// Rejestracja sprzedawcy (nowe konta otrzymują rolę 'seller'). Konto powstaje
// bez hasła — sprzedawca dostaje mailem link do jego ustawienia.
//
// W sklepie dedykowanym rejestracji sprzedawcy NIE MA i tych tras nie
// rejestrujemy w ogóle — nie wystarczy zamknąć ich middlewarem. Adres
// `/rejestracja` należy się tam KLIENTOWI sklepu, a Laravel wiąże trasę z parą
// metoda+adres: definicja centrali stoi wyżej w pliku, więc nadpisałaby
// storefront i rejestracja klienta przestałaby istnieć. Cicho, bo strona
// wyglądałaby normalnie — tylko formularz prowadziłby donikąd.
//
// Nazwa `register` znika razem z trasami, dlatego odnośnik „Załóż za darmo" na
// ekranie logowania jest w tym trybie schowany (resources/views/auth/login.blade.php).
// AKTYWACJA (niżej) zostaje — to nią właściciel ustawia pierwsze hasło.
if (Mode::saas()) {
    Route::get('/rejestracja', [RegisterController::class, 'create'])
        ->middleware('registration.open')
        ->name('register');
    // Limit per IP: ten formularz wysyła maila aktywacyjnego na DOWOLNY podany
    // adres, więc bez dławika jest gotowym narzędziem do zalewania cudzej skrzynki
    // naszym kosztem — reputacyjnym. Progi: config/security.php.
    Route::post('/rejestracja', [RegisterController::class, 'store'])
        ->middleware(['throttle:register', 'registration.open'])
        ->name('register.store');
    Route::view('/rejestracja/potwierdzenie', 'auth.registered')->name('register.confirmation');
    Route::post('/rejestracja/wyslij-ponownie', [ResendActivationController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('register.resend');
}

// Aktywacja konta (ustawienie pierwszego hasła + danych) — token brokera 'activation', 24 h.
Route::get('/aktywacja/{token}', [ActivationController::class, 'create'])->name('activation.show');
Route::post('/aktywacja', [ActivationController::class, 'store'])
    ->middleware('throttle:activation')
    ->name('activation.store');

// Webhook Paynow: powiadomienie o zmianie statusu płatności (źródło prawdy o
// zapłacie). Publiczny — Paynow nie ma sesji ani CSRF; broni go podpis (patrz
// kontroler). Na domenie centrali; adres wpisujemy w panelu Paynow sprzedawcy.
Route::post('/platnosci/paynow/webhook', PaynowWebhookController::class)->name('payments.paynow.webhook');

// Webhooki opłat za PAKIETY Kramio (konto platformy) — osobna trasa, bo podpis
// weryfikuje klucz platformy z `.env`, nie klucz sklepu. W sklepie dedykowanym
// nie ma pakietów ani konta platformy, więc trasa jest zamknięta.
// UWAGA: webhook Paynow SKLEPU (wyżej) zostaje — to nim płacą kupujący.
Route::post('/platnosci/paynow/pakiety/webhook', PackagePaymentWebhookController::class)
    ->middleware('saas')
    ->name('payments.paynow.packages.webhook');

/*
|--------------------------------------------------------------------------
| Dokumenty prawne (publiczne, bez logowania)
|--------------------------------------------------------------------------
| URL po polsku, nazwa trasy po angielsku. Typ wstrzykiwany przez domyślny
| parametr trasy (implicit enum binding).
*/
Route::get('/'.LegalDocumentType::Terms->slug(), [LegalController::class, 'show'])
    ->defaults('type', LegalDocumentType::Terms->value)
    ->name(LegalDocumentType::Terms->routeName());
Route::get('/'.LegalDocumentType::Privacy->slug(), [LegalController::class, 'show'])
    ->defaults('type', LegalDocumentType::Privacy->value)
    ->name(LegalDocumentType::Privacy->routeName());

/*
|--------------------------------------------------------------------------
| Wypis z wiadomości handlowych Kramio (publiczny, bez logowania)
|--------------------------------------------------------------------------
| Link ze stopki wiadomości platformy do sprzedawców. Podpisany i BEZTERMINOWY
| — mail sprzed roku musi dać się odsubskrybować tak samo jak dzisiejszy.
| Odpowiednik `storefront.unsubscribe`, tylko na centrali i dla konta `User`.
*/
Route::get('/wypisz-sie/{user}', [PlatformUnsubscribeController::class, 'show'])
    ->middleware('signed')->name('platform.unsubscribe');
Route::post('/wypisz-sie/{user}/przywroc', [PlatformUnsubscribeController::class, 'restore'])
    ->middleware('signed')->name('platform.unsubscribe.restore');

/*
|--------------------------------------------------------------------------
| Zgłaszanie treści bezprawnych (publiczne, bez logowania) — art. 16 DSA
|--------------------------------------------------------------------------
| Mechanizm musi być „łatwo dostępny", więc żadnego logowania i link w stopce
| każdego storefrontu. Formularz stoi na CENTRALI, bo obowiązek jest nasz, nie
| sprzedawcy — link ze storefrontu buduj przez `Central::url()`, inaczej trafi
| w subdomenę sklepu, którego zgłoszenie dotyczy.
|
| `throttle` bo formularz jest otwarty dla świata; limit dobrany tak, żeby nie
| przeszkadzał człowiekowi zgłaszającemu kilka adresów pod rząd.
*/
Route::get('/zglos-tresc', [ContentReportController::class, 'create'])->name('reports.create');
Route::post('/zglos-tresc', [ContentReportController::class, 'store'])
    ->middleware('throttle:10,60')->name('reports.store');

/*
|--------------------------------------------------------------------------
| Panel administratora (rola: admin)
|--------------------------------------------------------------------------
*/
// `saas`: konsola administratora to narzędzie OPERATORA PLATFORMY — sklepy
// innych sprzedawców, ich konta, przychód z pakietów, zgłoszenia DSA. Przy
// jednym kliencie nie ma czym zarządzać, a właściciel ma dostać wyłącznie panel
// swojego sklepu. Rola `admin` zostaje w kodzie na wejście serwisowe, tylko bez
// ekranów.
// `saas` PRZED `auth` i `role`: kolejność decyduje o tym, co zobaczy odwiedzający.
// Po `role:admin` zalogowany sprzedawca dostałby 403 — „to tu jest, ale nie dla
// ciebie" — czyli potwierdzenie, że pod spodem siedzi platforma. Tak dostaje 404,
// niezależnie od tego, czy i jako kto jest zalogowany.
Route::middleware(['saas', 'auth', 'role:admin'])
    ->prefix('administrator')
    ->name('administrator.')
    ->group(function () {
        Route::get('/panel', AdministratorDashboard::class)->name('dashboard');

        // Sklepy — lista + zarządzanie pakietem/uprawnieniami/ceną per sklep.
        Route::get('/sklepy', [AdministratorShopController::class, 'index'])->name('shops.index');
        Route::get('/sklepy/{shop}', [AdministratorShopController::class, 'edit'])->name('shops.edit');

        // Usunięcie sklepu razem z kontem właściciela — natychmiast, bez karencji
        // (ta chroni sprzedawcę przed własnym kliknięciem, nie platformę).
        // `przywroc` zatrzymuje usunięcie zlecone przez sprzedawcę.
        Route::post('/sklepy/{shop}/usun', [AdministratorShopController::class, 'destroy'])->name('shops.destroy');
        Route::post('/sklepy/{shop}/przywroc', [AdministratorShopController::class, 'restore'])->name('shops.restore');

        // Wiadomość serwisowa do właściciela sklepu — POZA zgodą marketingową
        // (awaria, sprawa konta). Handlowe treści idą działem „Wiadomości".
        Route::post('/sklepy/{shop}/wiadomosc', [AdministratorShopController::class, 'message'])->name('shops.message');

        // Pakiety — przekrój pieniędzy platformy: przychód z opłat, wartość
        // biegnących abonamentów, rozkład sklepów po pakietach. Zmiana pakietu
        // pojedynczego sklepu zostaje w dziale „Sklepy".
        // Zamówienia całej platformy — TYLKO DO ODCZYTU. Sterowanie zamówieniem
        // zostaje u sprzedawcy; tu jest wyszukiwarka do wsparcia i przekrój.
        // Ustawienia platformy: STAN (diagnostyka, tylko odczyt) + PRZEŁĄCZNIKI
        // operacyjne. Cennik, progi i dane firmy zostają w config/.
        Route::get('/ustawienia', [AdministratorSettingsController::class, 'index'])->name('settings.index');
        Route::post('/ustawienia', [AdministratorSettingsController::class, 'update'])->name('settings.update');

        // Analityka platformy — ten sam ekran co u sprzedawcy, domyślnie sumarycznie
        // dla wszystkich sklepów; filtr `sklep` zawęża do jednego.
        Route::get('/analityka', [AdministratorAnalyticsController::class, 'index'])->name('analytics.index');

        Route::get('/zamowienia', [AdministratorOrderController::class, 'index'])->name('orders.index');
        Route::get('/zamowienia/{order}', [AdministratorOrderController::class, 'show'])->name('orders.show');

        Route::get('/pakiety', [AdministratorPackageController::class, 'index'])->name('packages.index');

        // Rejestr opłat + rejestracja wpłaty przyjętej poza bramką (przelew,
        // gotówka). Zapis prowadzi komponent Livewire, stąd sam GET.
        Route::get('/pakiety/oplaty', [AdministratorPackageController::class, 'payments'])->name('packages.payments');
        Route::get('/pakiety/oplaty/nowa', [AdministratorPackageController::class, 'recordPayment'])->name('packages.payments.create');

        // Sprzedawcy — konta z rolą `seller`: lista z filtrami i karta konta
        // (dane, sklep, stan aktywacji, zgody). Podgląd plus jedna akcja
        // pomocowa: ponowne wysłanie linku aktywacyjnego.
        Route::get('/sprzedawcy', [AdministratorSellerController::class, 'index'])->name('sellers.index');
        Route::get('/sprzedawcy/{user}', [AdministratorSellerController::class, 'show'])->name('sellers.show');
        Route::post('/sprzedawcy/{user}/aktywacja', [AdministratorSellerController::class, 'resendActivation'])->name('sellers.activation');

        // Wiadomości do sprzedawców — treści handlowe do kont ze zgodą.
        // Kontroler prowadzi wyłącznie szkic; wybór odbiorców i wysyłkę
        // obsługują komponenty Livewire na ekranie edycji.
        Route::get('/wiadomosci', [AdministratorMailingController::class, 'index'])->name('mailings.index');
        Route::get('/wiadomosci/nowa', [AdministratorMailingController::class, 'create'])->name('mailings.create');
        Route::post('/wiadomosci', [AdministratorMailingController::class, 'store'])->name('mailings.store');
        Route::get('/wiadomosci/{mailing}', [AdministratorMailingController::class, 'edit'])->name('mailings.edit');
        Route::post('/wiadomosci/{mailing}', [AdministratorMailingController::class, 'update'])->name('mailings.update');
        Route::post('/wiadomosci/{mailing}/usun', [AdministratorMailingController::class, 'destroy'])->name('mailings.destroy');

        // Zgłoszenia treści bezprawnych (art. 16 DSA). Ekran kończy się na
        // decyzji z uzasadnieniem — akcje na sklepie zostają w dziale „Sklepy”.
        Route::get('/zgloszenia', [AdministratorContentReportController::class, 'index'])->name('reports.index');
        Route::get('/zgloszenia/{report}', [AdministratorContentReportController::class, 'show'])->name('reports.show');
        Route::post('/zgloszenia/{report}/rozstrzygnij', [AdministratorContentReportController::class, 'decide'])->name('reports.decide');

        // Podgląd szablonów maili (na froncie, dla nas) — np. /administrator/podglad-maila/aktywacja
        Route::get('/podglad-maila/{template}', [MailPreviewController::class, 'show'])->name('mail.preview');
    });

/*
|--------------------------------------------------------------------------
| Panel sprzedawcy (rola: seller)
|--------------------------------------------------------------------------
*/
// `role:seller,employee` — panel jest wspolny dla wlasciciela i jego pracownikow.
// O tym, KTO co w nim widzi, rozstrzyga `section:` przy poszczegolnych trasach,
// a rzeczy zastrzezone dla wlasciciela chowaja sie za zagniezdzonym
// `role:seller` (pracownik ma role `employee`, wiec sam sie o nia obija).
Route::middleware(['auth', 'role:seller,employee', 'ensure.consents'])
    ->prefix('sprzedawca')          // URL po polsku; nazwa trasy 'seller.' (kod) po angielsku
    ->name('seller.')
    ->group(function () {
        Route::get('/panel', SellerDashboard::class)->name('dashboard');

        // Analityka (Poziom 1: z danych, które już mamy; dla wszystkich pakietów).
        Route::get('/analityka', [AnalyticsController::class, 'index'])
            ->middleware('section:analytics')->name('analytics.index');

        /*
         * Pracownicy — TYLKO WLASCICIEL. Zarzadzanie ludzmi nie jest dzialem,
         * ktory sprzedawca moze komus oddac: pracownik z kompletem uprawnien
         * nadal nie zaprosi kolejnego ani nie odbierze dostepu sobie.
         *
         * Bez `saas`: konta pracownikow dziala tak samo w sklepie dedykowanym,
         * gdzie panel sprzedawcy jest panelem klienta.
         */
        Route::middleware('role:seller')->prefix('pracownicy')->name('employees.')->group(function () {
            Route::get('/', [EmployeeController::class, 'index'])->name('index');
            Route::post('/', [EmployeeController::class, 'store'])->name('store');
            Route::post('/{employee}', [EmployeeController::class, 'update'])->name('update');
            Route::post('/{employee}/odbierz', [EmployeeController::class, 'revoke'])->name('revoke');
            Route::post('/{employee}/przywroc', [EmployeeController::class, 'restore'])->name('restore');
        });

        /*
         * TYLKO WLASCICIEL. Dane firmy, ustawienia sprzedazy i klucze integracji
         * to rzeczy, ktorych sprzedawca nie oddaje razem z dzialem panelu:
         * zmiana NIP-u wychodzi na fakturach, a klucze Paynow i Fakturowni to
         * cudze pieniadze i realne dokumenty. Nie ma dla nich checkboxa —
         * `role:seller` odbija pracownika niezaleznie od nadanych mu dzialow.
         */
        Route::middleware('role:seller')->group(function () {
            // Profil sklepu (nazwa, opis, adres). Edycja przez POST (FOUNDATION sek. 5).
            Route::get('/sklep', [ShopProfileController::class, 'edit'])->name('shop.edit');
            Route::post('/sklep', [ShopProfileController::class, 'update'])->name('shop.update');

            // Ustawienia sklepu (sprzedaz/VAT, dostawa, platnosci, wlaczniki integracji).
            Route::get('/ustawienia', [ShopSettingsController::class, 'edit'])->name('settings.edit');
            Route::post('/ustawienia', [ShopSettingsController::class, 'update'])->name('settings.update');

            // Integracje (klucze uslug: Paynow, Fakturownia, Google Analytics).
            Route::get('/integracje', [IntegrationController::class, 'edit'])->name('integrations.edit');
            Route::post('/integracje', [IntegrationController::class, 'update'])->name('integrations.update');

            // Auto-uzupelnienie danych firmy po NIP (Biala lista MF). Zwraca JSON.
            Route::post('/firma/z-nip', CompanyLookupController::class)
                ->middleware('throttle:20,1')
                ->name('company.lookup');
        });

        // Usunięcie własnego sklepu (RODO). Osobny ekran z rachunkiem strat;
        // zlecenie ustawia karencję i gasi storefront, kasuje `shops:purge`.
        //
        // `saas`: to funkcja PLATFORMY, chroniąca sprzedawcę przed własnym
        // kliknięciem i dająca mu wyjście z usługi. Klient na własnym serwerze
        // kasuje sklep, kasując pliki i bazę — a zostawienie tego ekranu daje
        // przycisk, który po siedmiu dniach uruchamia `shops:purge` i usuwa
        // sklep razem z całą historią sprzedaży.
        Route::middleware(['saas', 'role:seller'])->group(function () {
            Route::get('/usun-sklep', [ShopDeletionController::class, 'show'])->name('deletion.show');
            Route::post('/usun-sklep', [ShopDeletionController::class, 'store'])->name('deletion.store');
            Route::post('/usun-sklep/cofnij', [ShopDeletionController::class, 'cancel'])->name('deletion.cancel');
        });

        // Wygląd sklepu (logo, kolor przewodni, szablon motywu). Edycja przez POST.
        Route::middleware('section:content')->group(function () {
            Route::get('/wyglad', [AppearanceController::class, 'edit'])->name('appearance.edit');
            Route::post('/wyglad', [AppearanceController::class, 'update'])->name('appearance.update');
        });

        // Zamówienia (podgląd + zmiana statusu). Lista i szczegół; zmiana statusu przez POST.
        Route::middleware('section:orders')->group(function () {
            Route::get('/zamowienia', [OrderController::class, 'index'])->name('orders.index');
            Route::get('/zamowienia/{order}', [OrderController::class, 'show'])->name('orders.show');
            // Etykieta przesyłki: pobieramy ją z InPostu przez NASZ serwer, bo token
            // ShipX nie ma prawa opuścić backendu. GET, bo to czyste pobranie pliku.
            Route::get('/zamowienia/{order}/etykieta', [OrderController::class, 'label'])->name('orders.label');

            // Odbiór kuriera: JEDNO zlecenie na wiele paczek (dopłata jest za
            // przyjazd, nie za paczkę). Osobny ekran, bo to operacja na zbiorze
            // przesyłek, a nie na pojedynczym zamówieniu.
            Route::get('/odbior-kuriera', [ShipmentPickupController::class, 'index'])->name('shipments.pickup');
        });

        // „Mój pakiet" — co sprzedawca ma wykupione i do kiedy, plus ZAKUP ONLINE
        // przez Paynow z konta platformy (klucze produkcyjne w `.env`, webhook
        // `payments.paynow.packages.webhook`). Zdanie „zakup dojdzie osobno"
        // wisiało tu długo po tym, jak zakup powstał — i raz wprowadziło
        // asystenta w błąd przy planowaniu. Nie wracać do niego.
        //
        // `saas`: sklep dedykowany jest opłacony jednorazowo i nie ma pakietu
        // do oglądania, kupowania ani przedłużania.
        // `role:seller`: pracownik nie kupuje cudzego abonamentu ani nie oglada
        // rozliczen sklepu, w ktorym pracuje. To sprawa wlasciciela i tylko jego.
        Route::get('/pakiet', [PackageController::class, 'show'])
            ->middleware(['saas', 'role:seller'])->name('package.show');
        Route::post('/pakiet/kup/{package}', [PackageController::class, 'purchase'])
            ->middleware(['saas', 'role:seller', 'throttle:10,1'])->name('package.purchase');

        // Kartoteka klientów — we wszystkich pakietach. Identyfikatorem jest
        // ADRES E-MAIL, bo klient bez konta (gość) nie ma `id`; `where` na
        // wzorcu przepuszcza kropki i małpę w segmencie ścieżki.
        Route::middleware('section:customers')->group(function () {
            Route::get('/klienci', [CustomerController::class, 'index'])->name('customers.index');
            Route::get('/klienci/{email}', [CustomerController::class, 'show'])
                ->where('email', '.*')->name('customers.show');
        });

        // Kody rabatowe (funkcja płatna — uprawnienie `discount_codes`, Pawilon).
        // Bez uprawnienia strona pokazuje zachętę zamiast narzędzia.
        Route::middleware('section:marketing')->group(function () {
            Route::get('/kody-rabatowe', [DiscountCodeController::class, 'index'])->name('discounts.index');
            Route::get('/kody-rabatowe/nowy', [DiscountCodeController::class, 'create'])->name('discounts.create');
            Route::get('/kody-rabatowe/{discountCode}/edycja', [DiscountCodeController::class, 'edit'])->name('discounts.edit');
            Route::post('/kody-rabatowe/{discountCode}/przelacz', [DiscountCodeController::class, 'toggle'])->name('discounts.toggle');
            Route::post('/kody-rabatowe/{discountCode}/usun', [DiscountCodeController::class, 'destroy'])->name('discounts.destroy');

            // Wiadomości do klientów (korespondencja seryjna — uprawnienie `bulk_mail`,
            // Pawilon). Kontroler prowadzi wyłącznie szkic; wysyłkę (próbka do siebie
            // i wysyłka do klientów) obsługuje komponent Livewire na stronie edycji.
            Route::get('/wiadomosci', [BulkMailingController::class, 'index'])->name('mailings.index');
            Route::get('/wiadomosci/nowa', [BulkMailingController::class, 'create'])->name('mailings.create');
            Route::post('/wiadomosci', [BulkMailingController::class, 'store'])->name('mailings.store');
            Route::get('/wiadomosci/{bulkMailing}/edycja', [BulkMailingController::class, 'edit'])->name('mailings.edit');
            Route::post('/wiadomosci/{bulkMailing}', [BulkMailingController::class, 'update'])->name('mailings.update');
            Route::post('/wiadomosci/{bulkMailing}/usun', [BulkMailingController::class, 'destroy'])->name('mailings.destroy');
        });

        // Informacje (strony tekstowe storefrontu). Edycja/usuwanie przez POST;
        // kolejność (drag & drop) zapisywana AJAX-em przez POST.
        Route::middleware('section:content')->group(function () {
            Route::get('/informacje', [PageController::class, 'index'])->name('pages.index');
            Route::get('/informacje/nowa', [PageController::class, 'create'])->name('pages.create');
            Route::post('/informacje', [PageController::class, 'store'])->name('pages.store');
            Route::post('/informacje/kolejnosc', [PageController::class, 'reorder'])->name('pages.reorder');
            Route::get('/informacje/{page}/edycja', [PageController::class, 'edit'])->name('pages.edit');
            Route::post('/informacje/{page}', [PageController::class, 'update'])->name('pages.update');
            Route::post('/informacje/{page}/usun', [PageController::class, 'destroy'])->name('pages.destroy');

            // Wzór regulaminu sklepu. `wzor` otwiera kreator (pola wypełnione
            // podpowiedziami z profilu), `wzor/wstaw` wkleja gotowy dokument do
            // EDYTORA — bez zapisu treści. Publikuje dopiero „Zapisz" sprzedawcy,
            // bo podstrona systemowa jest zawsze opublikowana i zapis oznaczałby
            // publikację w jego imieniu.
            Route::post('/informacje/{page}/wzor', [PageController::class, 'termsWizard'])->name('pages.terms');
            Route::post('/informacje/{page}/wzor/wstaw', [PageController::class, 'insertTerms'])->name('pages.terms.insert');
        });

        // Produkty (edycja/usuwanie przez POST — FOUNDATION sek. 5).
        Route::middleware('section:products')->group(function () {
            Route::get('/produkty', [ProductController::class, 'index'])->name('products.index');
            Route::get('/produkty/nowy', [ProductController::class, 'create'])->name('products.create');
            Route::post('/produkty', [ProductController::class, 'store'])->name('products.store');
            Route::get('/produkty/{product}/edycja', [ProductController::class, 'edit'])->name('products.edit');
            Route::post('/produkty/{product}', [ProductController::class, 'update'])->name('products.update');
            Route::post('/produkty/{product}/usun', [ProductController::class, 'destroy'])->name('products.destroy');

            // Zdjęcia produktu.
            Route::post('/produkty/{product}/zdjecia', [ProductImageController::class, 'store'])->name('products.images.store');
            Route::post('/produkty/{product}/zdjecia/kolejnosc', [ProductImageController::class, 'reorder'])->name('products.images.reorder');
            Route::post('/produkty/{product}/zdjecia/{image}/usun', [ProductImageController::class, 'destroy'])->name('products.images.destroy');
        });
    });

/*
|--------------------------------------------------------------------------
| Ponowna akceptacja dokumentów prawnych (po zmianie ich wersji)
|--------------------------------------------------------------------------
| Dostępne dla zalogowanego użytkownika; brama spod której nie wpuszczamy do
| panelu, dopóki zaległe zgody nie zostaną złożone (EnsureConsentsAreCurrent).
*/
Route::middleware('auth')->group(function () {
    Route::get('/zgody', [ConsentController::class, 'show'])->name('consents.show');
    Route::post('/zgody', [ConsentController::class, 'store'])->name('consents.store');

    // Redakcja treści przez AI („Popraw przez AI") — zwraca poprawiony tekst, nie zapisuje.
    // Limit liczy FRAGMENTY, nie kliknięcia: jedna korekta długiej strony to
    // kilkanaście żądań (dzielenie w resources/js/ai.js), więc dawne 30/min
    // kończyłoby się błędem limitu w połowie roboty. Ochrona przed nadużyciem
    // zostaje — 120 wywołań modelu na minutę to i tak dużo powyżej normalnej pracy.
    // Opis SEO pisany na żądanie z boksu „SEO" (zwraca tekst, nie zapisuje).
    Route::post('/ai/opis-seo', [AiController::class, 'seoDescription'])
        ->middleware('throttle:30,1')
        ->name('ai.seo-description');

    Route::post('/ai/popraw', [AiController::class, 'improve'])
        ->middleware('throttle:120,1')
        ->name('ai.improve');

    // Edycja własnego profilu (dane z users, awatar, hasło). Edycja przez POST.
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profil', [ProfileController::class, 'update'])->name('profile.update');
});

/*
|==============================================================================
| STOREFRONT — subdomena sklepu {shop}.{central_domain}
|==============================================================================
| Publiczny sklep jednego sprzedawcy. {shop} = slug = etykieta subdomeny;
| middleware `tenant` (ResolveShop) rozwiązuje model Shop z subdomeny i dzieli
| go z widokami (404 gdy slug bez sklepu). Inne kontrolery niż centrala — to
| sedno podziału „inne domeny → inne kontrolery". Grupa łapie tylko hosty
| {label}.{central_domain}, więc centrala (bez subdomeny) działa jak dotąd.
*/
// TRYB DEDYKOWANY: te same trasy, ale BEZ ograniczenia domeny — sklep jest
// jeden i mieszka wprost na domenie klienta, więc `{shop}` nie ma skąd wziąć
// wartości (ResolveShop bierze wtedy jedyny sklep w instalacji).
//
// Definicja tras siedzi w JEDNYM domknięciu i jest rejestrowana raz. Rozpisanie
// tych 39 tras w dwóch gałęziach `if` gwarantowałoby, że za pół roku będą się
// różnić — a rozjazd między trybami jest tu najdroższym możliwym błędem.
$storefrontRoutes = function () {
    // Mapa strony i robots.txt sklepu. MUSZĄ być tutaj, a nie wśród tras
    // centrali: trasa bez ograniczenia domeny pasuje do KAŻDEGO hosta, więc
    // zdefiniowana wyżej w pliku przechwyciłaby też subdomeny i każdy sklep
    // serwowałby mapę centrali. Odpowiedniki centrali stoją na SAMYM KOŃCU
    // pliku, już za tą grupą — kolejność rejestracji jest tu jedynym
    // rozstrzygnięciem i dlatego pilnuje jej test.
    Route::get('/sitemap.xml', [SitemapController::class, 'storefront'])->name('storefront.sitemap');
    Route::get('/robots.txt', [RobotsController::class, 'storefront'])->name('storefront.robots');

    Route::get('/', [StorefrontHome::class, 'show'])->name('storefront.home');
    Route::get('/produkty', [StorefrontProduct::class, 'index'])->name('storefront.products');
    Route::get('/produkt/{product}', [StorefrontProduct::class, 'show'])->name('storefront.product');
    // Landing działu „Informacje" → 302 na pierwszą podstronę (lewe menu
    // przejmuje dalszą nawigację). PRZED wildcardem, by nie wpadł w {page}.
    Route::get('/informacje', [StorefrontPage::class, 'index'])->name('storefront.information');
    // Wirtualna „O sklepie" (treść z shop.description) — PRZED wildcardem, by
    // stały slug nie wpadł w /informacje/{page} (który szuka strony po id).
    Route::get('/informacje/'.config('pages.about.slug'), [StorefrontPage::class, 'about'])->name('storefront.about');
    // Nasza Polityka prywatności renderowana w motywie sklepu, jako ostatnia
    // pozycja działu „Informacje". Stały slug PRZED wildcardem {page}.
    Route::get('/informacje/'.config('pages.privacy.slug'), [StorefrontPage::class, 'privacy'])->name('storefront.privacy');
    Route::get('/informacje/{page}', [StorefrontPage::class, 'show'])->name('storefront.page');
    // Stary adres → 301 na nowy kanoniczny (przeniesienie na stałe).
    Route::redirect('/polityka-prywatnosci', '/informacje/'.config('pages.privacy.slug'), 301);
    Route::get('/koszyk', [StorefrontCart::class, 'show'])->name('storefront.cart');
    Route::get('/kasa', [StorefrontCheckout::class, 'show'])->name('storefront.checkout');
    Route::get('/kasa/dziekujemy', [StorefrontCheckout::class, 'confirmation'])->name('storefront.checkout.confirmation');

    // Strona płatności zamówienia (token = zaszyfrowany id zamówienia). Jedno
    // miejsce dla wszystkich linków „dokończ płatność": mail, „Moje konto",
    // powrót z Paynow, ekran podziękowania. Działa bez logowania.
    Route::get('/platnosc/{token}', [StorefrontPayment::class, 'show'])->name('storefront.payment.show');
    Route::post('/platnosc/{token}', [StorefrontPayment::class, 'pay'])->name('storefront.payment.pay');

    // Odstąpienie od umowy (14 dni) — publiczny formularz pod tym samym
    // tokenem zamówienia co płatność. Bez logowania, bo ustawa wymaga, by
    // złożenie oświadczenia było łatwe. Throttle chroni przed wysypem
    // zgłoszeń z jednego linku, nie przed klientem (limit jest hojny).
    // Wypis z korespondencji seryjnej — podpisany link ze stopki mailingu,
    // bez logowania. Samo wejście wypisuje (zgoda ma być odwoływalna równie
    // łatwo, jak udzielona); POST obok przywraca zgodę klikniętą omyłkowo.
    // Link NIE wygasa: mail sprzed roku musi dać się odsubskrybować.
    Route::get('/wypisz-sie/{customer}', [StorefrontUnsubscribe::class, 'show'])
        ->middleware('signed')->name('storefront.unsubscribe');
    Route::post('/wypisz-sie/{customer}/przywroc', [StorefrontUnsubscribe::class, 'restore'])
        ->middleware('signed')->name('storefront.unsubscribe.restore');

    Route::get('/zwrot/{token}', [StorefrontOrderReturn::class, 'show'])->name('storefront.return.show');
    Route::post('/zwrot/{token}', [StorefrontOrderReturn::class, 'store'])
        ->middleware('throttle:10,1')->name('storefront.return.store');

    /*
    |----------------------------------------------------------------------
    | Konta klientów (guard `customer`, w obrębie sklepu)
    |----------------------------------------------------------------------
    | Rejestracja bez hasła → podpisany link aktywacyjny mailem → ustawienie
    | hasła + przypięcie wcześniejszych zamówień + auto-login. Logowanie i
    | wylogowanie scope'owane do sklepu. Aktywacja pod `signed` (link z maila).
    */
    Route::get('/rejestracja', [StorefrontRegister::class, 'create'])->name('storefront.register');
    Route::post('/rejestracja', [StorefrontRegister::class, 'store'])
        ->middleware('throttle:10,1')->name('storefront.register.store');
    Route::get('/rejestracja/potwierdzenie', [StorefrontRegister::class, 'registered'])
        ->name('storefront.register.confirmation');

    Route::get('/aktywacja/{customer}', [StorefrontActivation::class, 'create'])
        ->middleware('signed')->name('storefront.activation');
    Route::post('/aktywacja/{customer}', [StorefrontActivation::class, 'store'])
        ->middleware('signed')->name('storefront.activation.store');

    // Odzyskiwanie hasła klienta. Link jest PODPISANY i niesie identyfikator
    // konta, bo konta są per sklep — ten sam e-mail bywa kontem u wielu
    // sprzedawców i token szukany po samym adresie trafiłby w cudze.
    Route::get('/nie-pamietam-hasla', [StorefrontPasswordReset::class, 'create'])
        ->name('storefront.password.request');
    Route::post('/nie-pamietam-hasla', [StorefrontPasswordReset::class, 'store'])
        ->middleware('throttle:password_reset')->name('storefront.password.email');
    Route::get('/nowe-haslo/{customer}', [StorefrontPasswordReset::class, 'edit'])
        ->middleware('signed')->name('storefront.password.reset');
    Route::post('/nowe-haslo/{customer}', [StorefrontPasswordReset::class, 'update'])
        ->middleware('signed')->name('storefront.password.update');

    Route::get('/logowanie', [StorefrontAuth::class, 'create'])->name('storefront.login');
    Route::post('/logowanie', [StorefrontAuth::class, 'store'])
        ->middleware('throttle:10,1')->name('storefront.login.attempt');
    Route::post('/wyloguj', [StorefrontAuth::class, 'destroy'])->name('storefront.logout');

    // Moje konto — tylko zalogowany klient (guard `customer`): historia
    // zamówień, dane profilu, zmiana hasła, usunięcie konta (RODO).
    Route::middleware('auth.customer')->prefix('moje-konto')->name('storefront.account.')->group(function () {
        Route::get('/', [StorefrontAccount::class, 'index'])->name('index');
        Route::get('/zamowienia', [StorefrontAccount::class, 'orders'])->name('orders');
        Route::get('/zamowienia/{order}', [StorefrontAccount::class, 'order'])->name('order');
        Route::get('/dane', [StorefrontAccount::class, 'edit'])->name('edit');
        Route::post('/dane', [StorefrontAccount::class, 'update'])->name('update');
        Route::post('/zgody', [StorefrontAccount::class, 'consents'])->name('consents');
        Route::post('/haslo', [StorefrontAccount::class, 'password'])->name('password');
        Route::post('/usun', [StorefrontAccount::class, 'destroy'])->name('destroy');
    });
};

if (Mode::dedicated()) {
    // Sklep na domenie głównej. Trasy centrali, które by z nim kolidowały, są
    // w tym trybie zamknięte middlewarem `saas` albo w ogóle nierejestrowane
    // (strona główna platformy) — patrz góra pliku.
    Route::middleware(['tenant', 'record.traffic'])->group($storefrontRoutes);
} else {
    Route::domain('{shop}.'.config('tenancy.central_domain'))
        ->middleware(['tenant', 'record.traffic'])
        ->group($storefrontRoutes);
}

/*
|--------------------------------------------------------------------------
| Mapa strony i robots.txt centrali
|--------------------------------------------------------------------------
| CELOWO na samym końcu pliku, ZA grupą subdomen sklepów. Te trasy nie są
| przypięte do hosta (centrala odpowiada też na `www` i pod gołym adresem),
| więc pasują do wszystkiego — gdyby stały wyżej, przechwyciłyby subdomeny
| i każdy sklep dostałby mapę centrali zamiast własnej. Usterka byłaby cicha:
| strona wyglądałaby normalnie, a Google indeksowałby nie to, co trzeba.
|
| `robots.txt` działa tylko dopóki NIE MA pliku `public/robots.txt` — .htaccess
| oddaje istniejące pliki z pominięciem Laravela.
*/
// W sklepie dedykowanym nie ma centrali, więc nie ma też jej mapy strony ani
// robots.txt — te adresy należą się SKLEPOWI. Trasy centrali stoją w pliku ZA
// grupą storefrontu, więc nadpisałyby jego wersje i Google indeksowałby mapę
// platformy zamiast katalogu produktów. Usterka byłaby cicha: adres
// odpowiadałby poprawnym XML-em, tylko nie tym, co trzeba.
// Żaden widok nie woła nazw `sitemap` ani `robots`, więc mogą zniknąć.
if (Mode::saas()) {
    Route::get('/sitemap.xml', [SitemapController::class, 'central'])->name('sitemap');
    Route::get('/robots.txt', [RobotsController::class, 'central'])->name('robots');
}
