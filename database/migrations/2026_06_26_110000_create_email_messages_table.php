<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbox/spool wychodzących maili. Każda wysyłka to wstawiony wiersz; krótka
 * komenda crona (`email:dispatch`) opróżnia kolejkę — bezpieczne na CloudLinux
 * LVE, w odróżnieniu od długo żyjącego demona kolejki. Wzorzec zaadaptowany z
 * kociaczek.com.pl; różnice u nas: `shop_id` (dobór szaty kolorystycznej per
 * sklep) oraz `heading`/`preheader` (nasz brandowalny komponent x-mail.message).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();

            // 1=Low (newsletter) … 3=High (krytyczne, np. aktywacja). Wyższy pierwszy.
            $table->unsignedTinyInteger('priority')->default(2);

            // Sklep-nadawca → po nim MailBranding::for() dobiera logo/kolory.
            // Nullable + bez FK: model Shop jeszcze nie istnieje (dojdzie później).
            $table->foreignId('shop_id')->nullable()->index();

            // Adresat.
            $table->string('to_email');
            $table->string('to_name')->nullable();

            // Treść („wsad”) wkładana w komponent x-mail.message.
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->string('heading')->nullable();
            $table->string('greeting')->nullable();
            $table->json('intro_lines')->nullable();
            $table->string('action_text')->nullable();
            $table->text('action_url')->nullable();
            $table->json('outro_lines')->nullable();

            // Harmonogram / stan dostarczenia. sent_at NULL = wciąż w kolejce.
            $table->timestamp('scheduled_at')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();

            $table->timestamps();

            // Pod zapytanie dyspozytora „co jest do wysłania teraz”.
            $table->index(['sent_at', 'failed_at', 'scheduled_at', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_messages');
    }
};
