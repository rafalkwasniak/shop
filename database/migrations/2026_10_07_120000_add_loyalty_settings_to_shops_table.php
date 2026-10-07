<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ustawienia punktów za zakupy. Wszystko domyślnie WYŁĄCZONE — kolumny mogą
 * leżeć na produkcji, zanim pojawi się ekran ustawień, i niczego nie zmieniają.
 *
 * - `loyalty_earn_percent` — ile % zapłaconej wartości produktów wraca w punktach.
 * - `loyalty_point_value` — ile złotych wart jest jeden punkt (lista w config/loyalty.php).
 * - `loyalty_validity_months` — ważność; NULL = punkty nie wygasają.
 * - `loyalty_delay_days` — karencja; NULL = długość okna zwrotu.
 * - `loyalty_max_redeem_percent` — punktami najwyżej X% wartości produktów; NULL = bez limitu.
 * - `loyalty_min_redeem_points` — od ilu punktów można je wydawać; NULL = od pierwszego.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->boolean('loyalty_enabled')->default(false);
            $table->decimal('loyalty_earn_percent', 5, 2)->nullable();
            $table->decimal('loyalty_point_value', 6, 2)->default(0.01);
            $table->unsignedSmallInteger('loyalty_validity_months')->nullable()->default(12);
            $table->unsignedSmallInteger('loyalty_delay_days')->nullable();
            $table->unsignedTinyInteger('loyalty_max_redeem_percent')->nullable();
            $table->unsignedInteger('loyalty_min_redeem_points')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'loyalty_enabled', 'loyalty_earn_percent', 'loyalty_point_value',
                'loyalty_validity_months', 'loyalty_delay_days',
                'loyalty_max_redeem_percent', 'loyalty_min_redeem_points',
            ]);
        });
    }
};
