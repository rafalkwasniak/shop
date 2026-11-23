<?php

namespace Tests\Feature\Storefront;

use App\Enums\IntegrationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Livewire\Checkout;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Żywa ścieżka płatności online: kasa oferuje metodę tylko gdy sklep ją włączył,
 * ekran podsumowania niesie przycisk „Przejdź do płatności" dla nieopłaconego
 * zamówienia online, a trasa inicjująca tworzy płatność w Paynow i przekierowuje
 * kupującego. Kontrakt z operatorem jest zaślepiony — sprawdzamy naszą stronę.
 */
class OnlinePaymentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function shopWithOnlinePayments(): Shop
    {
        $shop = Shop::factory()->withOnlinePayments()->create([
            'street' => 'Kwiatowa', 'building_number' => '5', 'postal_code' => '00-001',
            'city' => 'Warszawa', 'province' => 'mazowieckie',
            'pickup_enabled' => true, 'pay_on_pickup_enabled' => true,
        ]);
        $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => true,
            'config' => ['api_key' => 'API', 'signature_key' => 'SIGN', 'environment' => 'sandbox'],
        ]);

        return $shop->fresh();
    }

    private function base(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    public function test_checkout_offers_online_method_only_when_enabled(): void
    {
        $shop = $this->shopWithOnlinePayments();

        $options = Livewire::test(Checkout::class, ['shopId' => $shop->id])->instance()->paymentOptions();
        $this->assertArrayHasKey(Checkout::onlineOption(PaymentProvider::Paynow), $options);

        // Wyłączenie integracji zdejmuje metodę z kasy — sama konfiguracja nie wystarcza.
        $shop->integration(IntegrationType::Payments)->update(['enabled' => false]);
        $options = Livewire::test(Checkout::class, ['shopId' => $shop->fresh()->id])->instance()->paymentOptions();
        $this->assertArrayNotHasKey(Checkout::onlineOption(PaymentProvider::Paynow), $options);
    }

    public function test_checkout_hides_online_method_without_entitlement(): void
    {
        // Brama pakietu: sklep skonfigurowany i z włączoną integracją, ale bez
        // uprawnienia `online_payments` (np. po zejściu z pakietu) NIE oferuje
        // płatności online w kasie.
        $shop = $this->shopWithOnlinePayments();
        $shop->forceFill(['entitlements' => ['online_payments' => false]])->save();

        $this->assertFalse($shop->fresh()->onlinePaymentsEnabled());

        $options = Livewire::test(Checkout::class, ['shopId' => $shop->id])->instance()->paymentOptions();
        $this->assertArrayNotHasKey(Checkout::onlineOption(PaymentProvider::Paynow), $options);
    }

    public function test_confirmation_shows_pay_button_for_unpaid_online_order(): void
    {
        $shop = $this->shopWithOnlinePayments();
        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::AwaitingPayment,
            'payment_method' => PaymentMethod::Online,
        ]);

        $this->withSession(['recent_order_id' => $order->id])
            ->get($this->base($shop).'/kasa/dziekujemy')
            ->assertOk()
            ->assertSee('Przejdź do płatności');
    }

    public function test_confirmation_shows_paid_state_and_no_button_once_paid(): void
    {
        $shop = $this->shopWithOnlinePayments();
        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::Paid,
            'payment_method' => PaymentMethod::Online,
        ]);

        $this->withSession(['recent_order_id' => $order->id])
            ->get($this->base($shop).'/kasa/dziekujemy')
            ->assertOk()
            ->assertSee('Płatność potwierdzona')
            ->assertDontSee('Przejdź do płatności');
    }

    public function test_payment_page_shows_pay_button_for_unpaid_online_order(): void
    {
        $shop = $this->shopWithOnlinePayments();
        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::AwaitingPayment,
            'payment_method' => PaymentMethod::Online,
        ]);

        // Token niesie dostęp — bez sesji i bez logowania (jak z maila/gościa).
        $this->get($this->base($shop).'/platnosc/'.$order->paymentToken())
            ->assertOk()
            ->assertSee('To zamówienie czeka na opłacenie')
            ->assertSee('Zapłać');
    }

    public function test_payment_page_shows_paid_state_and_no_button(): void
    {
        $shop = $this->shopWithOnlinePayments();
        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::Paid,
            'payment_method' => PaymentMethod::Online,
        ]);

        $this->get($this->base($shop).'/platnosc/'.$order->paymentToken())
            ->assertOk()
            ->assertSee('Płatność potwierdzona')
            ->assertDontSee('To zamówienie czeka na opłacenie');
    }

    public function test_invalid_payment_token_redirects_home(): void
    {
        $shop = $this->shopWithOnlinePayments();

        $this->get($this->base($shop).'/platnosc/nie-jest-tokenem')
            ->assertRedirect($this->base($shop));
    }

    public function test_pay_creates_payment_and_redirects_to_paynow(): void
    {
        Http::fake(['*/v1/payments' => Http::response([
            'paymentId' => 'PAY-9', 'redirectUrl' => 'https://paynow.pl/go/9', 'status' => 'NEW',
        ], 201)]);

        $shop = $this->shopWithOnlinePayments();
        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::AwaitingPayment,
            'payment_method' => PaymentMethod::Online,
            'total_gross' => 50,
        ]);

        $this->post($this->base($shop).'/platnosc/'.$order->paymentToken())
            ->assertRedirect('https://paynow.pl/go/9');

        $order->refresh();
        $this->assertSame('PAY-9', $order->payment_external_id);
        $this->assertSame('NEW', $order->payment_status);
        // Zamówienie pamięta bramkę — przy dwóch operatorach strona płatności,
        // maile i webhook muszą wiedzieć, którym kupujący płaci.
        $this->assertSame(PaymentProvider::Paynow, $order->payment_provider);
    }

    public function test_order_from_before_expiry_can_still_be_paid(): void
    {
        // Decyzja Rafała: zamówienie złożone przed wygaśnięciem abonamentu musi
        // dać się dopłacić. Gdyby bramka zamknęła się z dnia na dzień, pieniądze
        // utknęłyby w pół drogi, a winnym byłby sklep.
        Http::fake(['*/v1/payments' => Http::response([
            'paymentId' => 'PAY-11', 'redirectUrl' => 'https://paynow.pl/go/11', 'status' => 'NEW',
        ], 201)]);

        $shop = $this->shopWithOnlinePayments();
        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::AwaitingPayment,
            'payment_method' => PaymentMethod::Online,
            'total_gross' => 50,
        ]);

        // Abonament wygasł po złożeniu zamówienia (poza karencją).
        $shop->forceFill([
            'package' => 'pavilion',
            'price_yearly' => 1500,
            'subscription_ends_at' => now()->subDays(60),
        ])->save();

        $this->assertFalse($shop->fresh()->onlinePaymentsEnabled(), 'nowych płatności już nie przyjmujemy');
        $this->assertTrue($shop->fresh()->canFinishOnlinePayment(), 'ale rozpoczętą trzeba dokończyć');

        $this->post($this->base($shop).'/platnosc/'.$order->paymentToken())
            ->assertRedirect('https://paynow.pl/go/11');
    }

    public function test_pay_refuses_non_online_order(): void
    {
        Http::fake();
        $shop = $this->shopWithOnlinePayments();
        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::New,
            'payment_method' => PaymentMethod::PayOnPickup,
        ]);
        $token = $order->paymentToken();

        $this->post($this->base($shop).'/platnosc/'.$token)
            ->assertRedirect($this->base($shop).'/platnosc/'.$token);

        Http::assertNothingSent();
    }

    public function test_invalid_token_pay_redirects_home(): void
    {
        Http::fake();
        $shop = $this->shopWithOnlinePayments();

        $this->post($this->base($shop).'/platnosc/nie-jest-tokenem')
            ->assertRedirect($this->base($shop));

        Http::assertNothingSent();
    }

    public function test_pay_falls_back_with_error_when_gateway_fails(): void
    {
        Http::fake(['*/v1/payments' => Http::response(['error' => 'nope'], 500)]);

        $shop = $this->shopWithOnlinePayments();
        $order = Order::factory()->for($shop)->create([
            'status' => OrderStatus::AwaitingPayment,
            'payment_method' => PaymentMethod::Online,
        ]);
        $token = $order->paymentToken();

        $this->post($this->base($shop).'/platnosc/'.$token)
            ->assertRedirect($this->base($shop).'/platnosc/'.$token)
            ->assertSessionHas('error');

        $this->assertNull($order->refresh()->payment_external_id);
    }
}
