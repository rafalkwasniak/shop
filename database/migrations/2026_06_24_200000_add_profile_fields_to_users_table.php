<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Platform users are admins and sellers. `name` holds the first name (Laravel
// default), `surname` the last name. Phone and surname are nullable here and
// enforced by validation at the step that requires them (e.g. shop activation).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('surname')->nullable()->after('name');
            $table->string('phone')->nullable()->after('surname');
            $table->string('role')->default('seller')->after('phone')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['surname', 'phone', 'role']);
        });
    }
};
