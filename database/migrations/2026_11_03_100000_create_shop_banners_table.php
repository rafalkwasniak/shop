<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banery strony głównej sklepu (slider). Jeden baner = statyczny obrazek,
 * dwa i więcej = slider. Liczba z pakietu (`max_banners`).
 *
 * Obrazek to KILKA plików WebP o różnych szerokościach (do `srcset`), więc
 * zamiast ścieżki trzymamy katalog + uuid i listę szerokości; plik wariantu
 * składa ShopBanner::path(). Wymiary największego wariantu idą do atrybutów
 * `width`/`height` — przeglądarka zna proporcje przed pobraniem obrazka
 * i strona nie skacze przy ładowaniu.
 *
 * `starts_on`/`ends_on` — opcjonalne „pokazuj od–do" (daty włącznie, czas
 * Warszawy). Baner „Promocja do niedzieli" sam znika w poniedziałek.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('image_uuid', 36);
            $table->json('image_widths');
            $table->unsignedInteger('image_width');
            $table->unsignedInteger('image_height');
            $table->string('alt', 200);
            $table->string('link_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['shop_id', 'position']);
        });

        Schema::table('shops', function (Blueprint $table) {
            // Sekundy między slajdami (3–10). Bez znaczenia przy jednym banerze.
            $table->unsignedTinyInteger('banner_interval')->default(6)->after('contact_form_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('banner_interval');
        });

        Schema::dropIfExists('shop_banners');
    }
};
