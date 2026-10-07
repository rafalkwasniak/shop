<?php

namespace Tests\Feature\Loyalty;

use App\Enums\DeliveryMethod;
use App\Enums\LoyaltyEntryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\VatRate;
use App\Exceptions\CartNeedsReviewException;
use App\Livewire\Cart;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\EmailMessage;
use App\Models\LoyaltyEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use App\Services\FakturowniaService;
use App\Services\LoyaltyLedger;
use App\Services\OrderEditor;
use App\Services\OrderReturnService;
use App\Services\OrderService;
use App\Services\OrderStatusChanger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Krok C: płacenie punktami. Reguły: kwota z koszyka = kwota w kasie = kwota
 * na zamówieniu; po punktach zostaje co najmniej loyalty.min_payable (1 zł);
 * punkty schodzą z salda w transakcji zamówienia; przy zwrocie części albo
 * edycji w dół punkty za tę część wracają (decyzje Rafała 07.10).
 */
class LoyaltyRedemptionTest extends TestCase
{
    use RefreshDatabase;

    private LoyaltyLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(LoyaltyLedger::class);
    }

    private function shop(array $settings = []): Shop
    {
        return Shop::factory()->withCourierShipping()->withDiscountCodes()->withLoyalty($settings)->create([
            'courier_enabled' => true,
            'courier_cost' => 20,
            'bank_transfer_enabled' => true,
            'bank_account_number' => '11111111111111111111111111',
        ]);
    }

    private function product(Shop $shop, float $price, VatRate $vat = VatRate::R23, string $name = 'Lemoniada'): Product
    {
        return Product::factory()->create([
            'shop_id' => $shop->id, 'name' => $name, 'price_gross' => $price, 'vat_rate' => $vat, 'stock' => 10,
        ]);
    }

    private function customer(Shop $shop, int $points = 0): Customer
    {
        $customer = Customer::factory()->for($shop)->create(['email' => 'anna@example.com']);

        if ($points > 0) {
            $this->ledger->adjust($shop, 'anna@example.com', $points);
        }

        return $customer;
    }

    /** @return array<string, mixed> */
    private function orderData(): array
    {
        return [
            'buyer_name' => 'Anna', 'buyer_surname' => 'Kowalska', 'buyer_email' => 'anna@example.com',
            'buyer_phone' => '500600700',
            'delivery_method' => DeliveryMethod::Courier->value,
            'payment_method' => PaymentMethod::BankTransfer->value,
            'ship_street' => 'Kwiatowa', 'ship_building_number' => '5', 'ship_postal_code' => '00-001', 'ship_city' => 'Warszawa',
        ];
    }

    private function placeWithPoints(Shop $shop, Customer $customer, Product ...$products): Order
    {
        foreach ($products as $product) {
            app(CartService::class)->add($product, 1);
        }
        app(CartService::class)->usePoints($shop->id);

        return app(OrderService::class)->place($shop, $this->orderData(), $customer);
    }

    // ── Ile punktów wolno użyć ──────────────────────────────────────────────

    public function test_whole_balance_is_used_when_the_cart_allows(): void
    {
        $shop = $this->shop();
        $this->customer($shop, 1094);

        $r = $this->ledger->redeemable($shop, 'anna@example.com', 39.80);

        $this->assertSame(1094, $r->points);
        $this->assertSame(10.94, $r->amount);
    }

    public function test_at_least_one_zloty_stays_to_pay(): void
    {
        $shop = $this->shop();
        $this->customer($shop, 5000);

        $r = $this->ledger->redeemable($shop, 'anna@example.com', 20.00);

        $this->assertSame(1900, $r->points);
        $this->assertSame(19.00, $r->amount);
    }

    public function test_shop_percentage_limit_applies(): void
    {
        $shop = $this->shop(['loyalty_max_redeem_percent' => 30]);
        $this->customer($shop, 50000);

        $this->assertSame(3000, $this->ledger->redeemable($shop, 'anna@example.com', 100.00)->points);
    }

    public function test_reasons_when_points_cannot_be_used(): void
    {
        $shop = $this->shop(['loyalty_min_redeem_points' => 500]);
        $this->customer($shop, 300);

        $this->assertSame('minimum', $this->ledger->redeemable($shop, 'anna@example.com', 100)->reason);
        $this->assertSame('empty', $this->ledger->redeemable($shop, 'nikt@example.com', 100)->reason);

        $shop->update(['loyalty_min_redeem_points' => null]);
        $this->assertSame('cart', $this->ledger->redeemable($shop->fresh(), 'anna@example.com', 0.50)->reason);
    }

    // ── Koszyk ─────────────────────────────────────────────────────────────

    public function test_cart_lets_a_logged_in_customer_use_points(): void
    {
        $shop = $this->shop();
        $customer = $this->customer($shop, 1094);
        app(CartService::class)->add($this->product($shop, 39.80), 1);
        $this->actingAs($customer, 'customer');

        Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->assertSee('Masz')
            ->assertSee('Wykorzystaj 1094 pkt')
            ->assertDontSee('−10,94 zł')
            ->call('usePoints')
            ->assertSee('Wykorzystujesz')
            ->assertSee('Pozostanie: <strong>0 pkt</strong>.', false)
            ->assertSee('−10,94 zł')
            ->assertSee('28,86 zł')
            ->call('stopUsingPoints')
            ->assertSee('39,80 zł');
    }

    public function test_guest_is_invited_to_log_in(): void
    {
        $shop = $this->shop();
        app(CartService::class)->add($this->product($shop, 39.80), 1);

        Livewire::test(Cart::class, ['shopId' => $shop->id])
            ->assertSee('Masz punkty?')
            ->assertDontSee('Wykorzystaj');
    }

    // ── Zamówienie ─────────────────────────────────────────────────────────

    public function test_order_is_paid_partly_with_points(): void
    {
        $shop = $this->shop();
        $customer = $this->customer($shop, 1094);

        $order = $this->placeWithPoints($shop, $customer, $this->product($shop, 39.80));

        $this->assertSame(10.94, (float) $order->points_discount);
        $this->assertSame(48.86, (float) $order->total_gross); // 39,80 − 10,94 + 20 dostawy
        $this->assertSame(0, $this->ledger->balance($shop, 'anna@example.com'));
        $this->assertSame(1094, $this->ledger->spentOn($order));
        $this->assertFalse(app(CartService::class)->usesPoints($shop->id));

        $mail = EmailMessage::where('to_email', 'anna@example.com')->latest('id')->first();
        $this->assertStringContainsString('Punkty: −10,94 zł', json_encode($mail->intro_lines, JSON_UNESCAPED_UNICODE));
    }

    public function test_points_combine_with_a_discount_code_after_it(): void
    {
        $shop = $this->shop();
        $customer = $this->customer($shop, 10000);
        DiscountCode::factory()->create(['shop_id' => $shop->id, 'code' => 'LATO10', 'value' => 10]);
        app(CartService::class)->setDiscountCode($shop->id, 'LATO10');

        $order = $this->placeWithPoints($shop, $customer, $this->product($shop, 100));

        // 100 − 10 (kod) = 90; punkty do 89 zł, żeby został 1 zł.
        $this->assertSame(10.00, (float) $order->discount_amount);
        $this->assertSame(89.00, (float) $order->points_discount);
        $this->assertSame(21.00, (float) $order->total_gross);
    }

    public function test_points_are_spread_over_vat_rates_and_land_on_the_invoice(): void
    {
        $shop = $this->shop();
        $customer = $this->customer($shop, 10000);

        $order = $this->placeWithPoints($shop, $customer,
            $this->product($shop, 75, VatRate::R23, 'Rower'),
            $this->product($shop, 25, VatRate::R5, 'Bidon'),
        );

        $payload = app(FakturowniaService::class)->buildInvoicePayload($order->fresh('items'))['invoice'];
        $positions = collect($payload['positions']);

        // 99 zł punktami rozłożone 3:1 na pozycje — VAT liczy się od ceny po rabacie.
        $this->assertSame(0.75, $positions->firstWhere('name', 'Rower')['total_price_gross']);
        $this->assertSame(0.25, $positions->firstWhere('name', 'Bidon')['total_price_gross']);
        $this->assertStringContainsString('rabat za punkty: 99,00 zł', $payload['description']);
    }

    public function test_order_stops_when_points_were_spent_elsewhere_meanwhile(): void
    {
        $shop = $this->shop();
        $customer = $this->customer($shop, 1094);
        app(CartService::class)->add($this->product($shop, 39.80), 1);
        app(CartService::class)->usePoints($shop->id);

        // Punkty zniknęły między kasą a „Zamawiam" (np. wydane w drugiej karcie).
        $this->ledger->adjust($shop, 'anna@example.com', -1094);

        try {
            app(OrderService::class)->place($shop, $this->orderData(), $customer);
            $this->fail('Zamówienie nie powinno powstać.');
        } catch (CartNeedsReviewException $e) {
            $this->assertStringContainsString('Punkty nie zostały naliczone', implode(' ', $e->messages));
        }

        $this->assertSame(0, Order::count());
    }

    public function test_guest_cannot_pay_with_points_even_with_the_switch_on(): void
    {
        $shop = $this->shop();
        $this->customer($shop, 1094);
        app(CartService::class)->add($this->product($shop, 39.80), 1);
        app(CartService::class)->usePoints($shop->id);

        $order = app(OrderService::class)->place($shop, $this->orderData());

        $this->assertSame(0.00, (float) $order->points_discount);
        $this->assertSame(1094, $this->ledger->balance($shop, 'anna@example.com'));
    }

    public function test_points_are_earned_only_on_the_part_paid_with_money(): void
    {
        $shop = $this->shop();
        $customer = $this->customer($shop, 5000);
        $order = $this->placeWithPoints($shop, $customer, $this->product($shop, 100));

        app(OrderStatusChanger::class)->change($order->fresh(), OrderStatus::Completed);

        // 100 − 50 punktami = 50 zł zapłacone → 5% = 250 pkt.
        $this->assertSame(250, LoyaltyEntry::where('type', LoyaltyEntryType::Earned)->sole()->points);
    }

    // ── Anulowanie, zwrot, edycja ──────────────────────────────────────────

    public function test_cancelling_gives_all_points_back(): void
    {
        $shop = $this->shop();
        $customer = $this->customer($shop, 1094);
        $order = $this->placeWithPoints($shop, $customer, $this->product($shop, 39.80));

        app(OrderStatusChanger::class)->change($order->fresh(), OrderStatus::Cancelled);

        $this->assertSame(1094, $this->ledger->balance($shop, 'anna@example.com'));
    }

    public function test_partial_return_refunds_paid_money_and_gives_points_back(): void
    {
        $shop = $this->shop();
        $customer = $this->customer($shop, 2000);
        $order = $this->placeWithPoints($shop, $customer, $this->product($shop, 50), $this->product($shop, 50));
        $this->assertSame(20.00, (float) $order->points_discount);

        $order->statusEvents()->create(['from_status' => OrderStatus::Processing, 'to_status' => OrderStatus::Completed]);
        $item = $order->items()->first();

        $return = app(OrderReturnService::class)->register($order->fresh(), [$item->id => 1], [
            'customer_name' => 'Anna Kowalska', 'customer_address' => 'ul. Polna 1, 00-001 Warszawa',
            'bank_account' => null, 'note' => null,
        ]);

        // Połowa zamówienia: zapłacone pieniędzmi 40 zł, punktami 10 zł → 1000 pkt wraca.
        $this->assertSame('40.00', $return->refund_gross);
        $this->assertSame(10.00, (float) $order->fresh()->points_discount);
        $this->assertSame(1000, $this->ledger->balance($shop, 'anna@example.com'));
        $this->assertSame(1000, $this->ledger->spentOn($order));
    }

    public function test_seller_edit_below_points_paid_gives_the_difference_back(): void
    {
        $shop = $this->shop();
        $customer = $this->customer($shop, 10000);
        $order = $this->placeWithPoints($shop, $customer, $this->product($shop, 100));
        $this->assertSame(99.00, (float) $order->points_discount);

        // Sprzedawca obniża cenę pozycji do 40 zł — punkty płacą najwyżej 40 zł.
        app(OrderEditor::class)->changePrice($order->items()->first(), 40);

        // Wydane 9900 pkt, dalej płacą 4000 → 5900 wraca; plus 100 niewydanych.
        $this->assertSame(40.00, (float) $order->fresh()->points_discount);
        $this->assertSame(6000, $this->ledger->balance($shop, 'anna@example.com'));
        $this->assertSame(4000, $this->ledger->spentOn($order));
    }
}
