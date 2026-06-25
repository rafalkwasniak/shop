<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/administrator/dashboard')->assertRedirect(route('login'));
        $this->get('/seller/dashboard')->assertRedirect(route('login'));
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/administrator/dashboard')->assertOk();
    }

    public function test_seller_can_access_seller_dashboard(): void
    {
        $seller = User::factory()->create();

        $this->actingAs($seller)->get('/seller/dashboard')->assertOk();
    }

    public function test_seller_cannot_access_admin_dashboard(): void
    {
        $seller = User::factory()->create();

        $this->actingAs($seller)->get('/administrator/dashboard')->assertForbidden();
    }

    public function test_admin_cannot_access_seller_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/seller/dashboard')->assertForbidden();
    }
}
