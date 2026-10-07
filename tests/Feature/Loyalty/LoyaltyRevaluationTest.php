<?php

namespace Tests\Feature\Loyalty;

use App\Enums\LoyaltyEntryType;
use App\Enums\OrderStatus;
use App\Models\LoyaltyEntry;
use App\Models\Order;
use App\Models\Shop;
use App\Services\LoyaltyLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Zmiana wartości punktu przelicza salda z zachowaniem wartości w złotych
 * (decyzja Rafała 07.10): 300 pkt × 1 gr → 30 pkt × 10 gr. Historia zostaje
 * w jednostkach ze swojego dnia, a różnicę opisuje wpis „Przeliczenie".
 * Reszty zaokrąglamy na korzyść klienta.
 */
class LoyaltyRevaluationTest extends TestCase
{
    use RefreshDatabase;

    private LoyaltyLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(LoyaltyLedger::class);
    }

    private function shop(): Shop
    {
        return Shop::factory()->withLoyalty()->create();
    }

    private function order(Shop $shop, float $itemsTotal = 100, OrderStatus $status = OrderStatus::Completed): Order
    {
        return Order::factory()->create([
            'shop_id' => $shop->id,
            'status' => $status,
            'buyer_email' => 'ewa@example.com',
            'items_total' => $itemsTotal,
        ]);
    }

    private function balance(Shop $shop): int
    {
        return $this->ledger->balance($shop, 'ewa@example.com');
    }

    public function test_balance_keeps_its_value_in_zloty_and_history_explains_the_change(): void
    {
        $shop = $this->shop();
        $this->ledger->adjust($shop, 'ewa@example.com', 300);

        $this->assertSame(1, $this->ledger->revalue($shop, 0.10));

        $this->assertSame(30, $this->balance($shop->fresh()));
        $this->assertSame('0.10', $shop->fresh()->loyalty_point_value);

        $entry = LoyaltyEntry::where('type', LoyaltyEntryType::Revaluation)->sole();
        $this->assertSame(-270, $entry->points);
        $this->assertSame('Nowa wartość punktu: 0,10 zł zamiast 0,01 zł', $entry->note);
        // Historia sumuje się do salda: +300 za korektę, −270 przeliczenie.
        $this->assertSame(30, (int) LoyaltyEntry::sum('points'));
    }

    public function test_remainders_are_rounded_in_favour_of_the_customer(): void
    {
        $shop = $this->shop();
        $this->ledger->adjust($shop, 'ewa@example.com', 305);
        $this->ledger->adjust($shop, 'dlug@example.com', -305);

        $this->ledger->revalue($shop, 0.10);

        $this->assertSame(31, $this->balance($shop));
        $this->assertSame(-30, $this->ledger->balance($shop, 'dlug@example.com'));
    }

    public function test_any_value_in_range_converts_in_favour_of_the_customer(): void
    {
        $shop = $this->shop();
        $this->ledger->adjust($shop, 'ewa@example.com', 301);

        // 3,01 zł po 0,05 zł = 60,2 pkt → 61 na korzyść klienta.
        $this->ledger->revalue($shop, 0.05);

        $this->assertSame(61, $this->balance($shop->fresh()));
    }

    public function test_pending_points_are_converted_too(): void
    {
        $shop = $this->shop();
        $this->ledger->award($this->order($shop));

        $this->ledger->revalue($shop, 0.10);

        $this->assertSame(50, $this->ledger->pending($shop->fresh(), 'ewa@example.com'));
    }

    public function test_cancelling_after_revaluation_gives_back_the_converted_amount(): void
    {
        $shop = $this->shop();
        $this->ledger->adjust($shop, 'ewa@example.com', 500);
        $order = $this->order($shop, 50, OrderStatus::New);
        $this->ledger->spend($order, 'ewa@example.com', 300);

        $this->ledger->revalue($shop, 0.10);
        $this->assertSame(20, $this->balance($shop->fresh()));

        $this->assertSame(30, $this->ledger->restore($order->fresh()));
        $this->assertSame(50, $this->balance($shop->fresh()));
    }

    public function test_return_after_revaluation_takes_back_the_right_value(): void
    {
        $shop = $this->shop();
        $order = $this->order($shop);
        $this->ledger->award($order);
        $this->ledger->revalue($shop, 0.10);

        // Zwrot połowy: odebrać 2,50 zł, czyli 25 nowych punktów, nie 250.
        $order->updateQuietly(['items_total' => 50]);

        $this->assertSame(25, $this->ledger->reconcile($order->fresh()));
        $this->assertSame(25, $this->ledger->pending($shop->fresh(), 'ewa@example.com'));
    }

    public function test_same_value_changes_nothing_and_value_out_of_range_is_refused(): void
    {
        $shop = $this->shop();
        $this->ledger->adjust($shop, 'ewa@example.com', 300);

        $this->assertSame(0, $this->ledger->revalue($shop, 0.01));
        $this->assertSame(300, $this->balance($shop));

        $this->expectException(InvalidArgumentException::class);
        $this->ledger->revalue($shop, 10.01);
    }
}
