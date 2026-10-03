<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use App\Models\EmailMessage;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Odzyskiwanie hasła — dla sprzedawcy w centrali i dla klienta w sklepie.
 *
 * Do 02.08.2026 tej funkcji NIE BYŁO w ogóle: istniała wyłącznie jednorazowa
 * aktywacja konta, więc kto zgubił hasło, nie miał jak wrócić.
 *
 * Testy pilnują trzech rzeczy, na których ten mechanizm zwykle się wykłada:
 *  - formularz nie może zdradzać, CZY konto o danym adresie istnieje;
 *  - konto nieaktywowane musi dostać aktywację, nie reset;
 *  - link klienta musi być zamknięty w jego sklepie, bo ten sam e-mail bywa
 *    kontem u wielu sprzedawców.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    // ---------------------------------------------------------------- centrala

    public function test_seller_receives_a_link_to_set_a_new_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('stare-haslo')]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors();

        $mail = EmailMessage::latest('id')->first();

        $this->assertNotNull($mail);
        $this->assertSame($user->email, $mail->to_email);
        $this->assertStringContainsString('nowe-haslo', (string) $mail->action_url);
    }

    public function test_unknown_address_gets_the_same_answer_and_no_mail(): void
    {
        // Gdyby odpowiedź się różniła, formularz stałby się wyszukiwarką kont:
        // można by sprawdzać listę adresów i ustalać, kto sprzedaje na Kramio.
        $known = User::factory()->create(['password' => Hash::make('stare-haslo')]);

        $first = $this->post(route('password.email'), ['email' => $known->email]);
        EmailMessage::query()->delete();
        $second = $this->post(route('password.email'), ['email' => 'nikt@example.com']);

        $this->assertSame($first->getSession()->get('status'), $second->getSession()->get('status'));
        $second->assertSessionHasNoErrors();
        $this->assertSame(0, EmailMessage::count());
    }

    public function test_unactivated_account_gets_activation_instead(): void
    {
        // Konto po rejestracji ma tylko losowe hasło zastępcze, którego nikt nie
        // zna — „ustaw NOWE" byłoby myleniem. Znacznikiem jest potwierdzony
        // adres, NIE puste hasło (kolumna jest NOT NULL).
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->post(route('password.email'), ['email' => $user->email]);

        $mail = EmailMessage::latest('id')->first();

        $this->assertNotNull($mail);
        $this->assertStringContainsString('aktywacja', (string) $mail->action_url);
    }

    public function test_seller_can_set_a_new_password_and_log_in_with_it(): void
    {
        $user = User::factory()->create(['password' => Hash::make('stare-haslo')]);
        $token = Password::broker('users')->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Nowe-Haslo1',
            'password_confirmation' => 'Nowe-Haslo1',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Nowe-Haslo1', $user->fresh()->password));

        $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'Nowe-Haslo1',
        ])->assertRedirect(route('login.code'));

        $this->post(route('login.code.verify'), [
            'code' => substr(EmailMessage::latest('id')->firstOrFail()->subject, -6),
        ]);

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_invalid_token_is_refused(): void
    {
        $user = User::factory()->create(['password' => Hash::make('stare-haslo')]);

        $this->post(route('password.update'), [
            'token' => 'zmyslony-token',
            'email' => $user->email,
            'password' => 'Nowe-Haslo1',
            'password_confirmation' => 'Nowe-Haslo1',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('stare-haslo', $user->fresh()->password));
    }

    public function test_new_password_must_meet_the_same_rules_as_everywhere(): void
    {
        $user = User::factory()->create(['password' => Hash::make('stare-haslo')]);
        $token = Password::broker('users')->createToken($user);

        // Odzyskiwanie nie może być furtką na hasło słabsze niż przy rejestracji.
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'slabe',
            'password_confirmation' => 'slabe',
        ])->assertSessionHasErrors('password');
    }

    public function test_link_is_visible_on_the_login_screen(): void
    {
        // Funkcja, do której nie ma jak trafić, nie istnieje dla użytkownika.
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'), escape: false);
    }

    // -------------------------------------------------------------- storefront

    public function test_customer_receives_a_link_scoped_to_the_shop(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->create([
            'shop_id' => $shop->id,
            'password' => Hash::make('stare-haslo'),
            'email_verified_at' => now(),
        ]);

        $this->post($this->host($shop).'/nie-pamietam-hasla', ['email' => $customer->email])
            ->assertSessionHasNoErrors();

        $mail = EmailMessage::latest('id')->first();

        $this->assertNotNull($mail);
        $this->assertSame($shop->id, $mail->shop_id);
        $this->assertStringContainsString('nowe-haslo/'.$customer->id, (string) $mail->action_url);
    }

    public function test_customer_from_another_shop_is_not_found(): void
    {
        // Ten sam adres bywa kontem u wielu sprzedawców — sklep A nie może
        // wysyłać linków do konta w sklepie B ani potwierdzać, że ono istnieje.
        $first = Shop::factory()->create();
        $second = Shop::factory()->create();

        Customer::factory()->create([
            'shop_id' => $second->id,
            'email' => 'klient@example.com',
            'password' => Hash::make('stare-haslo'),
            'email_verified_at' => now(),
        ]);

        $this->post($this->host($first).'/nie-pamietam-hasla', ['email' => 'klient@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, EmailMessage::count());
    }

    public function test_customer_sets_a_new_password_and_is_logged_in(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->create([
            'shop_id' => $shop->id,
            'password' => Hash::make('stare-haslo'),
            'email_verified_at' => now(),
        ]);

        $url = URL::temporarySignedRoute('storefront.password.reset', now()->addHour(), [
            'shop' => $shop->slug,
            'customer' => $customer->id,
        ]);

        $this->post($url, [
            'password' => 'Nowe-Haslo1',
            'password_confirmation' => 'Nowe-Haslo1',
        ])->assertRedirect('/moje-konto');

        $this->assertTrue(Hash::check('Nowe-Haslo1', $customer->fresh()->password));
        $this->assertAuthenticatedAs($customer->fresh(), 'customer');
    }

    public function test_unsigned_link_is_refused(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->create(['shop_id' => $shop->id]);

        $this->get($this->host($shop).'/nowe-haslo/'.$customer->id)->assertForbidden();
    }

    public function test_signed_link_does_not_work_on_another_shop(): void
    {
        $first = Shop::factory()->create();
        $second = Shop::factory()->create();
        $customer = Customer::factory()->create(['shop_id' => $second->id]);

        // Podpis wiąże adres z subdomeną, ale sprawdzamy też `shop_id` — obrona
        // w głąb, gdyby kiedyś zmienił się sposób podpisywania.
        $url = URL::temporarySignedRoute('storefront.password.reset', now()->addHour(), [
            'shop' => $first->slug,
            'customer' => $customer->id,
        ]);

        $this->get($url)->assertNotFound();
    }
}
