<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\PhoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ActivationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(User $user, string $token, array $overrides = []): array
    {
        return array_merge([
            'token' => $token,
            'token_email' => $user->email,
            'name' => $user->name,
            'surname' => $user->surname,
            'email' => $user->email,
            'phone' => '+48500600700',
            'password' => 'TajneHaslo123',
            'password_confirmation' => 'TajneHaslo123',
            'terms' => '1',
            'privacy' => '1',
        ], $overrides);
    }

    public function test_activation_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $token = Password::broker('activation')->createToken($user);

        $this->get(route('activation.show', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee($user->name, false);
    }

    public function test_activation_sets_password_updates_data_and_logs_in(): void
    {
        $user = User::factory()->create(['name' => 'Stare', 'email_verified_at' => null]);
        $token = Password::broker('activation')->createToken($user);

        $response = $this->post(route('activation.store'), $this->payload($user, $token, [
            'name' => 'Nowe',
        ]));

        $response->assertRedirect(route('seller.dashboard'));
        $this->assertAuthenticatedAs($user->fresh());

        $fresh = $user->fresh();
        $this->assertSame('Nowe', $fresh->name);
        $this->assertTrue(Hash::check('TajneHaslo123', $fresh->password));
        $this->assertNotNull($fresh->email_verified_at); // znacznik aktywacji
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post(route('activation.store'), $this->payload($user, 'zly-token'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_activation_requires_accepting_both_consents(): void
    {
        $user = User::factory()->create();
        $token = Password::broker('activation')->createToken($user);

        $this->post(route('activation.store'), $this->payload($user, $token, [
            'terms' => '0',
            'privacy' => '0',
        ]))->assertSessionHasErrors(['terms', 'privacy']);

        $this->assertGuest();
    }

    public function test_phone_is_stored_in_country_prefixed_form(): void
    {
        $user = User::factory()->create();
        $token = Password::broker('activation')->createToken($user);

        $this->post(route('activation.store'), $this->payload($user, $token, [
            'phone' => '+48 500 600 700',
        ]))->assertRedirect(route('seller.dashboard'));

        $this->assertSame('48500600700', $user->fresh()->phone);
    }

    public function test_phone_normalization_variants(): void
    {
        $phone = app(PhoneService::class);

        // różne zapisy tego samego numeru → jedna postać kanoniczna 48 + 9 cyfr
        foreach (['+48 500 600 700', '0500600700', '500600700', '48500600700'] as $input) {
            $this->assertSame('48500600700', $phone->normalize($input), "wejście: {$input}");
        }

        $this->assertNull($phone->normalize(null));
        $this->assertNull($phone->normalize(''));
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = Password::broker('activation')->createToken($user);

        $this->post(route('activation.store'), $this->payload($user, $token, [
            'phone' => '12345',
        ]))->assertSessionHasErrors('phone');

        $this->assertGuest();
    }

    public function test_password_must_meet_complexity_rules(): void
    {
        $user = User::factory()->create();
        $token = Password::broker('activation')->createToken($user);

        // brak dużej litery i cyfry, za krótkie
        $this->post(route('activation.store'), $this->payload($user, $token, [
            'password' => 'slabe',
            'password_confirmation' => 'slabe',
        ]))->assertSessionHasErrors('password');

        $this->assertGuest();
    }
}
