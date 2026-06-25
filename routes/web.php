<?php

use App\Http\Controllers\Administrator\DashboardController as AdministratorDashboard;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Seller\DashboardController as SellerDashboard;
use Illuminate\Support\Facades\Route;

/*
|==============================================================================
| CENTRALA — domena platformy (config('tenancy.central_domain'))
|==============================================================================
| Zarządzanie: strona główna platformy, logowanie/rejestracja, panel
| administratora i sprzedawcy. Sprzedawca zarządza sklepem TUTAJ, nie na
| swojej subdomenie.
|
| Architektura wielonajemcza: storefronty sprzedawców będą serwowane z
| subdomen {shop}.{central_domain} (np. bukiety.shop.kwasniak.org) — patrz
| wyłączony szkielet STOREFRONT na dole pliku. Dopóki subdomeny nie są
| włączone na serwerze, wszystko działa na domenie centrali bez wiązania
| Route::domain (uniknięcie kruchości na localhost/www/testach).
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Uwierzytelnianie (wspólne dla admina i sprzedawcy)
|--------------------------------------------------------------------------
| Jedno wejście logowania; po zalogowaniu przekierowanie zależne od roli
| (UserRole::homeRoute()).
*/
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

// Rejestracja sprzedawcy (nowe konta otrzymują rolę 'seller').
Route::get('/register', [RegisterController::class, 'create'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

/*
|--------------------------------------------------------------------------
| Panel administratora (rola: admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])
    ->prefix('administrator')
    ->name('administrator.')
    ->group(function () {
        Route::get('/dashboard', AdministratorDashboard::class)->name('dashboard');
    });

/*
|--------------------------------------------------------------------------
| Panel sprzedawcy (rola: seller)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:seller'])
    ->prefix('sprzedawca')          // URL po polsku; nazwa trasy 'seller.' (kod) po angielsku
    ->name('seller.')
    ->group(function () {
        Route::get('/dashboard', SellerDashboard::class)->name('dashboard');
    });

/*
|==============================================================================
| STOREFRONT — subdomena sklepu {shop}.{central_domain}  [SZKIELET, WYŁĄCZONY]
|==============================================================================
| Publiczny sklep jednego sprzedawcy. {shop} = slug = etykieta subdomeny;
| middleware rozwiązuje model Shop i scope'uje wszystko do tego sklepu
| (jedna baza + shop_id). Inne kontrolery niż centrala — to jest sedno
| podziału „inne domeny → inne kontrolery".
|
| Włączymy razem z budową storefrontu i wildcard-subdomen na serwerze:
|
| Route::domain('{shop}.'.config('tenancy.central_domain'))
|     ->middleware('tenant')               // App\Http\Middleware\ResolveShop
|     ->group(function () {
|         Route::get('/', [Storefront\HomeController::class, 'show'])->name('storefront.home');
|         Route::get('/produkt/{product}', [Storefront\ProductController::class, 'show'])->name('storefront.product');
|         // /koszyk, /kategoria/{...}, ...
|     });
*/
