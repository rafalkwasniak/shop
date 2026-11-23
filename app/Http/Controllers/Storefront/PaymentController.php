<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shop;
use App\Services\PaynowService;
use App\Services\Przelewy24Service;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Publiczna strona płatności zamówienia, dostępna po tokenie (zaszyfrowany
 * identyfikator zamówienia — patrz `Order::paymentToken()`). Jedno miejsce, do
 * którego kierują WSZYSTKIE ścieżki dokończenia płatności: mail, „Moje konto",
 * powrót z bramki i ekran podziękowania. Działa bez logowania — dla kupującego
 * z konta i gościa tak samo, bo prawo dostępu niesie sam token, nie sesja.
 *
 * Bramka: ta wybrana w kasie (`orders.payment_provider`). Gdy sprzedawca ją
 * w międzyczasie wyłączył, strona proponuje pozostałe aktywne — zamówienie nie
 * może zostać bez sposobu zapłaty. Jedyny styk z bramkami to zwrotnica w
 * `pay()`; każda ma własną usługę i webhook.
 *
 * Bezpieczeństwo: token rozszyfrowujemy do id, a zamówienie i tak scope'ujemy do
 * sklepu z subdomeny (`shop()->orders()`), więc token jednego sklepu nie sięgnie
 * zamówienia innego. Zapłata i tak tylko zasila sprzedawcę — nie ma czym szkodzić.
 */
class PaymentController extends Controller
{
    public function show(Request $request, string $token): View|RedirectResponse
    {
        $order = $this->resolve($request, $token);

        if ($order === null) {
            return redirect()->to('/');
        }

        $shop = $request->attributes->get('shop');

        return view('storefront.payment', [
            'shop' => $shop,
            'order' => $order,
            'providers' => $this->providersFor($order, $shop),
        ]);
    }

    /**
     * Rozpoczyna płatność online wybraną bramką i przekierowuje do niej. Zapisuje
     * bramkę i identyfikator próby (po nim lub po sesji P24 webhook odnajdzie
     * zamówienie). Powrót wraca na tę stronę, a o zapłacie rozstrzyga webhook.
     * Płacimy tylko za nieopłacone zamówienie online przy realnie dostępnej
     * bramce — inaczej wracamy na stronę bez akcji.
     */
    public function pay(Request $request, string $token, PaynowService $paynow, Przelewy24Service $p24): RedirectResponse
    {
        $order = $this->resolve($request, $token);

        if ($order === null) {
            return redirect()->to('/');
        }

        $shop = $request->attributes->get('shop');
        $back = '/platnosc/'.$token;
        $providers = $this->providersFor($order, $shop);

        // Przycisk konkretnej bramki (gdy strona proponuje kilka) — tylko spośród
        // tych, które strona sama zaproponowała.
        $requested = PaymentProvider::tryFrom((string) $request->input('provider'));
        $provider = $requested !== null && in_array($requested, $providers, true) ? $requested : ($providers[0] ?? null);

        if (! $order->isAwaitingOnlinePayment() || $provider === null) {
            return redirect()->to($back);
        }

        $returnUrl = $request->getSchemeAndHttpHost().$back;

        $attempt = match ($provider) {
            PaymentProvider::Paynow => $this->startPaynow($paynow, $order, $returnUrl),
            PaymentProvider::Przelewy24 => $this->startPrzelewy24($p24, $order, $shop, $returnUrl),
        };

        if ($attempt === null) {
            $message = $provider === PaymentProvider::Przelewy24
                && mb_strlen((string) $order->buyer_email) > Przelewy24Service::MAX_EMAIL_LENGTH
                ? 'Przelewy24 przyjmują adres e-mail do '.Przelewy24Service::MAX_EMAIL_LENGTH.' znaków, a ten jest dłuższy. Skontaktuj się ze sklepem, aby opłacić zamówienie inaczej.'
                : 'Nie udało się rozpocząć płatności. Spróbuj ponownie za chwilę.';

            return redirect()->to($back)->with('error', $message);
        }

        $order->forceFill([
            'payment_provider' => $provider,
            'payment_external_id' => $attempt['externalId'],
            'payment_status' => $attempt['status'],
        ])->save();

        return redirect()->away($attempt['redirectUrl']);
    }

    /**
     * @return array{externalId: string, status: string, redirectUrl: string}|null
     */
    private function startPaynow(PaynowService $paynow, Order $order, string $returnUrl): ?array
    {
        $payment = $paynow->createPayment($order, $returnUrl);

        return $payment === null ? null : [
            'externalId' => $payment['paymentId'],
            'status' => $payment['status'],
            'redirectUrl' => $payment['redirectUrl'],
        ];
    }

    /**
     * @return array{externalId: string, status: string, redirectUrl: string}|null
     */
    private function startPrzelewy24(Przelewy24Service $p24, Order $order, Shop $shop, string $returnUrl): ?array
    {
        $transaction = $p24->registerTransaction($order, $returnUrl, $shop->przelewy24WebhookUrl());

        return $transaction === null ? null : [
            'externalId' => $transaction['sessionId'],
            'status' => 'REGISTERED',
            'redirectUrl' => $transaction['redirectUrl'],
        ];
    }

    /**
     * Bramki, którymi można teraz opłacić to zamówienie. Bramka z kasy, jeśli
     * nadal działa — wtedy tylko ona (klient wybrał świadomie). Inaczej wszystkie
     * dostępne, żeby wyłączenie jednej nie zostawiło zamówienia bez wyjścia.
     * Liczone jak dla zamówień złożonych wcześniej (patrz
     * `Shop::paymentProviderReady()`): dopłata działa także po wygaśnięciu pakietu.
     *
     * @return list<PaymentProvider>
     */
    private function providersFor(Order $order, Shop $shop): array
    {
        $payable = $shop->payableProvidersForUnfinishedOrders();

        if ($order->payment_provider !== null && in_array($order->payment_provider, $payable, true)) {
            return [$order->payment_provider];
        }

        return $payable;
    }

    /**
     * Zamówienie spod tokenu, scope'owane do sklepu z subdomeny. null, gdy token
     * uszkodzony/podrobiony albo wskazuje zamówienie spoza tego sklepu.
     */
    private function resolve(Request $request, string $token): ?Order
    {
        $shop = $request->attributes->get('shop');
        $id = Order::decodePaymentToken($token);

        return $id !== null ? $shop->orders()->with('items')->find($id) : null;
    }
}
