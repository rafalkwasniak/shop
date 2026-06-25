<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_admin_is_authenticated_and_redirected_to_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('administrator.dashboard'));
    }

    public function test_seller_is_authenticated_and_redirected_to_seller_dashboard(): void
    {
        $seller = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $seller->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($seller);
        $response->assertRedirect(route('seller.dashboard'));
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_already_authenticated_user_visiting_login_is_redirected_home(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/login')
            ->assertRedirect(route('administrator.dashboard'));
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
