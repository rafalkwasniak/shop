<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Logowanie dwuetapowe do centrali (admin, sprzedawca, pracownik): po haśle
 * mailem idzie 6-cyfrowy kod. Wiersz = jedna rozpoczęta próba logowania.
 *
 * W bazie, nie w sesji: komenda awaryjna `auth:login-code` musi umieć wystawić
 * kod dla próby, która czeka w cudzej przeglądarce — a do sesji z konsoli nie
 * ma dostępu. Trzymamy tylko HASH kodu, nigdy sam kod.
 *
 * Ostatnia część zeruje `remember_token` wszystkim kontom centrali. Ciasteczka
 * „Zapamiętaj mnie" wydane przed 2FA żyły latami i logowałyby z pominięciem
 * kodu. Po zerowaniu każdy przejdzie raz przez kod; otwarte sesje działają dalej.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        DB::table('users')->update(['remember_token' => null]);
    }

    public function down(): void
    {
        Schema::dropIfExists('login_challenges');
    }
};
