<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Włącznik zakładki „Kontakt" z formularzem na storefroncie.
 *
 * Domyślnie WYŁĄCZONY: formularz to nowa skrzynka do pilnowania, a sprzedawca,
 * który o nim nie wie, nie odpisze klientom — gorzej niż brak formularza.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->boolean('contact_form_enabled')->default(false)->after('contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('contact_form_enabled');
        });
    }
};
