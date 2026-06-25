<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_an_administrator(): void
    {
        $this->artisan('shop:create-admin', [
            '--name' => 'Rafał',
            '--surname' => 'Kwaśniak',
            '--email' => 'admin@shop.test',
            '--password' => 'sekretne-haslo',
        ])->assertSuccessful();

        $user = User::where('email', 'admin@shop.test')->firstOrFail();

        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue($user->isAdmin());
        $this->assertTrue(Hash::check('sekretne-haslo', $user->password));
    }

    public function test_command_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'admin@shop.test']);

        $this->artisan('shop:create-admin', [
            '--name' => 'Rafał',
            '--surname' => 'Kwaśniak',
            '--email' => 'admin@shop.test',
            '--password' => 'sekretne-haslo',
        ])->assertFailed();
    }

    public function test_command_rejects_short_password(): void
    {
        $this->artisan('shop:create-admin', [
            '--name' => 'Rafał',
            '--surname' => 'Kwaśniak',
            '--email' => 'admin@shop.test',
            '--password' => 'krotkie',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'admin@shop.test']);
    }
}
