<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'discord' => [
        // Adres webhooka kanału, na który lecą alerty o błędach.
        'webhook' => env('DISCORD_WEBHOOK_URL'),
    ],

    // AI (dostawcy, modele, zadania) mieszka w osobnym pliku: config/ai.php.

    'mf' => [
        // Biała lista podatników VAT (Ministerstwo Finansów) — auto-fill firmy z NIP. Bez klucza.
        'base_url' => env('MF_WHITELIST_URL', 'https://wl-api.mf.gov.pl'),
    ],

    'gus' => [
        // GUS REGON BIR1.1 — dokładny auto-fill firmy z NIP (pełna nazwa + adres + województwo).
        // Wymaga darmowego klucza użytkownika BIR. Pusty klucz = pomijamy GUS (fallback: Biała lista).
        'key' => env('GUS_REGON_KEY'),
        'base_url' => env('GUS_REGON_URL', 'https://wyszukiwarkaregon.stat.gov.pl'),
    ],

    'inpost' => [
        // Geowidget v5 — mapa wyboru paczkomatu. Token jest PUBLICZNY z natury
        // (ląduje w HTML strony), chroni go dowiązanie do domeny: wygenerowany
        // na `*.kramio.pl`, więc działa na wszystkich storefrontach i tylko tam.
        // To token PLATFORMY (Poziom 1 — sprzedawca nie musi mieć konta InPost).
        // Sprzedawca z własnym kontem wkleja swój do shop_integrations i ten ma
        // pierwszeństwo; ten wpis jest zapasowy. Patrz pamięć „plan-shipping”.
        'geowidget_token' => env('INPOST_GEOWIDGET_TOKEN'),

        // ShipX — nadawanie przesyłek (etykiety). Model PER-SKLEP jak Paynow:
        // token i Organization ID sprzedawcy żyją w shop_integrations
        // (zaszyfrowane) — TU NIE MA sekretów. Trzymamy wyłącznie stałe adresy
        // API; wyboru sandbox/produkcja dokonuje sprzedawca przy integracji.
        //
        // UWAGA BEZPIECZEŃSTWA: token ShipX ma zakres `api:shipx`, czyli UMIE
        // NADAWAĆ PACZKI na koszt sprzedawcy. Nigdy nie wolno go wypuścić do
        // przeglądarki — mapa paczkomatów używa OSOBNEGO tokenu platformy
        // (`geowidget_token` wyżej, zakres `api:apipoints`, tylko odczyt punktów).
        'shipx' => [
            'base_url' => [
                'sandbox' => 'https://sandbox-api-shipx-pl.easypack24.net',
                'production' => 'https://api-shipx-pl.easypack24.net',
            ],
            'timeout' => (int) env('INPOST_SHIPX_TIMEOUT', 30),

            // Nazwy usług ShipX, którymi nadajemy. `inpost_courier_c2c` to
            // usługa „InPost Kurier" z Managera Paczek — jedyna kurierska
            // dostępna na koncie firmowym bez rozszerzania umowy (zweryfikowane
            // na sandboxie 2026-08-08: `inpost_courier_standard` nie pojawia się
            // w ofertach). Konta z bogatszą umową mogą mieć więcej; docelowo
            // wybór powinien iść z OFERT dla konkretnej przesyłki, bo lista
            // usług systemowych (`GET /v1/services`) mówi, co istnieje, a nie
            // co wolno kupić temu konkretnemu sprzedawcy.
            // Bez `env()` — to stałe biznesowe, nie ustawienia środowiska.
            'service' => [
                'locker' => 'inpost_locker_standard',
                'courier' => 'inpost_courier_c2c',
            ],
        ],
    ],

    'fakturownia' => [
        // Faktury VAT. `url` = adres konta (np. https://twojadomena.fakturownia.pl).
        // Ten wpis to globalna/platformowa integracja; token per-sklep i tak trafi
        // do shop_integrations (zaszyfrowany). Na razie nieużywane — miejsce na token.
        'url' => env('FAKTUROWNIA_URL'),
        'token' => env('FAKTUROWNIA_TOKEN'),
    ],

    'paynow' => [
        // Płatności online (bramka mBanku). Model PER-SKLEP: klucze sprzedawcy
        // (dostęp do API + obliczanie podpisu) żyją w shop_integrations, zaszyfrowane
        // — TU NIE MA żadnych sekretów ani wpisów w `.env`. Trzymamy wyłącznie stałe,
        // publiczne adresy API operatora; wybór sandbox/produkcja robi środowisko
        // zapisane przy integracji sklepu. (Płatność za SaaS-a, gdyby kiedyś powstała,
        // byłaby osobną integracją platformy i dopiero ona sięgnęłaby po `.env`.)
        'base_url' => [
            'sandbox' => 'https://api.sandbox.paynow.pl',
            'production' => 'https://api.paynow.pl',
        ],

        // Konto PLATFORMY — opłaty za pakiety Kramio (pieniądze idą do nas,
        // nie do sprzedawcy). Jedyna konfiguracja Paynow w `.env`; kluczy
        // sklepów tu nie ma i nie będzie.
        'platform' => [
            'api_key' => env('PAYNOW_PLATFORM_API_KEY'),
            'signature_key' => env('PAYNOW_PLATFORM_SIGNATURE_KEY'),
            'environment' => env('PAYNOW_PLATFORM_ENVIRONMENT', 'sandbox'),
        ],
    ],

    'przelewy24' => [
        // Druga bramka płatności online, model PER-SKLEP jak Paynow: Merchant ID,
        // POS ID, klucz CRC i klucz API sprzedawcy żyją w shop_integrations,
        // zaszyfrowane — TU NIE MA sekretów ani wpisów w `.env`. Ten sam host
        // obsługuje API (`/api/v1/...`) i przekierowanie kupującego
        // (`/trnRequest/{token}`), więc wystarcza jeden adres na środowisko.
        'base_url' => [
            'sandbox' => 'https://sandbox.przelewy24.pl',
            'production' => 'https://secure.przelewy24.pl',
        ],

        // Publiczny adres IP, z którego TEN serwer woła API. P24 wpuszcza żądania
        // tylko z adresów wpisanych w panelu sprzedawcy („Moje dane → Dane API i
        // konfiguracja → Adres IP"), więc karta integracji pokazuje go do
        // przeklejenia. Zależy od maszyny, nie od kodu — dlatego `.env`.
        // PRZEPROWADZKA: nowy serwer = nowe IP = każdy sprzedawca z P24 musi je
        // zmienić u siebie, inaczej płatności staną.
        'server_ip' => env('SERVER_OUTBOUND_IP'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Analytics — statystyki CENTRALI
    |--------------------------------------------------------------------------
    |
    | Dotyczy wyłącznie stron platformy (landing, rejestracja, logowanie,
    | dokumenty prawne). Sklepy sprzedawców mają WŁASNE identyfikatory, wpisywane
    | w Integracjach i trzymane per sklep — te dwa pomiary nigdy się nie mieszają.
    |
    | Identyfikator siedzi w `.env`, choć nie jest sekretem (i tak widać go w
    | źródle strony). Chodzi o to, żeby kopia serwisu postawiona lokalnie albo
    | na testowej domenie NIE dosypywała ruchu do statystyk produkcji — brak
    | wpisu po prostu wyłącza pomiar.
    |
    */

    'google' => [
        'analytics_id' => env('GOOGLE_ANALYTICS_ID'),
        // Kod weryfikacyjny Search Console dla CENTRALI (kramio.pl). Sklepy mają
        // własny, per sklep, w Integracjach — ten dotyczy wyłącznie stron
        // platformy i bez niego nie da się zgłosić mapy centrali.
        'site_verification' => env('GOOGLE_SITE_VERIFICATION'),
    ],

];
