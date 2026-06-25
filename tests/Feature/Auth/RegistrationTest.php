<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_new_users_register_as_sellers_and_are_logged_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'Anna',
            'surname' => 'Nowak',
            'email' => 'anna@sklep.test',
            'password' => 'tajne-haslo-123',
            'password_confirmation' => 'tajne-haslo-123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('seller.dashboard'));

        $user = User::where('email', 'anna@sklep.test')->firstOrFail();
        $this->assertSame(UserRole::Seller, $user->role);
        $this->assertSame('Anna', $user->name);
        $this->assertSame('Nowak', $user->surname);
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $this->post('/register', [
            'name' => 'Anna',
            'surname' => 'Nowak',
            'email' => 'anna@sklep.test',
            'password' => 'tajne-haslo-123',
            'password_confirmation' => 'inne-haslo-456',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'anna@sklep.test']);
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'anna@sklep.test']);

        $this->post('/register', [
            'name' => 'Anna',
            'surname' => 'Nowak',
            'email' => 'anna@sklep.test',
            'password' => 'tajne-haslo-123',
            'password_confirmation' => 'tajne-haslo-123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_visiting_register_is_redirected_home(): void
    {
        $seller = User::factory()->create();

        $this->actingAs($seller)
            ->get('/register')
            ->assertRedirect(route('seller.dashboard'));
    }
}
