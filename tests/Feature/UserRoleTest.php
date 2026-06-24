<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_a_seller_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Seller, $user->role);
        $this->assertTrue($user->isSeller());
        $this->assertFalse($user->isAdmin());
    }

    public function test_admin_state_creates_an_admin(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->isSeller());
    }

    public function test_role_is_not_mass_assignable(): void
    {
        $this->assertNotContains('role', (new User)->getFillable());
    }

    public function test_profile_fields_are_stored(): void
    {
        User::factory()->create([
            'name' => 'Anna',
            'surname' => 'Kowalska',
            'phone' => '+48111222333',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Anna',
            'surname' => 'Kowalska',
            'phone' => '+48111222333',
        ]);
    }
}
