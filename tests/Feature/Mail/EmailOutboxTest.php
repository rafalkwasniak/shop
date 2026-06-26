<?php

namespace Tests\Feature\Mail;

use App\Enums\MailPriority;
use App\Mail\OutboxMailable;
use App\Models\EmailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailOutboxTest extends TestCase
{
    use RefreshDatabase;

    private function queue(array $overrides = []): EmailMessage
    {
        return EmailMessage::create(array_merge([
            'to_email' => 'klient@przyklad.test',
            'subject' => 'Temat',
            'greeting' => 'Cześć,',
            'intro_lines' => ['Pierwsza linia.'],
        ], $overrides));
    }

    public function test_queued_message_defaults_to_mid_priority_and_is_scheduled_now(): void
    {
        $message = $this->queue();

        $this->assertSame(MailPriority::Mid, $message->priority);
        $this->assertNotNull($message->scheduled_at);
        $this->assertNull($message->sent_at);
    }

    public function test_dispatch_sends_due_messages_and_marks_them_sent(): void
    {
        Mail::fake();
        $message = $this->queue();

        $this->artisan('email:dispatch')->assertSuccessful();

        Mail::assertSent(OutboxMailable::class);
        $this->assertNotNull($message->fresh()->sent_at);
    }

    public function test_dispatch_orders_by_priority_then_age(): void
    {
        Mail::fake();

        $low = $this->queue(['priority' => MailPriority::Low, 'to_email' => 'low@przyklad.test']);
        $high = $this->queue(['priority' => MailPriority::High, 'to_email' => 'high@przyklad.test']);

        $this->artisan('email:dispatch')->assertSuccessful();

        // Oba wysłane, ale High musi zostać przetworzony jako pierwszy.
        $sentOrder = [];
        Mail::assertSent(OutboxMailable::class, function (OutboxMailable $mail) use (&$sentOrder) {
            $sentOrder[] = $mail->message->to_email;

            return true;
        });

        $this->assertSame(['high@przyklad.test', 'low@przyklad.test'], $sentOrder);
        $this->assertNotNull($low->fresh()->sent_at);
        $this->assertNotNull($high->fresh()->sent_at);
    }

    public function test_future_scheduled_message_is_not_sent_yet(): void
    {
        Mail::fake();
        $message = $this->queue(['scheduled_at' => Carbon::now()->addHour()]);

        $this->artisan('email:dispatch')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNull($message->fresh()->sent_at);
    }

    public function test_failed_delivery_backs_off_then_permanently_fails(): void
    {
        config()->set('mail_outbox.max_attempts', 2);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $message = $this->queue();

        // 1. próba: błąd → backoff, brak trwałego faila, scheduled_at w przyszłość.
        $this->artisan('email:dispatch')->assertSuccessful();
        $message->refresh();
        $this->assertSame(1, $message->attempts);
        $this->assertNull($message->failed_at);
        $this->assertTrue($message->scheduled_at->isFuture());

        // Cofamy termin, by druga próba była „due”.
        $message->forceFill(['scheduled_at' => Carbon::now()->subMinute()])->save();

        // 2. próba: wyczerpane → trwały fail.
        $this->artisan('email:dispatch')->assertSuccessful();
        $message->refresh();
        $this->assertSame(2, $message->attempts);
        $this->assertNotNull($message->failed_at);
        $this->assertSame('SMTP down', $message->last_error);
    }
}
