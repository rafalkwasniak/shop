<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kiedy klient dostał przypomnienie, że ta porcja punktów wkrótce wygaśnie.
 * Znacznik na porcji, nie na kliencie: kolejna porcja z późniejszym terminem
 * dostanie własne przypomnienie, a ta sama — nigdy drugiego.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_entries', function (Blueprint $table) {
            $table->timestamp('expiry_notified_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_entries', function (Blueprint $table) {
            $table->dropColumn('expiry_notified_at');
        });
    }
};
