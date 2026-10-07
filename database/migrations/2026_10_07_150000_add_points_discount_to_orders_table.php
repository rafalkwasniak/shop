<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Część wartości produktów zapłacona punktami za zakupy. Osobno od
 * `discount_amount` (kod rabatowy), bo to dwa różne źródła: kod jest
 * migawką zniżki, a punkty są pieniądzem klienta, który wraca na jego konto
 * przy anulowaniu, zwrocie albo edycji zamówienia w dół.
 *
 * Kwota w zł, nie liczba punktów: sumy, faktura i VAT liczą się w złotych,
 * a liczbę wydanych punktów zna księga (`loyalty_entries` + `usages`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('points_discount', 10, 2)->default(0)->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('points_discount');
        });
    }
};
