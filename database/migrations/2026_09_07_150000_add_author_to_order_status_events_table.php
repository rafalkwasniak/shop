<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autor zmiany statusu zamówienia (plan-shop-employees, krok 6).
 *
 * Dopóki panel miał jednego użytkownika, pytanie „kto?" miało jedną możliwą
 * odpowiedź i kolumna byłaby ozdobą. Z pracownikami cała wartość dziennika
 * bierze się właśnie z niej: „kto anulował to zamówienie" to pierwsze pytanie,
 * jakie sprzedawca zada, gdy coś pójdzie nie tak.
 *
 * NULLABLE, bo autor bywa nieludzki i to jest normalny stan, nie brak danych:
 * potwierdzenie płatności przychodzi webhookiem Paynow, a część przejść robi
 * cron. Widok nazywa taki wpis „Automatycznie", zamiast zostawiać puste miejsce.
 *
 * `nullOnDelete`: skasowanie konta pracownika nie może zabrać historii
 * zamówienia — wpis zostaje, traci tylko podpis. Kaskada wycinałaby zdarzenia
 * ze środka osi czasu i zamówienie wyglądałoby, jakby nigdy nie zmieniło stanu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_status_events', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('order_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_status_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
