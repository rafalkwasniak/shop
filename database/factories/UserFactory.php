<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+48#########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Seller,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is a platform administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * Akceptacja aktualnych wersji wszystkich wymaganych dokumentów prawnych —
     * sprzedawca, który przeszedł rejestrację i nie jest blokowany bramą zgód.
     */
    public function consented(): static
    {
        return $this->afterCreating(function (User $user): void {
            foreach (config('legal.required_types') as $type) {
                $document = LegalDocument::current($type);

                if ($document === null) {
                    continue;
                }

                $user->consents()->create([
                    'legal_document_id' => $document->getKey(),
                    'accepted_at' => now(),
                ]);
            }
        });
    }
}
