<?php

namespace Tests\Feature\Storefront;

use App\Enums\DeliveryMethod;
use App\Enums\IntegrationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\ShopStatus;
use App\Livewire\Checkout;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kilka bramek naraz (decyzja Rafała 2026-11-23): kasa pokazuje każdą aktywną
 * bramkę jako osobną opcję, zamówienie zapamiętuje wybraną, a strona płatności
 * kieruje do niej — albo, gdy sprzedawca ją wyłączył, do pozostałych.
 */
class MultiGatewayCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function shop(bool $paynow = true, bool $przelewy24 = true): Shop
    {
        $shop = Shop::factory()->withOnlinePayments()->create([
            'street' => 'Kwiatowa', 'building_number' => '5', 'postal_code' => '00-001',
            'city' => 'Warszawa', 'province' => 'mazowieckie',
            'pickup_enabled' => true, 'pay_on_pickup_enabled' => true,
        ]);

        $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => $paynow,
            'config' => ['api_key' => 'API', 'signature_key' => 'SIGN', 'environment' => 'sandbox'],
        ]);
        $shop->integrations()->create([
            'type' => IntegrationType::Przelewy24,
            'enabled' => $przelewy24,
            'config' => ['merchant_id' => 431164, 'pos_id' => null, 'crc_key' => 'CRC', 'api_key' => 'API', 'environment' => 'sandbox'],
        ]);

        return $shop->fresh();
    }

    private function base(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    private function filledCheckout(Shop $shop, string $email = 'jan@example.com'): Testable
    {
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null, 'price_gross' => 40.00,
        ]);
        app(CartService::class)->add($product, 1);

        return Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', $email)
            ->set('buyer_phone', '123456789')
            ->set('delivery_method', 'pickup')
            ->set('accept_terms', true)
            ->set('accept_privacy', true);
    }

    private function awaitingOrder(Shop $shop, ?PaymentProvider $provider): Order
    {
        return Order::factory()->for($shop)->create([
            'status' => OrderStatus::AwaitingPayment,
            'payment_method' => PaymentMethod::Online,
            'payment_provider' => $provider,
            'total_gross' => 40,
            'buyer_email' => 'jan@example.com',
        ]);
    }

    // --- Kasa -----------------------------------------------------------

    public function test_checkout_offers_each_active_gateway_separately(): void
    {
        $options = Livewire::test(Checkout::class, ['shopId' => $this->shop()->id])->instance()->paymentOptions();

        $this->assertSame('Płatność online Paynow', $options['online:paynow']);
        $this->assertSame('Płatność online Przelewy24', $options['online:przelewy24']);
    }

    public function test_disabled_gateway_disappears_from_checkout(): void
    {
        $options = Livewire::test(Checkout::class, ['shopId' => $this->shop(paynow: false)->id])->instance()->paymentOptions();

        $this->assertArrayNotHasKey('online:paynow', $options);
        $this->assertArrayHasKey('online:przelewy24', $options);
    }

    public function test_order_remembers_the_chosen_gateway(): void
    {
        $shop = $this->shop();

        $this->filledCheckout($shop)
            ->set('payment_method', 'online:przelewy24')
            ->call('place')
            ->assertHasNoErrors()
            ->assertRedirect('/kasa/dziekujemy');

        $order = Order::sole();
        $this->assertSame(PaymentMethod::Online, $order->payment_method);
        $this->assertSame(PaymentProvider::Przelewy24, $order->payment_provider);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
    }

    public function test_offline_methods_have_no_gateway(): void
    {
        $shop = $this->shop();

        $this->filledCheckout($shop)
            ->set('payment_method', 'pay_on_pickup')
            ->call('place')
            ->assertHasNoErrors();

        $this->assertNull(Order::sole()->payment_provider);
    }

    public function test_too_long_email_is_stopped_in_checkout_only_for_przelewy24(): void
    {
        $shop = $this->shop();
        $email = str_repeat('a', 45).'@example.com';

        $this->filledCheckout($shop, $email)
            ->set('payment_method', 'online:przelewy24')
            ->call('place')
            ->assertHasErrors('buyer_email');
        $this->assertSame(0, Order::count());

        // Ten sam adres przy Paynow przechodzi — ograniczenie jest bramki, nie sklepu.
        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_name', 'Jan')->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', $email)->set('buyer_phone', '123456789')
            ->set('delivery_method', 'pickup')->set('payment_method', 'online:paynow')
            ->set('accept_terms', true)->set('accept_privacy', true)
            ->call('place')
            ->assertHasNoErrors();
    }

    public function test_returning_customer_gets_last_gateway_preselected(): void
    {
        // Paynow jest pierwszy na liście (domyślne zaznaczenie) — podpowiedź
        // z ostatniego zamówienia musi go przebić, inaczej test nic nie mówi.
        $shop = $this->shop();
        $customer = Customer::factory()->for($shop)->create(['email' => 'ala@example.com']);
        Order::factory()->for($shop)->for($customer)->create([
            'delivery_method' => DeliveryMethod::Pickup,
            'payment_method' => PaymentMethod::Online,
            'payment_provider' => PaymentProvider::Przelewy24,
        ]);

        $this->actingAs($customer, 'customer');

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->assertSet('payment_method', 'online:przelewy24');
    }

    public function test_last_gateway_switched_off_falls_back_to_available_option(): void
    {
        $shop = $this->shop(przelewy24: false);
        $customer = Customer::factory()->for($shop)->create(['email' => 'ala@example.com']);
        Order::factory()->for($shop)->for($customer)->create([
            'delivery_method' => DeliveryMethod::Pickup,
            'payment_method' => PaymentMethod::Online,
            'payment_provider' => PaymentProvider::Przelewy24,
        ]);

        $this->actingAs($customer, 'customer');

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->assertSet('payment_method', 'online:paynow');
    }

    public function test_product_page_names_the_active_gateways(): void
    {
        $shop = $this->shop();
        $shop->forceFill(['status' => ShopStatus::Active])->save();
        $product = Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true]);

        $this->get($this->base($shop).$product->storefrontPath())
            ->assertOk()
            ->assertSee('Płatność online (Paynow lub Przelewy24)');
    }

    // --- Strona płatności -------------------------------------------------

    public function test_pay_sends_przelewy24_order_to_przelewy24(): void
    {
        Http::fake(['sandbox.przelewy24.pl/api/v1/transaction/register' => Http::response(['data' => ['token' => 'TOK-9']])]);
        $shop = $this->shop();
        $order = $this->awaitingOrder($shop, PaymentProvider::Przelewy24);

        $this->get($this->base($shop).'/platnosc/'.$order->paymentToken())
            ->assertSee('Zapłać przez Przelewy24')
            ->assertDontSee('Zapłać przez Paynow');

        $this->post($this->base($shop).'/platnosc/'.$order->paymentToken())
            ->assertRedirect('https://sandbox.przelewy24.pl/trnRequest/TOK-9');

        $order->refresh();
        $this->assertSame(PaymentProvider::Przelewy24, $order->payment_provider);
        $this->assertStringStartsWith('kramio-'.$order->id.'-', $order->payment_external_id);
        $this->assertSame('REGISTERED', $order->payment_status);

        // Powiadomienie ma przyjść na host sklepu, powrót — na stronę płatności
        // (token jest szyfrowany z losowym IV, więc porównujemy odszyfrowany).
        Http::assertSent(fn (Request $r) => $r['urlStatus'] === $shop->przelewy24WebhookUrl()
            && preg_match('#/platnosc/([^/]+)$#', $r['urlReturn'], $m) === 1
            && Order::decodePaymentToken($m[1]) === $order->id);
    }

    public function test_pay_sends_paynow_order_to_paynow(): void
    {
        Http::fake(['*/v1/payments' => Http::response(['paymentId' => 'PAY-1', 'redirectUrl' => 'https://paynow.pl/go/1', 'status' => 'NEW'], 201)]);
        $shop = $this->shop();
        $order = $this->awaitingOrder($shop, PaymentProvider::Paynow);

        $this->post($this->base($shop).'/platnosc/'.$order->paymentToken())
            ->assertRedirect('https://paynow.pl/go/1');

        $this->assertSame(PaymentProvider::Paynow, $order->refresh()->payment_provider);
    }

    public function test_when_chosen_gateway_was_switched_off_the_page_offers_the_others(): void
    {
        Http::fake(['*/v1/payments' => Http::response(['paymentId' => 'PAY-2', 'redirectUrl' => 'https://paynow.pl/go/2', 'status' => 'NEW'], 201)]);
        $shop = $this->shop(przelewy24: false);
        $order = $this->awaitingOrder($shop, PaymentProvider::Przelewy24);

        $this->get($this->base($shop).'/platnosc/'.$order->paymentToken())
            ->assertSee('Zapłać przez Paynow')
            ->assertDontSee('Zapłać przez Przelewy24');

        $this->post($this->base($shop).'/platnosc/'.$order->paymentToken(), ['provider' => 'paynow'])
            ->assertRedirect('https://paynow.pl/go/2');

        $this->assertSame(PaymentProvider::Paynow, $order->refresh()->payment_provider);
    }

    public function test_gateway_not_offered_on_the_page_cannot_be_forced(): void
    {
        // Bramka z kasy działa — strona proponuje tylko ją. Podmiana pola
        // `provider` w formularzu nie przekieruje do innej.
        Http::fake(['sandbox.przelewy24.pl/*' => Http::response(['data' => ['token' => 'T']])]);
        $shop = $this->shop();
        $order = $this->awaitingOrder($shop, PaymentProvider::Przelewy24);

        $this->post($this->base($shop).'/platnosc/'.$order->paymentToken(), ['provider' => 'paynow'])
            ->assertRedirect('https://sandbox.przelewy24.pl/trnRequest/T');
    }

    public function test_page_explains_when_no_gateway_is_left(): void
    {
        Http::fake();
        $shop = $this->shop(paynow: false, przelewy24: false);
        $order = $this->awaitingOrder($shop, PaymentProvider::Przelewy24);

        $this->get($this->base($shop).'/platnosc/'.$order->paymentToken())
            ->assertSee('Płatność online jest w tym sklepie chwilowo niedostępna')
            ->assertDontSee('Zapłać przez');

        $token = $order->paymentToken();
        $this->post($this->base($shop).'/platnosc/'.$token)
            ->assertRedirectContains('/platnosc/'.$token);
        Http::assertNothingSent();
    }

    public function test_order_without_remembered_gateway_uses_first_available(): void
    {
        // Zamówienia sprzed bramek w kolumnie (lub złożone z pominięciem kasy).
        Http::fake(['*/v1/payments' => Http::response(['paymentId' => 'PAY-3', 'redirectUrl' => 'https://paynow.pl/go/3', 'status' => 'NEW'], 201)]);
        $shop = $this->shop();
        $order = $this->awaitingOrder($shop, null);

        $this->post($this->base($shop).'/platnosc/'.$order->paymentToken())
            ->assertRedirect('https://paynow.pl/go/3');
    }
}
