<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Którą bramką płaci zamówienie online (`App\Enums\PaymentProvider`). Do tej
 * pory była jedna (Paynow), więc wystarczała metoda `online`; z drugą bramką
 * (Przelewy24) zamówienie musi pamiętać operatora — dla strony płatności, maili
 * i webhooka. Puste dla zamówień bez płatności online.
 *
 * Wszystkie dotychczasowe zamówienia online (także usunięte logicznie) płaciły
 * przez Paynow — innej bramki nie było — więc uzupełniamy je wprost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_provider')->nullable()->after('payment_method');
        });

        DB::table('orders')
            ->where('payment_method', 'online')
            ->update(['payment_provider' => 'paynow']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_provider');
        });
    }
};
