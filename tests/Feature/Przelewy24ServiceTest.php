<?php

namespace Tests\Feature;

use App\Enums\IntegrationType;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Shop;
use App\Services\Przelewy24Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Usługa Przelewy24: trzy podpisy, rejestracja transakcji, rozpoznanie
 * prawdziwego powiadomienia, weryfikacja i test dostępu. Sieć zaślepiona
 * (`Http::fake`) — realny kontrakt potwierdza dopiero sandbox.
 *
 * Podpisy sprawdzamy na DOSŁOWNYCH ciągach JSON z oficjalnej dokumentacji P24
 * (YAML v1.0.18): kolejność kluczy i typy (int vs string) zmieniają wynik, a P24
 * odpowiada na zły podpis gołym 401 bez opisu. Test złapie każdy z tych błędów.
 */
class Przelewy24ServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): Przelewy24Service
    {
        return app(Przelewy24Service::class);
    }

    private function configuredShop(array $overrides = []): Shop
    {
        $shop = Shop::factory()->withOnlinePayments()->create(['name' => 'Lemoniady']);
        $shop->integrations()->create([
            'type' => IntegrationType::Przelewy24,
            'enabled' => true,
            'config' => array_merge([
                'merchant_id' => 431164,
                'pos_id' => null,
                'crc_key' => 'CRC-SECRET',
                'api_key' => 'API-SECRET',
                'environment' => 'sandbox',
            ], $overrides),
        ]);

        return $shop->fresh();
    }

    private function order(Shop $shop, array $attributes = []): Order
    {
        return Order::factory()->for($shop)->create(array_merge([
            'number' => 17,
            'payment_method' => PaymentMethod::Online,
            'total_gross' => 123.45,
            'buyer_email' => 'kupujacy@example.com',
        ], $attributes));
    }

    // --- Podpisy ---------------------------------------------------------

    public function test_register_sign_matches_documented_json(): void
    {
        $json = '{"sessionId":"sessionId","merchantId":999999,"amount":1000,"currency":"PLN","crc":"crc"}';

        $this->assertSame(
            hash('sha384', $json),
            $this->service()->registerSign('sessionId', 999999, 1000, 'PLN', 'crc'),
        );
    }

    public function test_verify_sign_matches_documented_json(): void
    {
        $json = '{"sessionId":"sessionId","orderId":999999,"amount":1000,"currency":"PLN","crc":"crc"}';

        $this->assertSame(
            hash('sha384', $json),
            $this->service()->verifySign('sessionId', 999999, 1000, 'PLN', 'crc'),
        );
    }

    public function test_notification_sign_matches_documented_field_order(): void
    {
        // Tytuł z polskimi znakami i ukośnikiem: flagi UNESCAPED_UNICODE i
        // UNESCAPED_SLASHES muszą dać surowe „ó" i „/", nie ó i \/.
        $json = '{"merchantId":431164,"posId":431164,"sessionId":"kramio-1-abc","amount":12345,"originAmount":12345,"currency":"PLN","orderId":316954321,"methodId":154,"statement":"p24-Zamówienie 1/2","crc":"crc"}';

        $this->assertSame(
            hash('sha384', $json),
            $this->service()->notificationSign(431164, 431164, 'kramio-1-abc', 12345, 12345, 'PLN', 316954321, 154, 'p24-Zamówienie 1/2', 'crc'),
        );
    }

    // --- Rejestracja ----------------------------------------------------

    public function test_register_transaction_sends_signed_request_and_builds_redirect(): void
    {
        Http::fake([
            'sandbox.przelewy24.pl/api/v1/transaction/register' => Http::response(['data' => ['token' => 'TOK-123'], 'responseCode' => 0]),
        ]);

        $shop = $this->configuredShop();
        $order = $this->order($shop);

        $result = $this->service()->registerTransaction($order, 'https://lemoniady.kramio.pl/platnosc/x', 'https://lemoniady.kramio.pl/platnosci/przelewy24/webhook');

        $this->assertNotNull($result);
        $this->assertSame('TOK-123', $result['token']);
        $this->assertSame('https://sandbox.przelewy24.pl/trnRequest/TOK-123', $result['redirectUrl']);
        $this->assertStringStartsWith('kramio-'.$order->id.'-', $result['sessionId']);

        Http::assertSent(function (Request $request) use ($result) {
            return $request->method() === 'POST'
                && $request->url() === 'https://sandbox.przelewy24.pl/api/v1/transaction/register'
                // Basic Auth: login = POS ID (puste → Merchant ID), hasło = klucz API.
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('431164:API-SECRET'))
                && $request['merchantId'] === 431164
                && $request['posId'] === 431164
                && $request['amount'] === 12345
                && $request['currency'] === 'PLN'
                && $request['email'] === 'kupujacy@example.com'
                && $request['urlStatus'] === 'https://lemoniady.kramio.pl/platnosci/przelewy24/webhook'
                && $request['sign'] === hash('sha384', json_encode([
                    'sessionId' => $result['sessionId'], 'merchantId' => 431164, 'amount' => 12345, 'currency' => 'PLN', 'crc' => 'CRC-SECRET',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        });
    }

    public function test_each_attempt_gets_a_new_session_id(): void
    {
        Http::fake(['*/api/v1/transaction/register' => Http::response(['data' => ['token' => 'T']])]);

        $shop = $this->configuredShop();
        $order = $this->order($shop);

        $first = $this->service()->registerTransaction($order, 'https://a/r', 'https://a/s');
        $second = $this->service()->registerTransaction($order, 'https://a/r', 'https://a/s');

        $this->assertNotSame($first['sessionId'], $second['sessionId']);
    }

    public function test_production_environment_uses_secure_host_and_distinct_pos_id(): void
    {
        Http::fake(['secure.przelewy24.pl/*' => Http::response(['data' => ['token' => 'T']])]);

        $shop = $this->configuredShop(['environment' => 'production', 'pos_id' => 555]);

        $result = $this->service()->registerTransaction($this->order($shop), 'https://a/r', 'https://a/s');

        $this->assertSame('https://secure.przelewy24.pl/trnRequest/T', $result['redirectUrl']);
        Http::assertSent(fn (Request $r) => $r['posId'] === 555
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('555:API-SECRET')));
    }

    public function test_register_returns_null_on_api_error(): void
    {
        Http::fake(['*' => Http::response(['error' => 'Incorrect authentication', 'code' => 401], 401)]);

        $shop = $this->configuredShop();

        $this->assertNull($this->service()->registerTransaction($this->order($shop), 'https://a/r', 'https://a/s'));
    }

    public function test_too_long_email_is_refused_before_any_request(): void
    {
        Http::fake();

        $shop = $this->configuredShop();
        $order = $this->order($shop, ['buyer_email' => str_repeat('a', 40).'@example.com']);

        $this->assertNull($this->service()->registerTransaction($order, 'https://a/r', 'https://a/s'));
        Http::assertNothingSent();
    }

    public function test_unconfigured_shop_sends_nothing(): void
    {
        Http::fake();

        $shop = Shop::factory()->withOnlinePayments()->create();

        $this->assertNull($this->service()->registerTransaction($this->order($shop), 'https://a/r', 'https://a/s'));
        $this->assertFalse($this->service()->testAccess($shop));
        Http::assertNothingSent();
    }

    // --- Powiadomienie -------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function notification(array $overrides = [], string $crc = 'CRC-SECRET'): array
    {
        $payload = array_merge([
            'merchantId' => 431164,
            'posId' => 431164,
            'sessionId' => 'kramio-1-abc',
            'amount' => 12345,
            'originAmount' => 12345,
            'currency' => 'PLN',
            'orderId' => 316954321,
            'methodId' => 154,
            'statement' => 'p24-A1B-C2D',
        ], $overrides);

        $payload['sign'] = $this->service()->notificationSign(
            $payload['merchantId'], $payload['posId'], $payload['sessionId'], $payload['amount'], $payload['originAmount'],
            $payload['currency'], $payload['orderId'], $payload['methodId'], $payload['statement'], $crc,
        );

        return $payload;
    }

    public function test_genuine_notification_is_accepted(): void
    {
        $this->assertTrue($this->service()->isAuthenticNotification($this->configuredShop(), $this->notification()));
    }

    public function test_notification_signed_with_another_crc_is_rejected(): void
    {
        $this->assertFalse($this->service()->isAuthenticNotification($this->configuredShop(), $this->notification(crc: 'OBCY')));
    }

    public function test_tampered_amount_is_rejected(): void
    {
        $payload = $this->notification();
        $payload['amount'] = 1;

        $this->assertFalse($this->service()->isAuthenticNotification($this->configuredShop(), $payload));
    }

    public function test_notification_for_another_merchant_is_rejected(): void
    {
        // Podpis poprawny, ale dla innego konta — np. sprzedawca A podsyła
        // powiadomienie ze swojego konta P24 pod zamówienie sklepu B.
        $this->assertFalse($this->service()->isAuthenticNotification($this->configuredShop(), $this->notification(['merchantId' => 1, 'posId' => 1])));
    }

    public function test_notification_missing_a_signed_field_is_rejected(): void
    {
        $payload = $this->notification();
        unset($payload['statement']);

        $this->assertFalse($this->service()->isAuthenticNotification($this->configuredShop(), $payload));
    }

    // --- Weryfikacja i test dostępu -------------------------------------

    public function test_verify_transaction_sends_signed_put_and_reports_success(): void
    {
        Http::fake(['sandbox.przelewy24.pl/api/v1/transaction/verify' => Http::response(['data' => ['status' => 'success'], 'responseCode' => 0])]);

        $ok = $this->service()->verifyTransaction($this->configuredShop(), 'kramio-1-abc', 316954321, 12345);

        $this->assertTrue($ok);
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
            && $r['orderId'] === 316954321
            && $r['amount'] === 12345
            && $r['sign'] === hash('sha384', '{"sessionId":"kramio-1-abc","orderId":316954321,"amount":12345,"currency":"PLN","crc":"CRC-SECRET"}'));
    }

    public function test_verify_transaction_reports_failure(): void
    {
        Http::fake(['*' => Http::response(['error' => 'Invalid input data', 'code' => 400], 400)]);

        $this->assertFalse($this->service()->verifyTransaction($this->configuredShop(), 'kramio-1-abc', 316954321, 12345));
    }

    public function test_access_check(): void
    {
        Http::fake(['sandbox.przelewy24.pl/api/v1/testAccess' => Http::sequence()
            ->push(['data' => true, 'error' => ''])
            ->push(['error' => 'Incorrect authentication', 'code' => 401], 401)]);

        $shop = $this->configuredShop();

        $this->assertTrue($this->service()->testAccess($shop));
        $this->assertFalse($this->service()->testAccess($shop));
    }
}
