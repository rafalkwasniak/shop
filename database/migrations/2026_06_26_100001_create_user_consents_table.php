<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Utrwalone zgody użytkowników na konkretne wersje dokumentów prawnych.
 * `legal_document_id` wskazuje niezmienny rekord wersji — to jest dowód
 * „co dokładnie zaakceptowano". `ip_address` na potrzeby audytu (RODO).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_document_id')->constrained();
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'legal_document_id']); // jedną wersję akceptuje się raz
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_consents');
    }
};
