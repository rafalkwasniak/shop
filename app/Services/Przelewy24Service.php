<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shop;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Płatności online przez Przelewy24 (REST API v1). Konfiguracja PER-SKLEP
 * (Merchant ID, POS ID, klucz CRC, klucz API ze `shop_integrations`,
 * zaszyfrowane) — pieniądze płyną klient → P24 → sprzedawca, wprost.
 *
 * Celowo OSOBNA usługa, nie wspólny interfejs z Paynow: P24 potwierdza zapłatę
 * inaczej. Powiadomienie (webhook) samo NIE jest potwierdzeniem — dopiero nasze
 * `PUT /transaction/verify` zamyka transakcję. Bez weryfikacji pieniądze nie
 * trafiają do sprzedawcy (zostają u klienta jako przedpłata w P24), a gdybyśmy
 * uznali samo powiadomienie, przyjmowalibyśmy podróbki.
 *
 * Podpisy (`sign`) — SHA-384 z JSON-a WYBRANYCH pól, w ustalonej kolejności,
 * liczby jako int, flagi JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES. Zestaw
 * pól jest INNY dla rejestracji, weryfikacji i powiadomienia (oficjalny YAML
 * P24 v1.0.18, sprawdzony 2026-11-23). Zły podpis = gołe 401 bez opisu, dlatego
 * każdy z trzech ma własną metodę i własny test.
 *
 * Świadome granice: NIE zmienia statusu zamówienia ani nie wysyła maili (robi
 * to webhook przez `OrderStatusChanger`), NIE sprawdza uprawnienia pakietu.
 * Sekrety (CRC, klucz API) nigdy nie trafiają do dziennika.
 */
class Przelewy24Service
{
    /** P24 przyjmuje e-mail klienta do 50 znaków (pole wymagane). */
    public const MAX_EMAIL_LENGTH = 50;

    /**
     * Sprawdza dane dostępowe (`GET /api/v1/testAccess`) — Basic Auth POS ID +
     * klucz API. Łapie złe klucze, złe środowisko i brak naszego IP w panelu P24,
     * zanim zrobi to pierwszy kupujący. Nie tworzy niczego po stronie P24.
     */
    public function testAccess(Shop $shop): bool
    {
        if (! $shop->przelewy24Configured()) {
            return false;
        }

        try {
            $response = $this->client($shop)->get($this->url($shop, '/api/v1/testAccess'));
        } catch (\Throwable $e) {
            Log::channel('przelewy24')->error('P24: wyjątek przy teście dostępu.', [
                'shop_id' => $shop->id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }

        $ok = $response->successful() && $response->json('data') === true;

        Log::channel('przelewy24')->info('P24: test dostępu.', [
            'shop_id' => $shop->id,
            'environment' => $shop->przelewy24Environment(),
            'ok' => $ok,
            'http_status' => $response->status(),
            'body' => $ok ? null : $response->json(),
        ]);

        return $ok;
    }

    /**
     * Rejestruje transakcję dla zamówienia i zwraca dane do przekierowania.
     * null, gdy brak konfiguracji, e-mail za długi dla P24 albo API odmówiło —
     * wołający decyduje, co pokazać kupującemu.
     *
     * `sessionId` generujemy przy KAŻDEJ próbie (P24 wymaga unikalnego, a klient
     * może zapłacić ponownie z maila). Zawiera globalne id zamówienia, więc jest
     * unikalny między sklepami — po nim webhook odnajdzie zamówienie.
     *
     * @return array{sessionId: string, token: string, redirectUrl: string}|null
     */
    public function registerTransaction(Order $order, string $returnUrl, string $statusUrl): ?array
    {
        $order->loadMissing('shop');
        $shop = $order->shop;

        if ($shop === null || ! $shop->przelewy24Configured()) {
            Log::channel('przelewy24')->warning('P24 pominięte: brak konfiguracji.', [
                'order_id' => $order->id,
                'shop_id' => $shop?->id,
            ]);

            return null;
        }

        if (mb_strlen((string) $order->buyer_email) > self::MAX_EMAIL_LENGTH) {
            Log::channel('przelewy24')->warning('P24 pominięte: e-mail kupującego dłuższy niż 50 znaków.', [
                'order_id' => $order->id,
            ]);

            return null;
        }

        $sessionId = 'kramio-'.$order->id.'-'.Str::lower(Str::random(12));
        $amount = $this->grosze($order->total_gross);
        $merchantId = (int) $shop->przelewy24MerchantId();

        $body = [
            'merchantId' => $merchantId,
            'posId' => (int) $shop->przelewy24PosId(),
            'sessionId' => $sessionId,
            'amount' => $amount,
            'currency' => 'PLN',
            'description' => Str::limit('Zamówienie #'.$order->number.' — '.$shop->name, 1000, ''),
            'email' => (string) $order->buyer_email,
            'country' => 'PL',
            'language' => 'pl',
            'urlReturn' => $returnUrl,
            'urlStatus' => $statusUrl,
            'sign' => $this->registerSign($sessionId, $merchantId, $amount, 'PLN', (string) $shop->przelewy24CrcKey()),
        ];

        Log::channel('przelewy24')->info('P24: rejestracja transakcji.', [
            'order_id' => $order->id,
            'shop_id' => $shop->id,
            'session_id' => $sessionId,
            'amount' => $amount,
            'environment' => $shop->przelewy24Environment(),
        ]);

        try {
            $response = $this->client($shop)->post($this->url($shop, '/api/v1/transaction/register'), $body);
        } catch (\Throwable $e) {
            Log::channel('przelewy24')->error('P24: wyjątek połączenia przy rejestracji.', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        $token = $response->json('data.token');

        if (! $response->successful() || blank($token)) {
            Log::channel('przelewy24')->error('P24: nieoczekiwana odpowiedź przy rejestracji.', [
                'order_id' => $order->id,
                'http_status' => $response->status(),
                'body' => $response->json(),
            ]);

            return null;
        }

        Log::channel('przelewy24')->info('P24: transakcja zarejestrowana.', [
            'order_id' => $order->id,
            'session_id' => $sessionId,
        ]);

        return [
            'sessionId' => $sessionId,
            'token' => (string) $token,
            'redirectUrl' => $this->url($shop, '/trnRequest/'.$token),
        ];
    }

    /**
     * Czy powiadomienie pochodzi od P24 dla TEGO sklepu: podpis zgodny ORAZ
     * Merchant/POS ID zgadzają się z konfiguracją sklepu. To filtr wstępny —
     * potwierdzeniem zapłaty jest dopiero udane `verifyTransaction()`.
     *
     * @param  array<string, mixed>  $payload  zdekodowane ciało powiadomienia
     */
    public function isAuthenticNotification(Shop $shop, array $payload): bool
    {
        $crc = $shop->przelewy24CrcKey();

        if (blank($crc) || blank($payload['sign'] ?? null) || ! is_string($payload['sign'])) {
            return false;
        }

        foreach (['merchantId', 'posId', 'sessionId', 'amount', 'originAmount', 'currency', 'orderId', 'methodId', 'statement'] as $field) {
            if (! array_key_exists($field, $payload)) {
                return false;
            }
        }

        if ((int) $payload['merchantId'] !== $shop->przelewy24MerchantId()
            || (int) $payload['posId'] !== $shop->przelewy24PosId()) {
            return false;
        }

        $expected = $this->notificationSign(
            (int) $payload['merchantId'],
            (int) $payload['posId'],
            (string) $payload['sessionId'],
            (int) $payload['amount'],
            (int) $payload['originAmount'],
            (string) $payload['currency'],
            (int) $payload['orderId'],
            (int) $payload['methodId'],
            (string) $payload['statement'],
            $crc,
        );

        return hash_equals($expected, $payload['sign']);
    }

    /**
     * Weryfikuje (zamyka) transakcję w P24 — `PUT /api/v1/transaction/verify`.
     * Dopiero `true` znaczy „pieniądze są sprzedawcy". `$amount` podaje wołający
     * Z NASZEGO ZAMÓWIENIA, nie z powiadomienia: gdyby kwoty się różniły, P24
     * odrzuci weryfikację i nie uznamy niedopłaty za zapłatę.
     */
    public function verifyTransaction(Shop $shop, string $sessionId, int $p24OrderId, int $amount, string $currency = 'PLN'): bool
    {
        if (! $shop->przelewy24Configured()) {
            return false;
        }

        $body = [
            'merchantId' => (int) $shop->przelewy24MerchantId(),
            'posId' => (int) $shop->przelewy24PosId(),
            'sessionId' => $sessionId,
            'amount' => $amount,
            'currency' => $currency,
            'orderId' => $p24OrderId,
            'sign' => $this->verifySign($sessionId, $p24OrderId, $amount, $currency, (string) $shop->przelewy24CrcKey()),
        ];

        try {
            $response = $this->client($shop)->put($this->url($shop, '/api/v1/transaction/verify'), $body);
        } catch (\Throwable $e) {
            Log::channel('przelewy24')->error('P24: wyjątek połączenia przy weryfikacji.', [
                'session_id' => $sessionId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }

        $ok = $response->successful() && $response->json('data.status') === 'success';

        Log::channel('przelewy24')->log($ok ? 'info' : 'error', 'P24: weryfikacja transakcji.', [
            'shop_id' => $shop->id,
            'session_id' => $sessionId,
            'p24_order_id' => $p24OrderId,
            'amount' => $amount,
            'ok' => $ok,
            'http_status' => $response->status(),
            'body' => $ok ? null : $response->json(),
        ]);

        return $ok;
    }

    /**
     * Podpis żądania rejestracji: {sessionId, merchantId, amount, currency, crc}.
     */
    public function registerSign(string $sessionId, int $merchantId, int $amount, string $currency, string $crc): string
    {
        return $this->hash([
            'sessionId' => $sessionId,
            'merchantId' => $merchantId,
            'amount' => $amount,
            'currency' => $currency,
            'crc' => $crc,
        ]);
    }

    /**
     * Podpis żądania weryfikacji: {sessionId, orderId, amount, currency, crc}.
     */
    public function verifySign(string $sessionId, int $orderId, int $amount, string $currency, string $crc): string
    {
        return $this->hash([
            'sessionId' => $sessionId,
            'orderId' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
            'crc' => $crc,
        ]);
    }

    /**
     * Podpis powiadomienia: {merchantId, posId, sessionId, amount, originAmount,
     * currency, orderId, methodId, statement, crc}.
     */
    public function notificationSign(int $merchantId, int $posId, string $sessionId, int $amount, int $originAmount, string $currency, int $orderId, int $methodId, string $statement, string $crc): string
    {
        return $this->hash([
            'merchantId' => $merchantId,
            'posId' => $posId,
            'sessionId' => $sessionId,
            'amount' => $amount,
            'originAmount' => $originAmount,
            'currency' => $currency,
            'orderId' => $orderId,
            'methodId' => $methodId,
            'statement' => $statement,
            'crc' => $crc,
        ]);
    }

    /**
     * Kwota zamówienia w groszach — ta sama, którą rejestrujemy i weryfikujemy.
     * Zaokrąglenie przed rzutowaniem, żeby grosz nie uciekł na błędzie float.
     */
    public function grosze(string|float $gross): int
    {
        return (int) round(((float) $gross) * 100);
    }

    /**
     * @param  array<string, int|string>  $fields  kolejność kluczy ma znaczenie
     */
    private function hash(array $fields): string
    {
        return hash('sha384', json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function client(Shop $shop): PendingRequest
    {
        return Http::withBasicAuth((string) $shop->przelewy24PosId(), (string) $shop->przelewy24ApiKey())
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }

    private function url(Shop $shop, string $path): string
    {
        return rtrim((string) config('services.przelewy24.base_url.'.$shop->przelewy24Environment()), '/').$path;
    }
}
