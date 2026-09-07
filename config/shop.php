<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tryb pracy aplikacji
    |--------------------------------------------------------------------------
    |
    | 'saas'      — Kramio: wielu sprzedawców, rejestracja, pakiety, abonament,
    |               landing platformy, konsola administratora, subdomeny.
    | 'dedicated' — sklep JEDNEGO klienta, na jego serwerze i jego domenie.
    |               Warstwa platformy jest wtedy niewidoczna: nie ma rejestracji,
    |               cennika, ekranu „Mój pakiet" ani konsoli admina, a właściciel
    |               dostaje wyłącznie panel swojego sklepu.
    |
    | WYŁĄCZAMY, NIE WYCINAMY. Kod platformy zostaje w repozytorium, bo ten sam
    | silnik obsługuje oba tryby — dzięki temu poprawka robi się raz, a kolejny
    | klient dedykowany to nowe wdrożenie, nie nowy projekt. Wycięcie
    | wielonajemczości (`shop_id` w każdej tabeli) byłoby tygodniami pracy w
    | ścieżce pieniędzy przy zysku, którego klient i tak nie widzi.
    |
    | Trybu NIE zmienia się na działającej instalacji.
    |
    */

    'mode' => env('SHOP_MODE', 'saas'),

    /*
    |--------------------------------------------------------------------------
    | Województwa (PL)
    |--------------------------------------------------------------------------
    |
    | Stały zbiór 16 województw. Używany jako opcje selecta w profilu sklepu i
    | jako reguła walidacji (Rule::in). Sklep jest jednokrajowy (PL) — patrz
    | decyzja o locale pl-first.
    |
    */

    'provinces' => [
        'dolnośląskie',
        'kujawsko-pomorskie',
        'lubelskie',
        'lubuskie',
        'łódzkie',
        'małopolskie',
        'mazowieckie',
        'opolskie',
        'podkarpackie',
        'podlaskie',
        'pomorskie',
        'śląskie',
        'świętokrzyskie',
        'warmińsko-mazurskie',
        'wielkopolskie',
        'zachodniopomorskie',
    ],

    /*
    |--------------------------------------------------------------------------
    | Opis sklepu
    |--------------------------------------------------------------------------
    |
    | Maksymalna długość opisu sklepu (HTML z edytora Trix). Jedno źródło prawdy —
    | używane w walidacji (ShopProfileRequest) i przy redakcji AI (limit długości
    | wyniku). Wyższy niż widoczny tekst, bo wlicza znaczniki formatowania.
    |
    */

    'description_max' => 4000,

    /*
    |--------------------------------------------------------------------------
    | Opis produktu
    |--------------------------------------------------------------------------
    |
    | Maksymalna długość opisu produktu (HTML z edytora Trix). Wyższy niż opis
    | sklepu, bo opisy produktów bywają dłuższe.
    |
    */

    'product_description_max' => 5000,

    /*
    |--------------------------------------------------------------------------
    | Asystent AI („Popraw przez AI")
    |--------------------------------------------------------------------------
    |
    | Limit redakcji AI na pojedyncze pole w ramach jednego załadowania strony
    | (pilnowany po stronie frontu). Chroni przed nadużyciem płatnego API.
    |
    */

    'ai' => [
        'max_uses_per_field' => (int) env('AI_MAX_USES_PER_FIELD', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Zdjęcia produktu (optymalizacja)
    |--------------------------------------------------------------------------
    |
    | Każde wgrane zdjęcie produktu jest skalowane (dłuższy bok do `max_side` px)
    | i ponownie kodowane jako WebP — niezależnie od formatu wejściowego. WebP daje
    | wyraźnie mniejsze pliki niż JPEG/PNG przy tej samej jakości i obsługuje
    | przezroczystość, więc trzymamy jeden format. Ponowne kodowanie usuwa metadane
    | (EXIF). `quality` 0–100: wyżej = lepsza jakość i większy plik.
    |
    | `max_upload_kb` = górny limit ORYGINAŁU na wejściu (walidacja uploadu). Wysoki
    | (20 MB), bo zdjęcia z telefonu bywają duże, a i tak zmniejszamy je do WebP i
    | oryginał wyrzucamy — limit chroni tylko przed skrajnościami/pamięcią GD.
    |
    | `max_per_product` = ile zdjęć można wgrać do jednego produktu. NIE jest to
    | uprawnienie pakietu, tylko próg techniczny — dlatego siedzi tu, a nie w
    | `packages`. Świadomie zostawiamy górną granicę zamiast „bez limitu":
    | walidacja tablicy bez ograniczenia to otwarte drzwi na wgranie kilkuset
    | plików naraz i wyczerpanie pamięci przez GD. Sklep dedykowany podnosi tę
    | wartość w `.env`, zamiast jej usuwać.
    |
    */

    'product_images' => [
        'max_side' => (int) env('PRODUCT_IMAGE_MAX_SIDE', 1600),
        'quality' => (int) env('PRODUCT_IMAGE_QUALITY', 82),
        'max_upload_kb' => 20480, // 20 MB
        'max_per_product' => (int) env('PRODUCT_IMAGE_MAX_PER_PRODUCT', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Wyróżnienie na stronie głównej sklepu
    |--------------------------------------------------------------------------
    |
    | Ile produktów sprzedawca może wyróżnić na stronie głównej (`show_on_homepage`).
    | Limit z premedytacją: strona główna ma być witryną „wow", a nie kopią listingu
    | — bez sufitu ktoś wrzuciłby na główną cały katalog i zniknąłby efekt. Sufit
    | domyka też liczbę sensownych układów głównej (hero/witryna dla ≤6). Dostępne
    | dla wszystkich pakietów (wygląd/kolory nie są uprawnieniem).
    |
    */

    'homepage_promoted_limit' => 6,

    /*
    | Gdy sprzedawca NIE wyróżnił żadnego produktu — ile LOSOWYCH aktywnych
    | pokazać na głównej (żeby nie była pusta; główna ma tak mało treści, że aż
    | prosi się o produkty). Losowe, nie „najnowsze", by za każdym wejściem
    | eksponować inne pozycje.
    */
    'homepage_fallback_count' => 3,

    /*
    |--------------------------------------------------------------------------
    | Rytm abonamentu
    |--------------------------------------------------------------------------
    |
    | Rok kosztuje tyle, co 10 miesięcy — dwa miesiące są gratis. Cennik na
    | landingu pokazuje OBIE kwoty: przekreśloną cenę 12 miesięcy (stawka
    | miesięczna × 12) i realną roczną, żeby zysk był widać od razu.
    |
    | Liczby siedzą tutaj, a nie w widoku, bo inaczej ta sama reguła mieszkałaby
    | w trzech miejscach (dzielnik 10 przy stawce miesięcznej, mnożnik 12 przy
    | cenie przekreślonej, różnica przy „2 miesiące gratis") i przy pierwszej
    | zmianie rozjechałaby się po cichu.
    |
    | UWAGA: cena przekreślona jest uczciwa dopiero wtedy, gdy abonament
    | MIESIĘCZNY faktycznie da się kupić. Dziś billing jest rocznny (patrz niżej
    | „cena na wystawie"), więc `months_total` × stawka to kwota, której nikt nie
    | zapłaci. Uruchamiając płatności, albo wystawcie plan miesięczny, albo
    | zdejmijcie przekreślenie z cennika.
    |
    */

    'billing' => [
        'months_paid' => 10,
        'months_total' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pakiety (abonament roczny)
    |--------------------------------------------------------------------------
    |
    | Definicja pakietów = źródło prawdy TYLKO w momencie zakupu/przypisania.
    | Model „snapshot": po przypisaniu pakietu sklep KOPIUJE `entitlements` do
    | siebie (kolumna `entitlements` na `shops`) i od tej pory żyje własnym
    | zestawem — zmiana tych wartości później nie dotyka już opłaconych sklepów
    | („kupiłeś, masz"). Patrz Shop::assignPackage() i Shop::entitlement().
    |
    | Slug (klucz, EN) jest stały i ukryty; `name` (PL) to naklejka widoczna dla
    | klienta i można ją zmieniać bez ruszania bazy. `order` daje jawną hierarchię
    | (kod nie zgaduje po slugu, co jest awansem, a co zejściem). `available`
    | decyduje, czy pakiet da się kupić — wycofanego NIE usuwamy (stare sklepy go
    | trzymają), tylko ustawiamy `false`.
    |
    | `entitlements` = kanoniczna lista uprawnień (ZATWIERDZONA 2026-07-19, 8 kluczy).
    | Wygląd/kolory i NASZA analityka są dla wszystkich, więc NIE są uprawnieniem
    | (GA/GTM to osobny klucz `ga_analytics`, płatny od Straganu). `max_products`
    | 24/72/240 dzieli się na pełne rzędy siatki (3/4/6 kolumn). Drabinka jest
    | ROZCIĄGNIĘTA, nie równoległa (2026-08-09): limit produktów nie jest motorem
    | konwersji — na płatny plan wypychają płatności/kurier/faktury, a na Pawilon
    | edycja zamówienia i kody rabatowe. Limit pracuje dopiero w GÓRNYM OGONIE:
    | sprzedawca z 200 pozycjami (biżuteria, koraliki) patrzył na najdroższy plan,
    | widział „do 96" i odpadał przed rejestracją. Stąd wysoki sufit u góry i
    | ostrożny w środku — Stragan 96 zabierałby powód, dla którego ktoś z 60
    | produktami musi wziąć Pawilon. Kram zostaje na 24, bo to jest bramka.
    |
    | `price_yearly` = cena roczna BRUTTO (VAT 23%). Reguła: rok = 10× miesiąc, czyli
    | 2 miesiące gratis (75/mc→750/rok, 150/mc→1500/rok). To cena „na wystawie";
    | realny billing (pobieranie pieniędzy) jest osobnym, późniejszym tematem.
    |
    | Uprawnienia płatne (`online_payments`, `courier_shipping`, `invoices`,
    | `ga_analytics`) startują od Straganu; `order_editing`, `discount_codes`,
    | `bulk_mail` są WYŁĄCZNIE w Pawilonie (uzasadniają 2× cenę). `bulk_mail` to
    | na razie flaga „na wyrost" — sama funkcja dojdzie później.
    |
    | `ai_weekly_limit` = liczba ZADAŃ AI na tydzień (okno wg numeru ISO). Jedno
    | zadanie to jedno kliknięcie sprzedawcy, nawet gdy długi tekst dzieli się na
    | kilkanaście wywołań modelu. Liczby są hojne z premedytacją: pomiar z
    | 2026-07-28 pokazał, że wywołanie kosztuje 0,05–0,7 GROSZA, więc limit nie
    | chroni budżetu przed sprzedawcą, tylko przed pętlą i skryptem (throttle
    | 30/min pozwala na ~43 tys. wywołań dziennie ≈ 60 zł). Szczyt użycia wypada
    | przy zakładaniu sklepu, potem ruch spada — stąd okno tygodniowe, nie dzienne.
    |
    */

    'packages' => [
        'stall' => [
            'name' => 'Kram',
            'order' => 1,
            'price_yearly' => 0,
            'available' => true,
            'entitlements' => [
                'max_products' => 60,
                'max_employees' => 0,
                'ai_weekly_limit' => 100,
                'online_payments' => false,
                'courier_shipping' => false,
                'invoices' => false,
                'ga_analytics' => false,
                'order_editing' => false,
                'discount_codes' => false,
                'bulk_mail' => false,
            ],
        ],
        'booth' => [
            'name' => 'Stragan',
            'order' => 2,
            'price_yearly' => 750,
            'available' => true,
            'entitlements' => [
                'max_products' => 300,
                'max_employees' => 0,
                'ai_weekly_limit' => 400,
                'online_payments' => true,
                'courier_shipping' => true,
                'invoices' => true,
                'ga_analytics' => true,
                'order_editing' => false,
                'discount_codes' => false,
                'bulk_mail' => false,
            ],
        ],
        'pavilion' => [
            'name' => 'Pawilon',
            'order' => 3,
            'price_yearly' => 1500,
            'available' => true,
            'entitlements' => [
                'max_products' => 600,
                'max_employees' => 5,
                'ai_weekly_limit' => 800,
                'online_payments' => true,
                'courier_shipping' => true,
                'invoices' => true,
                'ga_analytics' => true,
                'order_editing' => true,
                'discount_codes' => true,
                'bulk_mail' => true,
            ],
        ],

        /*
         * Sklep dedykowany — wdrożenie u jednego klienta, na jego serwerze.
         *
         * Nie jest pakietem w sensie handlowym: `available => false` trzyma go
         * poza cennikiem i poza ekranem zmiany pakietu, bo nikt go nie kupuje.
         * Przypisujemy go ręcznie przy wdrożeniu (`assignPackage('dedicated')`)
         * razem z `comped = true` na sklepie.
         *
         * DWA ZABEZPIECZENIA, ŻE NIC NIE WYGAŚNIE, celowo niezależne:
         *   1. `comped` — Shop::subscriptionActive() zwraca true od razu,
         *   2. `price_yearly => 0` — pakiet bez opłaty nie ma jak wygasnąć.
         * Gdyby ktoś kiedyś zdjął `comped`, sklep dalej działa.
         *
         * LIMITY TO DUŻE LICZBY, NIE `null`. Każdy odbiorca robi
         * `(int) $shop->entitlement(...)`, a `(int) null` daje 0 — przy zerze
         * ProductLimitLock zablokowałby sklep już na pierwszym produkcie.
         * Milion produktów i sto tysięcy zadań AI to w praktyce brak limitu,
         * a arytmetyka zostaje bezpieczna.
         */
        'dedicated' => [
            'name' => 'Sklep dedykowany',
            'order' => 99,
            'price_yearly' => 0,
            'available' => false,
            'entitlements' => [
                'max_products' => 1000000,
                'max_employees' => 1000,
                'ai_weekly_limit' => 100000,
                'online_payments' => true,
                'courier_shipping' => true,
                'invoices' => true,
                'ga_analytics' => true,
                'order_editing' => true,
                'discount_codes' => true,
                'bulk_mail' => true,
            ],
        ],
    ],

    // Slug pakietu domyślnego (darmowego) — przypisywany nowym sklepom.
    'default_package' => 'stall',

    /*
    |--------------------------------------------------------------------------
    | Cykl życia abonamentu
    |--------------------------------------------------------------------------
    |
    | `grace_days` — karencja po dacie opłacenia. Abonament roczny płacony
    | przelewem znaczy, że przegapienie terminu jest NORMALNE, nie złośliwe;
    | zgaszenie funkcji sekundę po północy generowałoby wyłącznie telefony.
    | W karencji sklep ma PEŁNE funkcje i widzi baner. Zero = zamek w terminie.
    |
    | `reminder_days` — ile dni PRZED terminem wychodzi przypomnienie. Treść
    | maila jest jedna dla wszystkich progów (mówi datę, nie „za ile dni"),
    | więc progi można zmieniać bez pisania nowych tekstów.
    |
    | `notice_days` / `urgent_days` — od kiedy ekran „Mój pakiet" przypomina o
    | terminie i od kiedy robi to na czerwono. Osobno od progów mailowych, bo
    | ekran widzi się przy każdej wizycie, a mail przychodzi raz.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Opłaty za pakiety
    |--------------------------------------------------------------------------
    |
    | `abandoned_after_hours` — po ilu godzinach rozpoczęta i nieopłacona
    | płatność trafia w konsoli admina na listę „wisi". Bramka rozstrzyga się w
    | minutach, ale przelew tradycyjny przez Paynow potrafi wpłynąć następnego
    | dnia roboczego — stąd doba, a nie godzina.
    |
    */

    'package_payments' => [
        'abandoned_after_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Zamówienia — progi listy „Wymaga uwagi" w konsoli admina
    |--------------------------------------------------------------------------
    |
    | `stalled_days` — po ilu dniach opłacone, ale niewydane zamówienie uznajemy
    | za zacięte. Trzy dni robocze to granica, po której klient zaczyna pisać;
    | rękodzieło bywa robione na zamówienie, więc niżej schodzić nie ma sensu.
    |
    | `unpaid_hours` — po ilu godzinach porzucona płatność online przestaje być
    | normalnym stanem „w toku". Doba, tak jak przy opłatach za pakiety.
    |
    */

    'orders' => [
        'attention' => [
            'stalled_days' => 3,
            'unpaid_hours' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pobranie (COD)
    |--------------------------------------------------------------------------
    |
    | Górne limity kwoty pobrania u InPostu, w złotych brutto, OSOBNE dla każdej
    | drogi: paczkomat inkasuje przez terminal skrytki, kurier przyjmuje też
    | gotówkę, więc progi się różnią. Wartości potwierdzone przez Rafała 17.08.
    |
    | Limit sprawdzamy W KASIE, a nie dopiero przy nadawaniu. Inaczej klient
    | złożyłby zamówienie, dostał potwierdzenie, a sprzedawca odbiłby się od
    | InPostu przy próbie nadania — z paczką, której nie da się wysłać, i
    | zamówieniem, które trzeba anulować ręcznie.
    |
    */

    'cash_on_delivery' => [
        'max_amount' => [
            'parcel_locker' => 5000,
            'courier' => 15000,
        ],
    ],

    'subscription' => [
        'grace_days' => 7,
        'reminder_days' => [14, 7, 1],
        'notice_days' => 30,
        'urgent_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Usuwanie sklepu
    |--------------------------------------------------------------------------
    |
    | `grace_days` — ile dni od zlecenia usunięcia sprzedawca ma na cofnięcie.
    | Storefront gaśnie NATYCHMIAST (żeby w karencji nie wpłynęło zamówienie do
    | sklepu, który za chwilę zniknie), a właściwe kasowanie robi `shops:purge`
    | po tym terminie. Admin kasuje z pominięciem karencji — chroni ona przed
    | własnym kliknięciem sprzedawcy, a nie przed świadomą decyzją platformy.
    |
    | `slug_quarantine_days` — jak długo po usunięciu adres subdomeny jest
    | zajęty. Bez kwarantanny stare linki, maile do klientów i wyniki w Google
    | prowadziłyby pod znanym adresem do CUDZEGO sklepu.
    |
    */

    'deletion' => [
        'grace_days' => 7,
        'slug_quarantine_days' => 90,
    ],

];
