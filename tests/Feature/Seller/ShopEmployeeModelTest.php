<?php

namespace Tests\Feature\Seller;

use App\Enums\PanelSection;
use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\ShopEmployee;
use App\Models\User;
use App\Support\PackageFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Warstwa danych pracowników (plan-shop-employees, krok 2). Bramy w panelu
 * dochodzą w kroku 3 — tutaj sprawdzamy wyłącznie to, kto gdzie pracuje i do
 * czego ma dostęp.
 */
class ShopEmployeeModelTest extends TestCase
{
    use RefreshDatabase;

    private function shopOnPavilion(): Shop
    {
        $shop = Shop::factory()->create();
        $shop->assignPackage('pavilion');
        $shop->save();

        return $shop->fresh();
    }

    public function test_employee_works_in_the_shop_that_hired_them(): void
    {
        $shop = $this->shopOnPavilion();
        $employment = ShopEmployee::factory()->create(['shop_id' => $shop->getKey()]);

        $employee = $employment->user->fresh();

        $this->assertSame(UserRole::Employee, $employee->role);
        $this->assertTrue($shop->is($employee->currentShop()));
    }

    /**
     * Sedno rozdziału z kroku 1: pracownik PRACUJE w sklepie, ale nie jest jego
     * właścicielem. Gdyby `shop()` cokolwiek tu zwracało, wszystkie miejsca
     * pytające o własność (zakup pakietu, kasowanie sklepu, rozliczenia)
     * uznałyby go za sprzedawcę.
     */
    public function test_employee_owns_nothing(): void
    {
        $employment = ShopEmployee::factory()->create();
        $employee = $employment->user->fresh();

        $this->assertNull($employee->shop);
        $this->assertFalse($employee->isSeller());
        $this->assertTrue($employee->isEmployee());
    }

    public function test_pending_invitation_gives_no_access(): void
    {
        $employment = ShopEmployee::factory()->pending()->create();

        $this->assertFalse($employment->isActive());
        $this->assertFalse($employment->allows(PanelSection::Orders));
        $this->assertNull($employment->user->fresh()->currentShop());
    }

    public function test_revoked_employment_gives_no_access(): void
    {
        $employment = ShopEmployee::factory()->revoked()->create();

        $this->assertFalse($employment->isActive());
        $this->assertFalse($employment->allows(PanelSection::Orders));
        $this->assertNull($employment->user->fresh()->currentShop());
    }

    /**
     * Odebranie dostępu nie może skasować wiersza — to jedyny ślad, kto i kiedy
     * miał dostęp do danych osobowych klientów sklepu.
     */
    public function test_revoking_keeps_the_record(): void
    {
        $employment = ShopEmployee::factory()->revoked()->create();

        $this->assertDatabaseHas('shop_employees', ['id' => $employment->getKey()]);
    }

    public function test_permissions_are_limited_to_granted_sections(): void
    {
        $employment = ShopEmployee::factory()
            ->withSections([PanelSection::Products])
            ->create();

        $this->assertTrue($employment->allows(PanelSection::Products));
        $this->assertFalse($employment->allows(PanelSection::Orders));
        $this->assertFalse($employment->allows(PanelSection::Customers));
    }

    /**
     * Dział usunięty z kodu zostaje w `permissions` starych wierszy.
     * `PanelSection::from()` rzuciłby na nim wyjątkiem przy renderowaniu listy
     * pracowników — czyli funkcja padłaby na danych, nie na błędzie logiki.
     */
    public function test_unknown_section_key_is_ignored(): void
    {
        $employment = ShopEmployee::factory()->create([
            'permissions' => ['orders', 'dzial-ktorego-juz-nie-ma'],
        ]);

        $this->assertSame([PanelSection::Orders], $employment->sections());
    }

    public function test_package_decides_whether_employees_exist_at_all(): void
    {
        $free = Shop::factory()->create();
        $free->assignPackage('stall');
        $free->save();

        $this->assertFalse($free->fresh()->allowsEmployees());
        $this->assertTrue($this->shopOnPavilion()->allowsEmployees());
    }

    /**
     * Zaproszenia bez odpowiedzi ZAJMUJĄ miejsca. Inaczej limit da się obejść:
     * zapraszasz dwadzieścia osób i czekasz, aż któraś kliknie.
     */
    public function test_pending_invitations_take_up_slots(): void
    {
        $shop = $this->shopOnPavilion();

        ShopEmployee::factory()->count(2)->create(['shop_id' => $shop->getKey()]);
        ShopEmployee::factory()->pending()->create(['shop_id' => $shop->getKey()]);

        $this->assertSame(2, $shop->fresh()->employeeSlotsLeft());
    }

    public function test_revoked_employment_frees_a_slot(): void
    {
        $shop = $this->shopOnPavilion();

        ShopEmployee::factory()->count(5)->create(['shop_id' => $shop->getKey()]);
        $this->assertSame(0, $shop->fresh()->employeeSlotsLeft());

        $shop->employees()->first()->update(['revoked_at' => now()]);
        $this->assertSame(1, $shop->fresh()->employeeSlotsLeft());
    }

    /**
     * Ekran zablokowany funkcją musi umieć nazwać pakiet, który ją odblokowuje.
     * Uprawnienie liczbowe (`max_employees => 5`) nie jest `true`, więc dawna
     * wersja `cheapestWith()` odpowiadałaby „nie ma tego nigdzie".
     */
    public function test_cheapest_package_with_employees_is_found(): void
    {
        $cheapest = PackageFeatures::cheapestWith('max_employees');

        $this->assertNotNull($cheapest);
        $this->assertSame('pavilion', $cheapest['key']);
    }

    public function test_owner_of_a_shop_is_unaffected(): void
    {
        $owner = User::factory()->create();
        $shop = Shop::factory()->for($owner, 'owner')->create();

        $this->assertTrue($shop->is($owner->currentShop()));
        $this->assertTrue($shop->is($owner->shop));
    }
}
