<?php

namespace Database\Factories;

use App\Enums\PanelSection;
use App\Models\Shop;
use App\Models\ShopEmployee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopEmployee>
 */
class ShopEmployeeFactory extends Factory
{
    /**
     * Domyślnie: pracownik PRZYJĘTY (ustawił hasło) z dostępem do zamówień.
     *
     * Stan czynny jest domyślny, bo o niego pyta większość testów. Zaproszenie
     * bez odpowiedzi i dostęp odebrany to stany wyjątkowe — mają własne metody,
     * żeby w teście było widać, że chodzi właśnie o nie.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'user_id' => User::factory()->employee(),
            'permissions' => [PanelSection::Orders->value],
            'invited_at' => now()->subDay(),
            'accepted_at' => now(),
            'revoked_at' => null,
        ];
    }

    /**
     * Zaproszenie wysłane, ale nikt go jeszcze nie przyjął — konto istnieje,
     * hasła nie zna nikt.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => null,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
        ]);
    }

    /**
     * @param  list<PanelSection>  $sections
     */
    public function withSections(array $sections): static
    {
        return $this->state(fn (array $attributes) => [
            'permissions' => array_column($sections, 'value'),
        ]);
    }
}
