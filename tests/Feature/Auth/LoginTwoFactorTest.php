<?php

namespace Tests\Feature\Auth;

use App\Enums\MailPriority;
use App\Models\EmailMessage;
use App\Models\LoginChallenge;
use App\Models\User;
use App\Services\LoginChallenges;
use App\Support\Vocative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Logowanie dwuetapowe do centrali: hasło, potem 6-cyfrowy kod z maila.
 */
class LoginTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function submitPassword(User $user, array $extra = []): TestResponse
    {
        return $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'password',
        ] + $extra);
    }

    private function lastCode(): string
    {
        $subject = EmailMessage::latest('id')->firstOrFail()->subject;
        $this->assertMatchesRegularExpression('/: \d{6}$/', $subject);

        return substr($subject, -6);
    }

    private function wrongCode(): string
    {
        return $this->lastCode() === '000000' ? '111111' : '000000';
    }

    public function test_correct_password_asks_for_code_instead_of_logging_in(): void
    {
        $admin = User::factory()->admin()->create();

        $this->submitPassword($admin)->assertRedirect(route('login.code'));

        $this->assertGuest();
        $this->assertNull($admin->fresh()->last_login_at);
        $this->get(route('login.code'))->assertOk()->assertSee($admin->email);
    }

    public function test_code_mail_is_high_priority_and_sent_right_away(): void
    {
        $admin = User::factory()->admin()->create();

        $this->submitPassword($admin);

        $message = EmailMessage::sole();
        $this->assertSame($admin->email, $message->to_email);
        $this->assertSame(MailPriority::High, $message->priority);
        $this->assertStringContainsString('Twój kod logowania: '.$this->lastCode(), implode(' ', $message->intro_lines));
        // Nagłówek-powitanie jak w wiadomościach platformy, bez drugiego powitania.
        $this->assertSame(Vocative::headline($admin->name), $message->heading);
        $this->assertNull($message->greeting);

        // Bez czekania na cron: outbox opróżniony zaraz po odpowiedzi.
        $this->assertNotNull($message->fresh()->sent_at);
    }

    public function test_admin_preview_shows_the_real_code_mail(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('administrator.mail.preview', 'kod-logowania'))
            ->assertOk()
            ->assertSee('Twój kod logowania: 482913')
            ->assertSee('Dlaczego prosimy o kod?');
    }

    public function test_immediate_send_does_not_boot_the_console(): void
    {
        // Artisan z żądania WWW ładuje routes/console.php, a harmonogram woła
        // `exec()` — zablokowane przez sandbox hostingu na stronie (nie w CLI,
        // więc sam test tego nie odtworzy). Incydent 03.10, patrz EmailOutbox.
        Artisan::spy();
        $admin = User::factory()->admin()->create();

        $this->submitPassword($admin);

        Artisan::shouldNotHaveReceived('call');
        $this->assertNotNull(EmailMessage::sole()->sent_at);
    }

    public function test_correct_code_logs_in_and_redirects_to_panel(): void
    {
        $admin = User::factory()->admin()->create();
        $this->submitPassword($admin);

        $this->post(route('login.code.verify'), ['code' => $this->lastCode()])
            ->assertRedirect(route('administrator.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh()->last_login_at);
        $this->assertSame(0, LoginChallenge::count());
    }

    public function test_sellers_and_employees_also_need_the_code(): void
    {
        foreach ([User::factory()->create(), User::factory()->employee()->create()] as $user) {
            $this->submitPassword($user)->assertRedirect(route('login.code'));
            $this->assertGuest();
        }
    }

    public function test_code_typed_with_spaces_is_accepted(): void
    {
        $seller = User::factory()->create();
        $this->submitPassword($seller);

        $code = $this->lastCode();

        $this->post(route('login.code.verify'), ['code' => ' '.substr($code, 0, 3).' '.substr($code, 3).' '])
            ->assertRedirect(route('seller.dashboard'));

        $this->assertAuthenticatedAs($seller);
    }

    public function test_wrong_code_is_rejected_and_counted(): void
    {
        $admin = User::factory()->admin()->create();
        $this->submitPassword($admin);

        $this->from(route('login.code'))
            ->post(route('login.code.verify'), ['code' => $this->wrongCode()])
            ->assertRedirect(route('login.code'))
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertSame(1, LoginChallenge::sole()->attempts);
    }

    public function test_exhausted_attempts_kill_the_code_and_send_back_to_password(): void
    {
        $admin = User::factory()->admin()->create();
        $this->submitPassword($admin);
        $code = $this->lastCode();
        $wrong = $this->wrongCode();

        foreach (range(1, 4) as $ignored) {
            $this->post(route('login.code.verify'), ['code' => $wrong])->assertSessionHasErrors('code');
        }

        $this->post(route('login.code.verify'), ['code' => $wrong])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        // Trafiony kod po wyczerpaniu limitu już nie wpuszcza.
        $this->post(route('login.code.verify'), ['code' => $code])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, LoginChallenge::count());
    }

    public function test_expired_code_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $this->submitPassword($admin);
        $code = $this->lastCode();

        $this->travel(11)->minutes();

        $this->post(route('login.code.verify'), ['code' => $code])
            ->assertSessionHasErrors(['code' => 'Kod wygasł. Wyślij nowy i wpisz go poniżej.']);

        $this->assertGuest();
    }

    public function test_resend_respects_cooldown_and_invalidates_previous_code(): void
    {
        $admin = User::factory()->admin()->create();
        $this->submitPassword($admin);
        $old = $this->lastCode();

        $this->post(route('login.code.resend'))->assertSessionHasErrors('code');
        $this->assertSame(1, EmailMessage::count());

        $this->travel(61)->seconds();

        $this->post(route('login.code.resend'))->assertSessionHas('status');
        $this->assertSame(2, EmailMessage::count());
        $new = $this->lastCode();

        if ($old !== $new) {
            $this->post(route('login.code.verify'), ['code' => $old])->assertSessionHasErrors('code');
        }

        $this->post(route('login.code.verify'), ['code' => $new])->assertRedirect(route('administrator.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_new_login_replaces_previous_pending_code(): void
    {
        $admin = User::factory()->admin()->create();

        $this->submitPassword($admin);
        $this->submitPassword($admin);

        $this->assertSame(1, LoginChallenge::count());
    }

    public function test_code_screen_without_pending_login_goes_back_to_password(): void
    {
        $this->get(route('login.code'))->assertRedirect(route('login'));
        $this->post(route('login.code.verify'), ['code' => '123456'])->assertRedirect(route('login'));
    }

    public function test_remember_me_lasts_thirty_days(): void
    {
        $this->get(route('login'))->assertSee('Zapamiętaj mnie na 30 dni');

        $admin = User::factory()->admin()->create();
        $this->submitPassword($admin, ['remember' => '1']);

        $response = $this->post(route('login.code.verify'), ['code' => $this->lastCode()]);

        $cookie = $response->getCookie(Auth::guard('web')->getRecallerName(), false);
        $this->assertNotNull($cookie);
        $this->assertEqualsWithDelta(now()->addMinutes(60 * 24 * 30)->getTimestamp(), $cookie->getExpiresTime(), 60);
    }

    public function test_kill_switch_logs_in_with_password_alone(): void
    {
        config(['security.two_factor.enabled' => false]);
        $admin = User::factory()->admin()->create();

        $this->submitPassword($admin)->assertRedirect(route('administrator.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertSame(0, EmailMessage::count());
    }

    public function test_console_issues_code_for_pending_login(): void
    {
        $admin = User::factory()->admin()->create();

        $this->artisan('auth:login-code', ['email' => $admin->email])->assertFailed();

        $this->submitPassword($admin);

        $this->artisan('auth:login-code', ['email' => $admin->email])
            ->expectsOutputToContain('Kod logowania:')
            ->assertSuccessful();

        $this->assertSame(1, EmailMessage::count(), 'Kod z konsoli nie idzie mailem.');
    }

    public function test_console_code_logs_in(): void
    {
        $admin = User::factory()->admin()->create();
        $this->submitPassword($admin);

        $challenge = LoginChallenge::sole();
        $code = app(LoginChallenges::class)->issueForConsole($admin);

        $this->post(route('login.code.verify'), ['code' => $code])->assertRedirect(route('administrator.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(LoginChallenge::whereKey($challenge->id)->exists());
    }

    public function test_dispatch_skips_while_another_run_holds_the_lock(): void
    {
        config(['mail_outbox.lock_wait_seconds' => 0]);
        EmailMessage::create(['to_email' => 'a@example.com', 'subject' => 'Test']);

        $lock = Cache::lock('email-dispatch', 120);
        $lock->get();

        $this->artisan('email:dispatch')->assertSuccessful();
        $this->assertNull(EmailMessage::sole()->sent_at);

        $lock->release();

        $this->artisan('email:dispatch')->assertSuccessful();
        $this->assertNotNull(EmailMessage::sole()->fresh()->sent_at);
    }
}
