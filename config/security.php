<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Throttling logowania
    |--------------------------------------------------------------------------
    |
    | Po przekroczeniu liczby nieudanych prób logowania (liczonych per
    | e-mail + IP) konto jest tymczasowo blokowane. `decay_seconds` to czas
    | blokady liczony od pierwszej nieudanej próby w oknie.
    |
    */

    'login' => [
        'max_attempts' => 5,
        'decay_seconds' => 300, // 5 minut
    ],

    /*
    |--------------------------------------------------------------------------
    | Logowanie dwuetapowe do centrali (kod z maila)
    |--------------------------------------------------------------------------
    |
    | Dotyczy KAŻDEGO, kto zarządza sklepem: administratora, sprzedawcy,
    | pracownika. Klientów sklepów nie dotyczy — kupują nawet bez konta.
    |
    | Kod: 6 cyfr. Same cyfry, bo tylko takie Safari pewnie wyłapuje z Maila
    | i podpowiada nad klawiaturą. Przy 5 próbach na kod trafienie na ślepo to
    | szansa 1 do 200 000, a każda nowa seria wymaga znowu hasła.
    |
    | `enabled` = WYŁĄCZNIK AWARYJNY. Gdy padnie SMTP, nikt nie wejdzie do
    | panelu; `LOGIN_TWO_FACTOR=false` w .env wraca do samego hasła. Do
    | pojedynczego wejścia bez wyłączania: `php artisan auth:login-code {email}`.
    |
    */

    'two_factor' => [
        'enabled' => (bool) env('LOGIN_TWO_FACTOR', true),
        'code_length' => 6,
        'ttl_minutes' => 10,
        'max_attempts' => 5,
        'resend_cooldown_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Limity formularzy publicznych (per IP)
    |--------------------------------------------------------------------------
    |
    | Trasy dostępne bez logowania, które COŚ URUCHAMIAJĄ: zakładają konto,
    | wysyłają maila, sprawdzają token. Blokada logowania liczona per e-mail
    | ich nie broni, bo atakujący za każdym razem podaje inny adres.
    |
    | Najgroźniejsza jest rejestracja: wysyła wiadomość aktywacyjną na DOWOLNY
    | podany adres, więc bez limitu Kramio jest darmową maszynką do zalewania
    | cudzej skrzynki. Płacimy za to reputacją domeny — a gdy ta siądzie, do
    | spamu zaczną wpadać maile transakcyjne WSZYSTKICH sprzedawców.
    |
    | Progi są celowo luźne wobec człowieka: konto sklepu zakłada się raz, a nie
    | pięć razy na minutę. Limit ma ściąć skrypt, nie zawadzać biuru za jednym
    | adresem IP.
    |
    */

    'public_forms' => [
        // Rejestracja sprzedawcy: konto + subdomena + mail aktywacyjny.
        'register' => ['max_attempts' => 5, 'decay_minutes' => 1],

        // Ustawienie hasła z tokenu aktywacyjnego. Token brokera jest długi i
        // losowy, więc to nie obrona przed zgadywaniem, tylko odcięcie skryptu,
        // który waliłby w ten adres bez końca.
        'activation' => ['max_attempts' => 10, 'decay_minutes' => 1],

        // Prośba o link do zmiany hasła. Ten formularz też wysyła maila na
        // podany adres, więc bez limitu byłby drugą maszynką do zalewania cudzej
        // skrzynki — obok rejestracji. Ciaśniej niż tam, bo o własne hasło
        // prosi się raz, a nie pięć razy pod rząd.
        'password_reset' => ['max_attempts' => 3, 'decay_minutes' => 1],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nagłówki bezpieczeństwa odpowiedzi
    |--------------------------------------------------------------------------
    |
    | Wysyłane przy każdej odpowiedzi HTTP (middleware SecurityHeaders). Audyt
    | ursalogic z 16.07.2026 dał platformie ocenę E za bezpieczeństwo — 55/100
    | IDENTYCZNIE na wszystkich 100 podstronach. Powodem nie była dziura w
    | aplikacji, tylko brak tych nagłówków; to poprawka, która działa na
    | wszystkie sklepy naraz.
    |
    | Wartość `null` = nagłówka nie wysyłamy (wygodne przy dostrajaniu).
    |
    | `Permissions-Policy`: geolokalizacja zostaje dozwolona dla własnej domeny,
    | bo mapa paczkomatów InPost używa jej do znalezienia punktów w pobliżu
    | kupującego. Kamera, mikrofon i pozostałe czujniki — wyłączone.
    |
    | Content-Security-Policy ŚWIADOMIE tu nie ma: storefront niesie inline
    | <style> z tokenami motywu, inline <script> GTM/GA i skrypty Livewire, więc
    | sensowne CSP wymaga nonce'ów i jest osobnym zadaniem, nie jedną linijką.
    |
    */

    'headers' => [
        // Przeglądarka nie zgaduje typu treści — blokuje atak plikiem
        // podszywającym się pod inny typ („zdjęcie", które jest skryptem).
        'X-Content-Type-Options' => 'nosniff',

        // Clickjacking: naszych stron nie wolno osadzić w ramce obcej witryny.
        'X-Frame-Options' => 'SAMEORIGIN',

        // Do obcych witryn wysyłamy samą domenę, bez ścieżki — adresy z tokenem
        // (płatność, faktura) nie mogą wyciec w nagłówku Referer.
        'Referrer-Policy' => 'strict-origin-when-cross-origin',

        'Permissions-Policy' => 'geolocation=(self), camera=(), microphone=(), payment=(), usb=()',
    ],

    /*
    |--------------------------------------------------------------------------
    | HSTS (Strict-Transport-Security)
    |--------------------------------------------------------------------------
    |
    | Wysyłany WYŁĄCZNIE po HTTPS — na zwykłym HTTP nic nie znaczy, a lokalnie
    | tylko przeszkadza. `include_subdomains` jest bezpieczne, bo storefronty
    | siedzą pod wildcard SSL `*.kramio.pl` (CLAUDE.md sek. 3 pkt 5).
    |
    | `preload` ŚWIADOMIE wyłączony: wpis na listę preload przeglądarek jest
    | praktycznie nieodwracalny i wymusza HTTPS na wszystkich subdomenach na
    | zawsze. Do rozważenia, gdy platforma się ustabilizuje.
    |
    */

    'hsts' => [
        'enabled' => true,
        'max_age' => 31536000,          // rok
        'include_subdomains' => true,
        'preload' => false,
    ],

];
