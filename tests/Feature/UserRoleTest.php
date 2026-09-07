<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Shop;
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

    public function test_current_shop_is_the_owned_shop(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->for($user, 'owner')->create();

        $this->assertTrue($shop->is($user->currentShop()));
    }

    public function test_current_shop_is_null_without_a_shop(): void
    {
        $this->assertNull(User::factory()->create()->currentShop());
    }

    /**
     * Panel sprzedawcy pyta o miejsce pracy (`currentShop()`), a nie o własność
     * (`shop()`). Dziś odpowiedź jest ta sama, więc podmiana z powrotem na
     * `shop()` przeszłaby przez całą suitę bez jednego czerwonego testu — i
     * cofnęłaby przygotowanie pod pracowników, którzy pracują w cudzym sklepie.
     *
     * Ten test pilnuje samego rozróżnienia, nie zachowania: dopóki obie metody
     * istnieją, wiadomo, o co pytać. Miejsca własnościowe (zakładanie sklepu w
     * rejestracji, lista sprzedawców u admina) zostają przy `shop()`.
     */
    public function test_ownership_and_workplace_are_separate_questions(): void
    {
        $this->assertTrue(method_exists(User::class, 'shop'));
        $this->assertTrue(method_exists(User::class, 'currentShop'));
    }
}
