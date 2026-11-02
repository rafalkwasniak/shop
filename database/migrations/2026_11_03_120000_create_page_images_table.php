<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zdjęcia wstawione w treść stron „Informacje". Rekord = jeden plik WebP
 * w `shops/{shop}/pages/{uuid}.webp`; w treści strony siedzi jego adres.
 *
 * Przypięte do SKLEPU, nie do strony: limit z pakietu liczymy na cały sklep,
 * a zdjęcie wgrywa się, zanim nowa strona w ogóle istnieje. Które zdjęcia są
 * w użyciu, mówi treść stron — nieużywane sprząta PageImageService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_images');
    }
};
