<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use App\Models\EmailMessage;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Ślad ostatniego logowania na koncie sprzedawcy — z niego konsola admina czyta,
 * czy konto żyje, czy zostało porzucone zaraz po rejestracji.
 */
class LoginTimestampTest extends TestCase
{
    use RefreshDatabase;

    private function completeWithCode(): void
    {
        $this->post(route('login.code.verify'), [
            'code' => substr(EmailMessage::latest('id')->firstOrFail()->subject, -6),
        ])->assertRedirect();
    }

    public function test_logging_in_stamps_last_login_at(): void
    {
        $user = User::factory()->create(['email' => 'sprzedawca@example.com']);
        $this->assertNull($user->last_login_at);

        $this->post(route('login.attempt'), [
            'email' => 'sprzedawca@example.com',
            'password' => 'password',
        ])->assertRedirect();
        $this->completeWithCode();

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_stamping_does_not_touch_updated_at(): void
    {
        // `updated_at` na koncie znaczy „ktoś zmienił dane". Wejście do panelu
        // zmianą nie jest, a przestemplowanie zatarłoby ślad prawdziwej edycji.
        $user = User::factory()->create(['email' => 'sprzedawca@example.com']);
        $before = $user->updated_at;

        Carbon::setTestNow(now()->addHour());

        $this->post(route('login.attempt'), [
            'email' => 'sprzedawca@example.com',
            'password' => 'password',
        ])->assertRedirect();
        $this->completeWithCode();

        Carbon::setTestNow();

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->last_login_at);
        $this->assertTrue($before->equalTo($fresh->updated_at));
    }

    public function test_customer_login_is_ignored(): void
    {
        // Zdarzenie `Login` leci z KAŻDEGO strażnika, także `customer` ze
        // storefrontu. Klient nie jest `User` i nie ma tej kolumny — listener
        // musi go po prostu przepuścić, a nie wywrócić logowanie w sklepie.
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();

        event(new Login('customer', $customer, false));

        $this->assertSame(0, User::whereNotNull('last_login_at')->count());
    }
}
