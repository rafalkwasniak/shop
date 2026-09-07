<?php

namespace Tests\Feature\Seller;

use App\Enums\PanelSection;
use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\ShopEmployee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Ekran „Pracownicy" (plan-shop-employees, krok 4): zaproszenie, zmiana działów,
 * odebranie i przywrócenie dostępu.
 */
class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function ownerOn(string $package = 'pavilion'): array
    {
        $owner = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $owner->getKey()]);
        $shop->assignPackage($package);
        $shop->save();

        return [$owner, $shop->fresh()];
    }

    /**
     * @return array<string, mixed>
     */
    private function invitation(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sandra',
            'surname' => 'Wysyłkowa',
            'email' => 'sandra@example.com',
            'permissions' => [PanelSection::Orders->value],
        ], $overrides);
    }

    public function test_owner_invites_an_employee(): void
    {
        [$owner, $shop] = $this->ownerOn();

        $this->actingAs($owner)
            ->from(route('seller.employees.index'))
            ->post(route('seller.employees.store'), $this->invitation())
            ->assertRedirect(route('seller.employees.index'));

        $employee = User::where('email', 'sandra@example.com')->firstOrFail();

        $this->assertSame(UserRole::Employee, $employee->role);
        // `currentShop()` jest tu CELOWO puste: zaproszenie bez odpowiedzi nie
        // jest czynnym zatrudnieniem, więc pracownik jeszcze nigdzie nie pracuje.
        // Sklep pojawi się dopiero po ustawieniu hasła (`accepted_at`).
        $this->assertNull($employee->currentShop());
        $this->assertTrue($shop->is($employee->employments()->firstOrFail()->shop));
        $this->assertDatabaseHas('shop_employees', [
            'shop_id' => $shop->getKey(),
            'user_id' => $employee->getKey(),
            'invited_by' => $owner->getKey(),
            'accepted_at' => null,
        ]);
    }

    /**
     * Właściciel NIE ustawia hasła pracownikowi. Gdyby je znał, zapis „kto
     * zmienił status zamówienia" przestałby cokolwiek znaczyć. Konto powstaje z
     * losowym ciągiem, bo kolumna jest NOT NULL — znacznikiem „nie aktywowano"
     * jest brak `email_verified_at`, dokładnie jak u sprzedawcy.
     */
    public function test_invitation_never_sets_a_password_the_owner_knows(): void
    {
        [$owner] = $this->ownerOn();

        $this->actingAs($owner)->post(route('seller.employees.store'), $this->invitation([
            'password' => 'tajne-haslo-wlasciciela',
        ]));

        $employee = User::where('email', 'sandra@example.com')->firstOrFail();

        $this->assertNull($employee->email_verified_at);
        $this->assertFalse($employee->isActivated());
        $this->assertFalse(Hash::check('tajne-haslo-wlasciciela', $employee->password));
    }

    /**
     * Zaproszony, ale nieaktywowany pracownik NIE wchodzi do panelu — konto
     * istnieje tylko po to, żeby link aktywacyjny miał na czym wisieć.
     */
    public function test_invited_employee_cannot_enter_before_accepting(): void
    {
        [$owner] = $this->ownerOn();
        $this->actingAs($owner)->post(route('seller.employees.store'), $this->invitation());

        $employee = User::where('email', 'sandra@example.com')->firstOrFail();

        $this->actingAs($employee)->get(route('seller.orders.index'))->assertForbidden();
    }

    public function test_email_already_taken_is_refused(): void
    {
        [$owner] = $this->ownerOn();
        User::factory()->create(['email' => 'zajety@example.com']);

        $this->actingAs($owner)
            ->from(route('seller.employees.index'))
            ->post(route('seller.employees.store'), $this->invitation(['email' => 'zajety@example.com']))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('shop_employees', 0);
    }

    public function test_employee_without_any_section_is_refused(): void
    {
        [$owner] = $this->ownerOn();

        $this->actingAs($owner)
            ->from(route('seller.employees.index'))
            ->post(route('seller.employees.store'), $this->invitation(['permissions' => []]))
            ->assertSessionHasErrors('permissions');
    }

    /**
     * Limit sprawdzany po stronie serwera, nie tylko w widoku: formularz mógł
     * zostać otwarty, gdy miejsce jeszcze było.
     */
    public function test_package_limit_stops_the_invitation(): void
    {
        [$owner, $shop] = $this->ownerOn();
        ShopEmployee::factory()->count(5)->create(['shop_id' => $shop->getKey()]);

        $this->actingAs($owner)
            ->from(route('seller.employees.index'))
            ->post(route('seller.employees.store'), $this->invitation())
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('users', ['email' => 'sandra@example.com']);
    }

    public function test_owner_changes_the_sections(): void
    {
        [$owner, $shop] = $this->ownerOn();
        $employment = ShopEmployee::factory()
            ->withSections([PanelSection::Orders])
            ->create(['shop_id' => $shop->getKey()]);

        $this->actingAs($owner)->post(route('seller.employees.update', $employment), [
            'permissions' => [PanelSection::Products->value, PanelSection::Customers->value],
        ])->assertRedirect();

        $employment->refresh();
        $this->assertTrue($employment->allows(PanelSection::Products));
        $this->assertFalse($employment->allows(PanelSection::Orders));
    }

    public function test_revoking_and_restoring_access(): void
    {
        [$owner, $shop] = $this->ownerOn();
        $employment = ShopEmployee::factory()->create(['shop_id' => $shop->getKey()]);

        $this->actingAs($owner)->post(route('seller.employees.revoke', $employment))->assertRedirect();
        $this->assertFalse($employment->fresh()->isActive());
        // Wiersz zostaje — to jedyny ślad, kto miał dostęp do danych klientów.
        $this->assertDatabaseHas('shop_employees', ['id' => $employment->getKey()]);

        $this->actingAs($owner)->post(route('seller.employees.restore', $employment))->assertRedirect();
        $this->assertTrue($employment->fresh()->isActive());
    }

    /**
     * Cudzy pracownik dla tego sprzedawcy nie istnieje — 404, nie 403, żeby nie
     * potwierdzać, że taki wiersz jest gdzie indziej.
     */
    public function test_owner_cannot_touch_another_shops_employee(): void
    {
        [$owner] = $this->ownerOn();
        $foreign = ShopEmployee::factory()->create();

        $this->actingAs($owner)->post(route('seller.employees.revoke', $foreign))->assertNotFound();
        $this->actingAs($owner)->post(route('seller.employees.update', $foreign), [
            'permissions' => [PanelSection::Products->value],
        ])->assertNotFound();
    }

    /**
     * Zarządzanie ludźmi nie jest działem, który da się komuś oddać. Pracownik
     * z KOMPLETEM uprawnień nadal nie zaprosi kolejnego ani nie odbierze
     * nikomu dostępu.
     */
    public function test_employee_cannot_manage_employees(): void
    {
        [, $shop] = $this->ownerOn();
        $employment = ShopEmployee::factory()
            ->withSections(PanelSection::cases())
            ->create(['shop_id' => $shop->getKey()]);

        $employee = $employment->user->fresh();

        $this->actingAs($employee)->get(route('seller.employees.index'))->assertForbidden();
        $this->actingAs($employee)->post(route('seller.employees.store'), $this->invitation())->assertForbidden();
        $this->actingAs($employee)->post(route('seller.employees.revoke', $employment))->assertForbidden();
    }

    public function test_free_package_sees_the_upsell_instead_of_the_tool(): void
    {
        [$owner] = $this->ownerOn('stall');

        $this->actingAs($owner)->get(route('seller.employees.index'))
            ->assertOk()
            ->assertSee('Konta pracowników')
            ->assertDontSee('Dodaj pracownika');
    }

    /**
     * Objaśnienie „Jak to działa" stoi POZA bramą pakietu — ten sam wzorzec co
     * na Kodach rabatowych. Sprzedawca na darmowym pakiecie ma się dowiedzieć,
     * co ta funkcja robi i czego NIE oddaje, zanim zdecyduje o dopłacie. Sama
     * zachęta bez wyjaśnienia mówi mu wyłącznie „nie masz".
     */
    public function test_locked_screen_still_explains_the_feature(): void
    {
        [$owner] = $this->ownerOn('stall');

        $this->actingAs($owner)->get(route('seller.employees.index'))
            ->assertOk()
            ->assertSee('Jak to działa')
            ->assertSee('sam ustawia swoje hasło');
    }

    public function test_free_package_cannot_invite_through_the_endpoint(): void
    {
        [$owner] = $this->ownerOn('stall');

        $this->actingAs($owner)->post(route('seller.employees.store'), $this->invitation())->assertForbidden();
        $this->assertDatabaseCount('shop_employees', 0);
    }

    public function test_list_shows_the_team_and_their_state(): void
    {
        [$owner, $shop] = $this->ownerOn();
        ShopEmployee::factory()->create([
            'shop_id' => $shop->getKey(),
            'user_id' => User::factory()->employee()->create(['name' => 'Krysia', 'surname' => 'Rozliczeniowa'])->getKey(),
        ]);
        ShopEmployee::factory()->pending()->create([
            'shop_id' => $shop->getKey(),
            'user_id' => User::factory()->employee()->create(['name' => 'Michał', 'surname' => 'Magazynowy'])->getKey(),
        ]);

        $this->actingAs($owner)->get(route('seller.employees.index'))
            ->assertOk()
            ->assertSee('Krysia')
            ->assertSee('Michał')
            ->assertSee('Aktywny')
            ->assertSee('nie ustawił hasła');
    }

    /**
     * Konwencja panelu: dodawanie na OSOBNEJ STRONIE, tak jak wiadomości, kody
     * rabatowe i produkty. Lista prowadzi do formularza przyciskiem, a nie ma
     * go wciśniętego w boczną kolumnę.
     */
    public function test_list_links_to_a_separate_form_page(): void
    {
        [$owner] = $this->ownerOn();

        $this->actingAs($owner)->get(route('seller.employees.index'))
            ->assertOk()
            ->assertSee(route('seller.employees.create'), false);

        $this->actingAs($owner)->get(route('seller.employees.create'))
            ->assertOk()
            ->assertSee('Nowy pracownik')
            ->assertSee('Adres e-mail');
    }

    public function test_edit_page_shows_current_sections(): void
    {
        [$owner, $shop] = $this->ownerOn();
        $employment = ShopEmployee::factory()
            ->withSections([PanelSection::Orders])
            ->create(['shop_id' => $shop->getKey()]);

        $this->actingAs($owner)->get(route('seller.employees.edit', $employment))
            ->assertOk()
            ->assertSee($employment->user->email)
            // Tożsamości na tym ekranie się NIE edytuje — pól nie ma.
            ->assertDontSee('name="email"', false)
            ->assertSee('value="orders"', false);
    }

    public function test_edit_page_refuses_another_shops_employee(): void
    {
        [$owner] = $this->ownerOn();

        $this->actingAs($owner)
            ->get(route('seller.employees.edit', ShopEmployee::factory()->create()))
            ->assertNotFound();
    }

    public function test_form_page_is_closed_without_the_package(): void
    {
        [$owner] = $this->ownerOn('stall');

        $this->actingAs($owner)->get(route('seller.employees.create'))->assertForbidden();
    }

    /**
     * Formularz bez wolnych miejsc odsyłamy na listę, zamiast pokazywać ekran,
     * który przy zapisie i tak odmówi.
     */
    public function test_form_page_redirects_when_there_are_no_seats(): void
    {
        [$owner, $shop] = $this->ownerOn();
        ShopEmployee::factory()->count(5)->create(['shop_id' => $shop->getKey()]);

        $this->actingAs($owner)->get(route('seller.employees.create'))
            ->assertRedirect(route('seller.employees.index'))
            ->assertSessionHas('error');
    }
}
