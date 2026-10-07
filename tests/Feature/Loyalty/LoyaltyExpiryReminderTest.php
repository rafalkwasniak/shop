<?php

namespace Tests\Feature\Loyalty;

use App\Models\Customer;
use App\Models\EmailMessage;
use App\Models\Order;
use App\Models\Shop;
use App\Services\LoyaltyLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Przypomnienie „punkty wkrótce wygasną": 14 dni przed terminem, jeden mail
 * na klienta (suma porcji, najbliższa data), każda porcja najwyżej raz, same
 * punkty bez złotówek, gość dostaje zaproszenie do konta.
 */
class LoyaltyExpiryReminderTest extends TestCase
{
    use RefreshDatabase;

    private LoyaltyLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(LoyaltyLedger::class);
    }

    private function shop(): Shop
    {
        $shop = Shop::factory()->withLoyalty()->create();
        Order::factory()->create(['shop_id' => $shop->id, 'buyer_email' => 'anna@example.com', 'buyer_name' => 'Anna']);

        return $shop;
    }

    private function lot(Shop $shop, int $points, int $expiresInDays): void
    {
        $this->ledger->adjust($shop, 'anna@example.com', $points)
            ->update(['expires_at' => now()->addDays($expiresInDays)->endOfDay()]);
    }

    private function body(EmailMessage $mail): string
    {
        return json_encode([$mail->subject, $mail->preheader, $mail->intro_lines, $mail->outro_lines, $mail->action_url], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function test_lots_expiring_the_same_day_make_one_mail_with_the_sum(): void
    {
        $shop = $this->shop();
        $this->lot($shop, 199, 14);
        $this->lot($shop, 99, 14);
        $this->lot($shop, 500, 40); // poza oknem 14 dni

        $this->artisan('loyalty:remind')->expectsOutput('Wysłane przypomnienia: 1.')->assertSuccessful();

        $mail = EmailMessage::sole();
        $this->assertSame('anna@example.com', $mail->to_email);
        $this->assertStringContainsString('298 pkt', $this->body($mail));
        $this->assertStringContainsString(now()->addDays(14)->format('d.m.Y'), $this->body($mail));
        $this->assertStringNotContainsString('zł', $this->body($mail));
    }

    public function test_different_expiry_days_get_separate_mails_even_in_one_run(): void
    {
        $shop = $this->shop();
        $this->lot($shop, 199, 10);
        $this->lot($shop, 99, 12);

        $this->artisan('loyalty:remind')->expectsOutput('Wysłane przypomnienia: 2.');

        $bodies = EmailMessage::orderBy('id')->get()->map(fn (EmailMessage $mail): string => $this->body($mail))->implode(' ');
        $this->assertStringContainsString('199 pkt** wygaśnie **'.now()->addDays(10)->format('d.m.Y'), $bodies);
        $this->assertStringContainsString('99 pkt** wygaśnie **'.now()->addDays(12)->format('d.m.Y'), $bodies);
    }

    public function test_daily_runs_remind_each_day_as_it_reaches_fourteen_days(): void
    {
        $shop = $this->shop();
        $this->lot($shop, 199, 15);
        $this->lot($shop, 99, 16);

        $this->artisan('loyalty:remind')->expectsOutput('Wysłane przypomnienia: 0.');
        $this->travel(1)->days();
        $this->artisan('loyalty:remind')->expectsOutput('Wysłane przypomnienia: 1.');
        $this->travel(1)->days();
        $this->artisan('loyalty:remind')->expectsOutput('Wysłane przypomnienia: 1.');

        $this->assertSame(2, EmailMessage::count());
    }

    public function test_each_lot_is_reminded_only_once(): void
    {
        $shop = $this->shop();
        $this->lot($shop, 199, 10);

        $this->artisan('loyalty:remind');
        $this->artisan('loyalty:remind')->expectsOutput('Wysłane przypomnienia: 0.');

        $this->assertSame(1, EmailMessage::count());
    }

    public function test_guest_is_invited_to_create_an_account(): void
    {
        $shop = $this->shop();
        $this->lot($shop, 199, 10);

        $this->artisan('loyalty:remind');

        $this->assertStringContainsString('/rejestracja', EmailMessage::sole()->action_url);
    }

    public function test_customer_with_account_is_sent_to_the_shop(): void
    {
        $shop = $this->shop();
        Customer::factory()->for($shop)->create(['email' => 'anna@example.com']);
        $this->lot($shop, 199, 10);

        $this->artisan('loyalty:remind');

        $mail = EmailMessage::sole();
        $this->assertSame('https://'.$shop->host(), $mail->action_url);
        $this->assertStringContainsString('Wykorzystaj', $this->body($mail));
    }

    public function test_no_mail_when_debt_would_eat_the_expiring_points(): void
    {
        $shop = $this->shop();
        // Najpierw dług (nie ma z czego zdjąć), potem porcja — zostaje cała, ale
        // przy wygaśnięciu spłaci dług, więc z punktów klienta nic nie zniknie.
        $this->ledger->adjust($shop, 'anna@example.com', -300);
        $this->lot($shop, 100, 10);

        $this->artisan('loyalty:remind')->expectsOutput('Wysłane przypomnienia: 0.');

        $this->assertSame(0, EmailMessage::count());
    }
}
