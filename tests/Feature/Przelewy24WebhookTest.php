<?php

namespace Tests\Feature;

use App\Enums\IntegrationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Jobs\GenerateInvoice;
use App\Models\EmailMessage;
use App\Models\Order;
use App\Models\Shop;
use App\Services\Przelewy24Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Webhook Przelewy24. Inaczej niż w Paynow samo powiadomienie NIE potwierdza
 * zapłaty: zamówienie przechodzi na „Opłacone" dopiero po udanej weryfikacji w
 * P24. Pilnujemy też podróbek (podpis, cudze konto), niedopłat (kwota) i
 * powtórek (jeden mail, jedno przejście).
 */
class Przelewy24WebhookTest extends TestCase
{
    use RefreshDatabase;

    private const CRC = 'CRC-SECRET';

    private const URL = '/platnosci/przelewy24/webhook';

    private const VERIFY = 'sandbox.przelewy24.pl/api/v1/transaction/verify';

    private function awaitingOrder(bool $autoInvoice = false, bool $withFakturownia = false, float $total = 123.45): Order
    {
        $shop = Shop::factory()->withOnlinePayments()->when($withFakturownia, fn ($f) => $f->withInvoicing())->create();
        $shop->integrations()->create([
            'type' => IntegrationType::Przelewy24,
            'enabled' => true,
            'config' => [
                'merchant_id' => 431164, 'pos_id' => null, 'crc_key' => self::CRC, 'api_key' => 'API',
                'environment' => 'sandbox', 'auto_invoice' => $autoInvoice,
            ],
        ]);

        if ($withFakturownia) {
            $shop->integrations()->create([
                'type' => IntegrationType::Invoicing,
                'enabled' => true,
                'config' => ['account_url' => 'https://sklep.fakturownia.pl', 'api_token' => 'SECRET'],
            ]);
        }

        return Order::factory()->for($shop->fresh())->create([
            'status' => OrderStatus::AwaitingPayment,
            'payment_method' => PaymentMethod::Online,
            'buyer_email' => 'kupujacy@example.com',
            'total_gross' => $total,
        ]);
    }

    /**
     * Powiadomienie tak, jak wysyła je P24 — podpisane kluczem CRC.
     *
     * @return array<string, mixed>
     */
    private function notification(Order $order, array $overrides = [], string $crc = self::CRC): array
    {
        $payload = array_merge([
            'merchantId' => 431164,
            'posId' => 431164,
            'sessionId' => 'kramio-'.$order->id.'-abc123',
            'amount' => 12345,
            'originAmount' => 12345,
            'currency' => 'PLN',
            'orderId' => 316954321,
            'methodId' => 154,
            'statement' => 'p24-A1B-C2D',
        ], $overrides);

        $payload['sign'] = app(Przelewy24Service::class)->notificationSign(
            $payload['merchantId'], $payload['posId'], $payload['sessionId'], $payload['amount'], $payload['originAmount'],
            $payload['currency'], $payload['orderId'], $payload['methodId'], $payload['statement'], $crc,
        );

        return $payload;
    }

    private function verifyOk(): void
    {
        Http::fake([self::VERIFY => Http::response(['data' => ['status' => 'success'], 'responseCode' => 0])]);
    }

    public function test_verified_payment_marks_order_paid_and_mails_buyer(): void
    {
        $this->verifyOk();
        $order = $this->awaitingOrder();

        $this->postJson(self::URL, $this->notification($order))->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(PaymentProvider::Przelewy24, $order->payment_provider);
        $this->assertSame('kramio-'.$order->id.'-abc123', $order->payment_external_id);
        $this->assertSame('VERIFIED', $order->payment_status);
        $this->assertSame(1, EmailMessage::count());

        // Weryfikacja kwotą z ZAMÓWIENIA i numerem transakcji P24 z powiadomienia.
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
            && $r['orderId'] === 316954321
            && $r['amount'] === 12345
            && $r['sessionId'] === 'kramio-'.$order->id.'-abc123');
    }

    public function test_failed_verification_leaves_order_awaiting_and_asks_p24_to_retry(): void
    {
        // Samo powiadomienie to nie zapłata. 500 = P24 ponowi powiadomienie.
        Http::fake([self::VERIFY => Http::response(['error' => 'Invalid input data', 'code' => 400], 400)]);
        $order = $this->awaitingOrder();

        $this->postJson(self::URL, $this->notification($order))->assertStatus(500);

        $this->assertSame(OrderStatus::AwaitingPayment, $order->refresh()->status);
        $this->assertSame(0, EmailMessage::count());
    }

    public function test_forged_signature_is_rejected_without_verification(): void
    {
        Http::fake();
        $order = $this->awaitingOrder();

        $this->postJson(self::URL, $this->notification($order, crc: 'PODROBIONY'))->assertStatus(400);

        $this->assertSame(OrderStatus::AwaitingPayment, $order->refresh()->status);
        Http::assertNothingSent();
    }

    public function test_notification_from_another_p24_account_is_rejected(): void
    {
        // Prawidłowo podpisane, ale kluczem i kontem INNEGO sprzedawcy: ktoś
        // płaci u siebie grosz i podsyła powiadomienie pod cudze zamówienie.
        Http::fake();
        $order = $this->awaitingOrder();

        $this->postJson(self::URL, $this->notification($order, ['merchantId' => 999, 'posId' => 999], 'CRC-OBCY'))->assertStatus(400);

        $this->assertSame(OrderStatus::AwaitingPayment, $order->refresh()->status);
        Http::assertNothingSent();
    }

    public function test_underpayment_is_not_verified(): void
    {
        Http::fake();
        $order = $this->awaitingOrder(total: 123.45);

        $this->postJson(self::URL, $this->notification($order, ['amount' => 100, 'originAmount' => 100]))->assertStatus(400);

        $this->assertSame(OrderStatus::AwaitingPayment, $order->refresh()->status);
        Http::assertNothingSent();
    }

    public function test_repeated_notification_does_not_change_status_or_mail_twice(): void
    {
        $this->verifyOk();
        $order = $this->awaitingOrder();
        $payload = $this->notification($order);

        $this->postJson(self::URL, $payload)->assertOk();
        $this->postJson(self::URL, $payload)->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertCount(1, $order->statusEvents);
        $this->assertSame(1, EmailMessage::count());
    }

    public function test_payment_from_an_older_attempt_still_reaches_the_order(): void
    {
        // Klient otworzył płatność dwa razy i zapłacił w PIERWSZEJ karcie. Na
        // zamówieniu zapisana jest druga próba — a pieniądze i tak nie mogą zawisnąć.
        $this->verifyOk();
        $order = $this->awaitingOrder();
        $order->forceFill([
            'payment_provider' => PaymentProvider::Przelewy24,
            'payment_external_id' => 'kramio-'.$order->id.'-druga',
        ])->save();

        $this->postJson(self::URL, $this->notification($order, ['sessionId' => 'kramio-'.$order->id.'-pierwsza']))->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('kramio-'.$order->id.'-pierwsza', $order->payment_external_id);
    }

    public function test_payment_for_cancelled_order_is_verified_but_status_stays(): void
    {
        // Bez weryfikacji pieniądze klienta utknęłyby w P24 jako przedpłata.
        // Weryfikujemy (trafiają do sprzedawcy, który zwraca), status zostaje.
        $this->verifyOk();
        $order = $this->awaitingOrder();
        $order->forceFill(['status' => OrderStatus::Cancelled])->save();

        $this->postJson(self::URL, $this->notification($order))->assertOk();

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame('VERIFIED', $order->payment_status);
        Http::assertSentCount(1);
    }

    public function test_unknown_session_format_and_missing_order_are_rejected(): void
    {
        Http::fake();

        $this->postJson(self::URL, ['sessionId' => 'cos-innego'])->assertStatus(400);
        $this->postJson(self::URL, ['sessionId' => 'kramio-999999-abc'])->assertStatus(404);

        Http::assertNothingSent();
    }

    public function test_auto_invoice_follows_the_przelewy24_flag(): void
    {
        Bus::fake();
        $this->verifyOk();
        $order = $this->awaitingOrder(autoInvoice: true, withFakturownia: true);

        $this->postJson(self::URL, $this->notification($order))->assertOk();

        Bus::assertDispatched(GenerateInvoice::class);
    }

    public function test_no_auto_invoice_when_przelewy24_flag_off(): void
    {
        Bus::fake();
        $this->verifyOk();
        $order = $this->awaitingOrder(autoInvoice: false, withFakturownia: true);

        $this->postJson(self::URL, $this->notification($order))->assertOk();

        Bus::assertNotDispatched(GenerateInvoice::class);
    }

    public function test_webhook_is_reachable_on_the_shop_subdomain(): void
    {
        // P24 woła adres z hosta sklepu (`Shop::przelewy24WebhookUrl()`), a nie
        // centrali — trasa musi go złapać, zanim przejmie go grupa storefrontu.
        $this->verifyOk();
        $order = $this->awaitingOrder();
        $url = $order->shop->przelewy24WebhookUrl();

        $this->postJson($url, $this->notification($order))->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }
}
