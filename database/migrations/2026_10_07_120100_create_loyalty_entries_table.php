<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Księga punktów za zakupy.
 *
 * Kluczem jest (shop_id, e-mail), NIE konto klienta: zakup bez zaznaczenia
 * „Załóż konto" nie tworzy rekordu w `customers`, a punkty mają się należeć od
 * pierwszego zakupu i czekać na rejestrację (decyzja Rafała 06.10). Tak samo
 * kluczuje się kartoteka klientów (`CustomerDirectory`).
 *
 * Każdy wpis to linia historii (`points` ze znakiem — to widzi klient), a wpis
 * dodatni jest zarazem PORCJĄ: `remaining` mówi, ile z niej jeszcze zostało.
 * Saldo liczymy wyłącznie z `remaining`, historię wyłącznie z `points` — dwie
 * kolumny o jednym znaczeniu każda, zamiast przeliczać saldo z całej historii.
 * Ujemne `remaining` na wpisie odebrania to niepokryty dług (zwrot towaru, za
 * który punkty zdążyły już zostać wydane) — spłacają go kolejne porcje.
 *
 * `base_amount` = kwota, od której naliczono porcję. Przy zwrocie odbieramy
 * punkty PROPORCJONALNIE do tego, o ile zmalała ta kwota, a nie przeliczając
 * od bieżącego procentu sklepu — sprzedawca mógł go w międzyczasie zmienić.
 *
 * `loyalty_entry_usages` zapamiętuje, z których porcji poszły punkty wydane na
 * zamówienie — żeby anulowanie mogło oddać je do tych samych porcji, z ich
 * własnym terminem ważności.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->integer('points');
            $table->integer('remaining')->default(0);
            $table->decimal('base_amount', 10, 2)->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'email']);
        });

        Schema::create('loyalty_entry_usages', function (Blueprint $table) {
            $table->id();
            // Wpis wydania (ujemny) i porcja, z której zeszły punkty.
            $table->foreignId('entry_id')->constrained('loyalty_entries')->cascadeOnDelete();
            $table->foreignId('lot_id')->constrained('loyalty_entries')->cascadeOnDelete();
            $table->unsignedInteger('points');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_entry_usages');
        Schema::dropIfExists('loyalty_entries');
    }
};
