<?php

namespace App\Http\Controllers;

use App\Enums\IntegrationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Models\Order;
use App\Services\OrderStatusChanger;
use App\Services\Przelewy24Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Powiadomienia Przelewy24 o opłaconej transakcji (`urlStatus`). P24 wysyła je
 * WYŁĄCZNIE dla opłaconych transakcji i ponawia (3, 5, 15 … 450 min), dopóki nie
 * zweryfikujemy transakcji. Samo powiadomienie NIE jest potwierdzeniem —
 * dopiero udane `PUT /transaction/verify` przekazuje pieniądze sprzedawcy.
 *
 * Kolejność:
 *  1. Zamówienie z `sessionId` (`kramio-{id}-{los}`) — id siedzi w sesji, więc
 *     trafiamy także w starszą próbę płatności (klient otworzył dwie karty
 *     i zapłacił w pierwszej). Sesja służy tylko do znalezienia sklepu i klucza.
 *  2. Podpis + Merchant/POS ID kluczem CRC TEGO sklepu — bez niego nie ufamy
 *     niczemu z ciała.
 *  3. Kwota z powiadomienia musi równać się kwocie zamówienia — inaczej NIE
 *     weryfikujemy (niedopłata nie może przejść jako zapłata).
 *  4. Weryfikacja w P24 kwotą z NASZEGO zamówienia.
 *  5. „Opłacone" tylko z „Oczekuje na płatność" (idempotencja), pod blokadą
 *     wiersza — dwa równoległe powiadomienia nie wyślą dwóch maili.
 *
 * Weryfikujemy też, gdy zamówienie nie czeka już na płatność (np. anulowane w
 * międzyczasie): bez weryfikacji pieniądze klienta utknęłyby w P24 jako
 * przedpłata, a tak trafiają do sprzedawcy, który może je zwrócić — tak samo
 * jak przy Paynow, gdzie zapłata zawsze wpływa.
 */
class Przelewy24WebhookController extends Controller
{
    public function __invoke(Request $request, Przelewy24Service $p24, OrderStatusChanger $changer): JsonResponse
    {
        $payload = $request->json()->all();
        $sessionId = $payload['sessionId'] ?? null;

        if (! is_string($sessionId) || preg_match('/^kramio-(\d+)-[a-z0-9]+$/', $sessionId, $matches) !== 1) {
            Log::channel('przelewy24')->warning('Webhook: nierozpoznana sesja.', ['session_id' => $sessionId]);

            return response()->json(['ok' => false], 400);
        }

        $order = Order::with('shop')->find((int) $matches[1]);

        if ($order === null || $order->shop === null) {
            // Np. powiadomienie z innej instancji (stary serwer) — nic tu po nas.
            Log::channel('przelewy24')->warning('Webhook: nieznane zamówienie.', ['session_id' => $sessionId]);

            return response()->json(['ok' => false], 404);
        }

        $shop = $order->shop;

        if (! $p24->isAuthenticNotification($shop, $payload)) {
            Log::channel('przelewy24')->error('Webhook: błędny podpis lub obce konto.', [
                'session_id' => $sessionId,
                'order_id' => $order->id,
            ]);

            return response()->json(['ok' => false], 400);
        }

        $expected = $p24->grosze($order->total_gross);

        if ((int) $payload['amount'] !== $expected || $payload['currency'] !== 'PLN') {
            Log::channel('przelewy24')->error('Webhook: kwota niezgodna z zamówieniem — bez weryfikacji.', [
                'session_id' => $sessionId,
                'order_id' => $order->id,
                'expected' => $expected,
                'amount' => $payload['amount'],
                'currency' => $payload['currency'],
            ]);

            return response()->json(['ok' => false], 400);
        }

        if (! $p24->verifyTransaction($shop, $sessionId, (int) $payload['orderId'], $expected)) {
            // 500 = „spróbuj później": P24 ponowi powiadomienie, a my weryfikację.
            return response()->json(['ok' => false], 500);
        }

        $paid = DB::transaction(function () use ($order, $sessionId, $changer): bool {
            $order = Order::lockForUpdate()->find($order->id);

            $order->forceFill([
                'payment_provider' => PaymentProvider::Przelewy24,
                'payment_external_id' => $sessionId,
                'payment_status' => 'VERIFIED',
            ])->save();

            if ($order->status !== OrderStatus::AwaitingPayment) {
                return false;
            }

            // Ta sama droga co ręczna zmiana w panelu: oś czasu + mail „Opłacone".
            $changer->change($order, OrderStatus::Paid, 'Płatność online potwierdzona (Przelewy24).');

            return true;
        });

        // Auto-FV według zgody zapisanej na wierszu TEJ bramki. `requestInvoice()`
        // sam pilnuje reszty (Fakturownia włączona, uprawnienie, brak dubla).
        if ($paid && $shop->integration(IntegrationType::Przelewy24)?->autoInvoiceAfterPayment()) {
            $order->refresh()->requestInvoice();
        }

        Log::channel('przelewy24')->info('Webhook: przetworzony.', [
            'session_id' => $sessionId,
            'order_id' => $order->id,
            'marked_paid' => $paid,
        ]);

        return response()->json(['ok' => true]);
    }
}
