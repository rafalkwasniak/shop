<?php

use App\Http\Controllers\Administrator\DashboardController as AdministratorDashboard;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Seller\DashboardController as SellerDashboard;
use Illuminate\Support\Facades\Route;

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
    ->prefix('seller')
    ->name('seller.')
    ->group(function () {
        Route::get('/dashboard', SellerDashboard::class)->name('dashboard');
    });
