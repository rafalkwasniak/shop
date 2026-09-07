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
     * Hash hasła liczony RAZ na cały przebieg testów. Bcrypt jest z założenia
     * wolny, więc hashowanie per użytkownik potrafiłoby zdominować czas suity.
     */
    protected static ?string $password;

    /**
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

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * Pracownik cudzego sklepu. Sam w sobie nie daje dostępu do niczego —
     * dostęp niesie członkostwo (ShopEmployeeFactory), nie rola.
     */
    public function employee(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Employee,
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
