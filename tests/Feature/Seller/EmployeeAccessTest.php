<?php

namespace Tests\Feature\Seller;

use App\Enums\PanelSection;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopEmployee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bramy panelu dla pracowników (plan-shop-employees, krok 3).
 *
 * Trzy pytania, na które ten plik odpowiada: czy pracownik wchodzi tam, gdzie
 * ma; czy odbija się od reszty; i czy menu pokazuje dokładnie to, co przepuszcza
 * brama. Rozjazd między menu a bramą nie jest kosmetyką — pozycja prowadząca
 * w 403 wygląda jak awaria.
 */
class EmployeeAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<PanelSection>  $sections
     * @return array{0: User, 1: Shop}
     */
    private function employeeWith(array $sections): array
    {
        $shop = Shop::factory()->create();
        $shop->assignPackage('pavilion');
        $shop->save();

        $employment = ShopEmployee::factory()
            ->withSections($sections)
            ->create(['shop_id' => $shop->getKey()]);

        return [$employment->user->fresh(), $shop->fresh()];
    }

    public function test_employee_enters_the_section_they_were_given(): void
    {
        [$employee] = $this->employeeWith([PanelSection::Products]);

        $this->actingAs($employee)->get(route('seller.products.index'))->assertOk();
    }

    public function test_employee_is_refused_a_section_they_were_not_given(): void
    {
        [$employee] = $this->employeeWith([PanelSection::Products]);

        $this->actingAs($employee)->get(route('seller.orders.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('seller.customers.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('seller.discounts.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('seller.pages.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('seller.analytics.index'))->assertForbidden();
    }

    /**
     * Rzeczy, których sprzedawca NIE oddaje razem z działem. Nie ma dla nich
     * checkboxa, więc pracownik z KOMPLETEM uprawnień i tak ma się odbić —
     * inaczej „pełny dostęp" po cichu znaczyłoby „również moje pieniądze".
     */
    public function test_owner_only_screens_refuse_even_a_fully_privileged_employee(): void
    {
        [$employee] = $this->employeeWith(PanelSection::cases());

        $this->actingAs($employee)->get(route('seller.package.show'))->assertForbidden();
        $this->actingAs($employee)->get(route('seller.integrations.edit'))->assertForbidden();
        $this->actingAs($employee)->get(route('seller.settings.edit'))->assertForbidden();
        $this->actingAs($employee)->get(route('seller.shop.edit'))->assertForbidden();
        $this->actingAs($employee)->get(route('seller.deletion.show'))->assertForbidden();
    }

    public function test_pulpit_is_open_to_every_employee(): void
    {
        [$employee] = $this->employeeWith([PanelSection::Analytics]);

        $this->actingAs($employee)->get(route('seller.dashboard'))->assertOk();
    }

    /**
     * Odebranie dostępu działa przy następnym kliknięciu — middleware czyta
     * członkostwo z bazy, nie z sesji. Bez tego zwolniony pracownik pracowałby
     * do wygaśnięcia ciasteczka.
     */
    public function test_revoking_shuts_the_door_immediately(): void
    {
        [$employee, $shop] = $this->employeeWith([PanelSection::Products]);

        $this->actingAs($employee)->get(route('seller.products.index'))->assertOk();

        $shop->employees()->first()->update(['revoked_at' => now()]);

        $this->actingAs($employee->fresh())->get(route('seller.products.index'))->assertForbidden();
    }

    public function test_menu_shows_exactly_what_the_gate_lets_through(): void
    {
        [$employee] = $this->employeeWith([PanelSection::Orders]);

        $response = $this->actingAs($employee)->get(route('seller.dashboard'))->assertOk();

        $response->assertSee(route('seller.orders.index'), false);
        // Działy bez uprawnienia i rzeczy właścicielskie znikają z menu.
        $response->assertDontSee(route('seller.products.index'), false);
        $response->assertDontSee(route('seller.integrations.edit'), false);
        $response->assertDontSee(route('seller.package.show'), false);
    }

    public function test_owner_still_sees_and_reaches_everything(): void
    {
        $owner = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $owner->getKey()]);
        $shop->assignPackage('pavilion');
        $shop->save();

        $this->actingAs($owner)->get(route('seller.products.index'))->assertOk();
        $this->actingAs($owner)->get(route('seller.orders.index'))->assertOk();
        $this->actingAs($owner)->get(route('seller.integrations.edit'))->assertOk();
        $this->actingAs($owner)->get(route('seller.package.show'))->assertOk();
    }

    /**
     * Pracownik nie jest stroną umowy z Kramio, więc brama zgód go nie dotyczy.
     * Gdyby dotyczyła, pierwsze logowanie kończyłoby się pętlą na ekranie
     * dokumentów, których nie ma prawa zaakceptować w cudzym imieniu.
     */
    public function test_employee_is_not_stopped_by_the_consent_gate(): void
    {
        [$employee] = $this->employeeWith([PanelSection::Products]);

        $this->assertTrue($employee->outstandingConsents()->isEmpty());
        $this->actingAs($employee)->get(route('seller.products.index'))->assertOk();
    }

    /**
     * Pracownik widzi sklep PRACODAWCY, a nie pustkę — to jest cały sens
     * `currentShop()` z kroku 1. Bez tego lista produktów byłaby pusta mimo
     * nadanego działu, a błąd wyglądałby na „brak danych", nie na brak dostępu.
     */
    public function test_employee_sees_the_employers_data(): void
    {
        [$employee, $shop] = $this->employeeWith([PanelSection::Products]);
        Product::factory()->create(['shop_id' => $shop->getKey(), 'name' => 'Kubek z kotem']);

        $this->actingAs($employee)->get(route('seller.products.index'))
            ->assertOk()
            ->assertSee('Kubek z kotem');
    }
}
