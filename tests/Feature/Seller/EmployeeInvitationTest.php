<?php

namespace Tests\Feature\Seller;

use App\Enums\PanelSection;
use App\Models\EmailMessage;
use App\Models\Shop;
use App\Models\ShopEmployee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Zaproszenie pracownika od maila do wejścia do panelu (plan-shop-employees,
 * krok 5). Token brokera `invitation`, ważny 7 dni.
 */
class EmployeeInvitationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function ownerOn(string $package = 'pavilion'): array
    {
        $owner = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $owner->getKey(), 'name' => 'Domowe Lemoniady']);
        $shop->assignPackage($package);
        $shop->save();

        return [$owner, $shop->fresh()];
    }

    private function invite(User $owner): EmailMessage
    {
        $this->actingAs($owner)->post(route('seller.employees.store'), [
            'name' => 'Sandra',
            'surname' => 'Wysyłkowa',
            'email' => 'sandra@example.com',
            'permissions' => [PanelSection::Orders->value],
        ]);

        // Zaprasza WŁAŚCICIEL, więc po tym żądaniu zostaje zalogowany. Zaproszenie
        // otwiera się z maila, czyli z pozycji gościa — bez wylogowania kolejne
        // asercje mówiłyby o właścicielu, a nie o zapraszanej osobie.
        Auth::logout();

        return EmailMessage::where('to_email', 'sandra@example.com')->latest('id')->firstOrFail();
    }

    public function test_invitation_lands_in_the_outbox_with_a_working_link(): void
    {
        [$owner, $shop] = $this->ownerOn();

        $mail = $this->invite($owner);

        // Branding SKLEPU, nie platformy: pracownik zna sprzedawcę, nie Kramio.
        $this->assertSame($shop->getKey(), $mail->shop_id);
        $this->assertStringContainsString('Domowe Lemoniady', $mail->subject);
        // Zaproszony ma wiedzieć, do czego dostaje dostęp, ZANIM kliknie.
        $this->assertStringContainsString('Zamówienia', json_encode($mail->intro_lines, JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('/zaproszenie/', $mail->action_url);

        $this->get($mail->action_url)->assertOk()->assertSee('Ustaw swoje hasło');
    }

    public function test_employee_sets_a_password_and_lands_in_the_panel(): void
    {
        [$owner, $shop] = $this->ownerOn();
        $mail = $this->invite($owner);

        $this->post(route('employee.invitation.store'), [
            'token' => $this->tokenFrom($mail->action_url),
            'token_email' => 'sandra@example.com',
            'password' => 'Haslo123456',
            'password_confirmation' => 'Haslo123456',
        ])->assertRedirect(route('seller.dashboard'));

        $employee = User::where('email', 'sandra@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('Haslo123456', $employee->password));
        $this->assertTrue($employee->isActivated());
        // Zalogowany jest PRACOWNIK, nie zapraszający.
        $this->assertSame($employee->getKey(), Auth::id());

        // DOPIERO TERAZ zatrudnienie jest czynne i sklep się otwiera.
        $employment = $employee->employments()->firstOrFail();
        $this->assertNotNull($employment->accepted_at);
        $this->assertTrue($shop->is($employee->fresh()->currentShop()));
    }

    public function test_employee_can_work_right_after_accepting(): void
    {
        [$owner] = $this->ownerOn();
        $mail = $this->invite($owner);

        $this->post(route('employee.invitation.store'), [
            'token' => $this->tokenFrom($mail->action_url),
            'token_email' => 'sandra@example.com',
            'password' => 'Haslo123456',
            'password_confirmation' => 'Haslo123456',
        ]);

        $this->get(route('seller.orders.index'))->assertOk();
        // Dział, którego nie dostała, dalej jest zamknięty.
        $this->get(route('seller.products.index'))->assertForbidden();
    }

    /**
     * Sedno bezpieczeństwa tej ścieżki: token brokera żyje 7 dni niezależnie od
     * nas, więc odebranie dostępu MUSI unieważniać zaproszenie. Bez tego dawny
     * mail byłby wejściem do sklepu długo po zwolnieniu.
     */
    public function test_revoked_invitation_stops_working(): void
    {
        [$owner, $shop] = $this->ownerOn();
        $mail = $this->invite($owner);

        $shop->employees()->firstOrFail()->update(['revoked_at' => now()]);

        $this->post(route('employee.invitation.store'), [
            'token' => $this->tokenFrom($mail->action_url),
            'token_email' => 'sandra@example.com',
            'password' => 'Haslo123456',
            'password_confirmation' => 'Haslo123456',
        ])->assertSessionHasErrors('token');

        $this->assertFalse(Auth::check());
        $this->assertFalse(User::where('email', 'sandra@example.com')->firstOrFail()->isActivated());
    }

    /**
     * Link jednorazowy w praktyce: po przyjęciu ten sam adres nie może służyć
     * jako „ustaw nowe hasło" dla kogoś, kto ma dostęp do starej skrzynki.
     */
    public function test_used_invitation_cannot_be_used_again(): void
    {
        [$owner] = $this->ownerOn();
        $mail = $this->invite($owner);
        $token = $this->tokenFrom($mail->action_url);

        $this->post(route('employee.invitation.store'), [
            'token' => $token,
            'token_email' => 'sandra@example.com',
            'password' => 'Haslo123456',
            'password_confirmation' => 'Haslo123456',
        ]);

        $this->post(route('employee.invitation.store'), [
            'token' => $token,
            'token_email' => 'sandra@example.com',
            'password' => 'InneHaslo123',
            'password_confirmation' => 'InneHaslo123',
        ])->assertSessionHasErrors('token');

        $this->assertTrue(Hash::check('Haslo123456',
            User::where('email', 'sandra@example.com')->firstOrFail()->password));
    }

    public function test_expired_token_is_refused(): void
    {
        [$owner] = $this->ownerOn();
        $mail = $this->invite($owner);

        // Ósmy dzień — link ważny 7.
        $this->travel(8)->days();

        $this->post(route('employee.invitation.store'), [
            'token' => $this->tokenFrom($mail->action_url),
            'token_email' => 'sandra@example.com',
            'password' => 'Haslo123456',
            'password_confirmation' => 'Haslo123456',
        ])->assertSessionHasErrors('token');
    }

    public function test_token_is_still_valid_on_the_sixth_day(): void
    {
        [$owner] = $this->ownerOn();
        $mail = $this->invite($owner);

        $this->travel(6)->days();

        $this->post(route('employee.invitation.store'), [
            'token' => $this->tokenFrom($mail->action_url),
            'token_email' => 'sandra@example.com',
            'password' => 'Haslo123456',
            'password_confirmation' => 'Haslo123456',
        ])->assertRedirect(route('seller.dashboard'));
    }

    public function test_stale_link_shows_a_plain_explanation_instead_of_a_form(): void
    {
        [$owner, $shop] = $this->ownerOn();
        $mail = $this->invite($owner);
        $shop->employees()->firstOrFail()->update(['revoked_at' => now()]);

        $this->get($mail->action_url)
            ->assertOk()
            ->assertSee('Zaproszenie nieaktualne')
            ->assertDontSee('Ustaw swoje hasło');
    }

    public function test_owner_resends_a_pending_invitation(): void
    {
        [$owner, $shop] = $this->ownerOn();
        $this->invite($owner);
        $employment = $shop->employees()->firstOrFail();

        $this->actingAs($owner)->post(route('seller.employees.resend', $employment))->assertRedirect();

        $this->assertSame(2, EmailMessage::where('to_email', 'sandra@example.com')->count());
    }

    /**
     * Osobie, która już ustawiła hasło, nowy link byłby drogą do przejęcia jej
     * konta przez każdego, kto ma dostęp do jej skrzynki.
     */
    public function test_accepted_invitation_is_not_resent(): void
    {
        [$owner, $shop] = $this->ownerOn();
        $employment = ShopEmployee::factory()->create(['shop_id' => $shop->getKey()]);

        $this->actingAs($owner)
            ->from(route('seller.employees.index'))
            ->post(route('seller.employees.resend', $employment))
            ->assertSessionHas('error');

        $this->assertSame(0, EmailMessage::count());
    }

    /**
     * Po aktywacji pracownik loguje się zwykłym formularzem, jak każdy inny —
     * ta ścieżka nie ma osobnego wejścia i mieć nie powinna. Rola kieruje go na
     * pulpit sprzedawcy, bo panel jest wspólny.
     */
    public function test_employee_logs_in_normally_afterwards(): void
    {
        [$owner] = $this->ownerOn();
        $mail = $this->invite($owner);

        $this->post(route('employee.invitation.store'), [
            'token' => $this->tokenFrom($mail->action_url),
            'token_email' => 'sandra@example.com',
            'password' => 'Haslo123456',
            'password_confirmation' => 'Haslo123456',
        ]);
        Auth::logout();

        $this->post(route('login'), [
            'email' => 'sandra@example.com',
            'password' => 'Haslo123456',
        ])->assertRedirect(route('seller.dashboard'));

        $this->get(route('seller.orders.index'))->assertOk();
    }

    private function tokenFrom(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';

        return basename($path);
    }
}
