<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Znacznik stron zakładanych przez system obok Regulaminu — na razie jednej:
 * „Zasady punktów" (`loyalty_rules`).
 *
 * Świadomie NIE używamy `is_system`: w kodzie ta flaga znaczy „to jest
 * Regulamin" (kreator wzoru, link w kasie, stały tytuł) i druga strona z nią
 * pomyliłaby się z regulaminem w każdym z tych miejsc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('system_key', 30)->nullable()->after('is_system');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('system_key');
        });
    }
};
