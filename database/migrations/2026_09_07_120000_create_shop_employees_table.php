<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Członkostwo pracownika w sklepie (plan-shop-employees, krok 2).
 *
 * Osobna tabela, a NIE kolumna `shop_id` na `users`: adres e-mail w `users`
 * jest unikalny, a księgowa obsługująca dwa sklepy to realny przypadek, nie
 * hipoteza. Z kolumną kończy się on migracją danych; z tabelą — dołożeniem
 * przełącznika sklepu. Na start przełącznika nie ma, bo członkostwo jest jedno.
 *
 * `permissions` to lista działów panelu (App\Enums\PanelSection), nie kolumny
 * boolean. Nowy dział = nowy case enuma, bez migracji.
 *
 * Odebranie dostępu ustawia `revoked_at`, NIE kasuje wiersza: inaczej znika
 * ślad, kto i kiedy miał dostęp do danych osobowych klientów sklepu — a to
 * jest dokładnie ta informacja, o którą pyta się po fakcie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->json('permissions');

            // Kto zaprosił. `nullOnDelete`, a nie kaskada — usunięcie konta
            // właściciela nie może zabrać ze sobą historii zatrudnienia.
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('invited_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            // Jedno członkostwo na parę. Ponowne zaproszenie tej samej osoby
            // odświeża istniejący wiersz, zamiast tworzyć drugi z innym
            // zestawem uprawnień — przy dwóch wierszach nie dałoby się
            // odpowiedzieć, który obowiązuje.
            $table->unique(['shop_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_employees');
    }
};
