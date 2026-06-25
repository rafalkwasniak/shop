<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Domena centrali
    |--------------------------------------------------------------------------
    |
    | Główna domena platformy. Tu leży zarządzanie: logowanie, rejestracja,
    | panel sprzedawcy i administratora. Storefronty sprzedawców siedzą na
    | subdomenach {shop}.{central_domain} (np. bukiety.shop.kwasniak.org),
    | gdzie {shop} = slug sklepu = etykieta subdomeny.
    |
    | Routing per-domena (Route::domain) włączymy razem z budową storefrontu;
    | na razie cała aplikacja działa na domenie centrali. Patrz routes/web.php.
    |
    */

    'central_domain' => env('APP_DOMAIN', 'localhost'),

];
