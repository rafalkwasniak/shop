<?php

namespace Tests\Feature\Loyalty;

use App\Enums\LoyaltyEntryType;
use App\Enums\OrderStatus;
use App\Models\LoyaltyEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\LoyaltyLedger;
use App\Services\OrderReturnService;
use App\Services\OrderStatusChanger;
use App\Services\OrderTotals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use RuntimeException;
use Tests\TestCase;

/**
 * Punkty idą za zamówieniem bez udziału UI: realizacja nalicza, anulowanie
 * oddaje i odbiera, zwrot odbiera proporcjonalnie. Sklep z wyłączonymi
 * punktami działa dokładnie jak wcześniej, a awaria punktów nie blokuje
 * zmiany statusu.
 */
class LoyaltyOrderHooksTest extends TestCase
{
    use RefreshDatabase;

    private function shop(bool $enabled = true): Shop
    {
        return Shop::factory()->create([
            'loyalty_enabled' => $enabled,
            'loyalty_earn_percent' => 5,
            'loyalty_point_value' => 0.01,
        ]);
    }

    /** Zamówienie na 2 × 50 zł z prawdziwą pozycją, policzone jak przy składaniu. */
    private function order(Shop $shop, OrderStatus $status = OrderStatus::Processing): Order
    {
        $product = Product::factory()->create(['shop_id' => $shop->id, 'price_gross' => 50, 'vat_rate' => '23']);
        $order = Order::factory()->create([
            'shop_id' => $shop->id,
            'status' => $status,
            'buyer_email' => 'anna@example.com',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price_gross' => 50,
            'vat_rate' => '23',
            'quantity' => 2,
            'sale_unit' => $product->sale_unit->value,
            'line_total_gross' => 100,
        ]);

        app(OrderTotals::class)->recalculate($order->load('items'));

        return $order->fresh();
    }

    private function changer(): OrderStatusChanger
    {
        return app(OrderStatusChanger::class);
    }

    public function test_completing_an_order_awards_pending_points(): void
    {
        $shop = $this->shop();
        $order = $this->order($shop);

        $this->assertTrue($this->changer()->change($order, OrderStatus::Completed));

        $lot = LoyaltyEntry::sole();
        $this->assertSame(LoyaltyEntryType::Earned, $lot->type);
        $this->assertSame(500, $lot->points);
        $this->assertSame(500, app(LoyaltyLedger::class)->pending($shop, 'anna@example.com'));
    }

    public function test_shop_without_points_gets_nothing(): void
    {
        $order = $this->order($this->shop(enabled: false));

        $this->changer()->change($order, OrderStatus::Completed);
        $this->changer()->change($order->fresh(), OrderStatus::Cancelled);

        $this->assertSame(0, LoyaltyEntry::count());
    }

    public function test_cancelling_a_completed_order_takes_its_points_back(): void
    {
        $shop = $this->shop();
        $order = $this->order($shop);
        $this->changer()->change($order, OrderStatus::Completed);

        $this->changer()->change($order->fresh(), OrderStatus::Cancelled);

        $this->assertSame(0, app(LoyaltyLedger::class)->pending($shop, 'anna@example.com'));
        $this->assertSame(-500, LoyaltyEntry::where('type', LoyaltyEntryType::Clawback)->sole()->points);
    }

    public function test_cancelling_an_order_paid_with_points_gives_them_back(): void
    {
        $shop = $this->shop();
        $ledger = app(LoyaltyLedger::class);
        $ledger->adjust($shop, 'anna@example.com', 300);
        $order = $this->order($shop, OrderStatus::New);
        $ledger->spend($order, 'anna@example.com', 300);

        $this->changer()->change($order, OrderStatus::Cancelled);

        $this->assertSame(300, $ledger->balance($shop, 'anna@example.com'));
    }

    public function test_return_takes_points_proportionally(): void
    {
        $shop = $this->shop();
        $order = $this->order($shop);
        $this->changer()->change($order, OrderStatus::Completed);

        $item = $order->items()->first();
        app(OrderReturnService::class)->register($order->fresh(), [$item->id => 1], [
            'customer_name' => 'Anna Kowalska',
            'customer_address' => 'ul. Polna 1, 00-001 Warszawa',
            'bank_account' => null,
            'note' => null,
        ]);

        $this->assertSame(250, app(LoyaltyLedger::class)->pending($shop, 'anna@example.com'));
    }

    public function test_points_failure_never_blocks_the_status_change(): void
    {
        Exceptions::fake();
        $this->mock(LoyaltyLedger::class)
            ->shouldReceive('award')
            ->andThrow(new RuntimeException('awaria punktów'));
        $order = $this->order($this->shop());

        $this->assertTrue($this->changer()->change($order, OrderStatus::Completed));

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_expire_command_expires_overdue_points(): void
    {
        $shop = $this->shop();
        $entry = app(LoyaltyLedger::class)->adjust($shop, 'anna@example.com', 120);
        $entry->update(['expires_at' => now()->subDay()]);

        $this->artisan('loyalty:expire')
            ->expectsOutput('Wygaszone punkty: 120.')
            ->assertSuccessful();

        $this->assertSame(0, app(LoyaltyLedger::class)->balance($shop, 'anna@example.com'));
    }
}
