<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wersjonowane dokumenty prawne (Regulamin, Polityka Prywatności…). Każda
 * zmiana treści = NOWY rekord (nowy `version`). Opublikowanej wersji nigdy
 * nie edytujemy — dzięki temu zgoda użytkownika (FK na konkretny rekord)
 * utrwala dokładnie tę treść, którą zaakceptował.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();        // wartość App\Enums\LegalDocumentType
            $table->unsignedInteger('version');
            $table->longText('content')->nullable(); // na start puste, treść dochodzi później
            $table->timestamp('published_at')->nullable(); // null = szkic; current() bierze najnowszą opublikowaną
            $table->timestamps();

            $table->unique(['type', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
