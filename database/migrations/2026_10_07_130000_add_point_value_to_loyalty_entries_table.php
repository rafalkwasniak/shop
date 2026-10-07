<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wartość punktu (w zł) z chwili zapisu wpisu.
 *
 * Sprzedawca może zmienić wartość punktu; salda przeliczamy wtedy z zachowaniem
 * wartości w złotych (300 pkt × 1 gr → 30 pkt × 10 gr), ale HISTORIA zostaje
 * w jednostkach ze swojego dnia — klient widzi „Za zakup +300" i osobny wpis
 * „Przeliczenie". Żeby późniejszy zwrot odebrał właściwą liczbę punktów, każdy
 * wpis pamięta, ile wtedy był wart punkt, a odbieranie liczy się w groszach.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_entries', function (Blueprint $table) {
            $table->decimal('point_value', 6, 2)->nullable()->after('remaining');
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_entries', function (Blueprint $table) {
            $table->dropColumn('point_value');
        });
    }
};
