<?php

namespace Tests\Feature\Loyalty;

use App\Enums\LoyaltyEntryType;
use App\Enums\OrderStatus;
use App\Exceptions\LoyaltyException;
use App\Models\LoyaltyEntry;
use App\Models\Order;
use App\Models\Shop;
use App\Services\LoyaltyLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Silnik punktów za zakupy, bez UI i bez podpięcia pod zamówienia: naliczanie,
 * karencja, wydawanie od najwcześniej wygasających, oddawanie po anulowaniu,
 * odbieranie po zwrocie (z długiem), wygasanie. Tu pilnujemy liczb, na których
 * staną koszyk, konto klienta i panel sprzedawcy.
 *
 * Zmiany zamówienia zapisujemy `updateQuietly()`: inaczej `OrderObserver` sam
 * wywołałby księgę i test nie sprawdzałby już tego, co woła jawnie.
 */
class LoyaltyLedgerTest extends TestCase
{
    use RefreshDatabase;

    private LoyaltyLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(LoyaltyLedger::class);
    }

    private function shop(array $attributes = []): Shop
    {
        return Shop::factory()->create(array_merge([
            'loyalty_enabled' => true,
            'loyalty_earn_percent' => 3,
            'loyalty_point_value' => 0.01,
            'loyalty_validity_months' => 12,
        ], $attributes));
    }

    private function order(Shop $shop, float $itemsTotal = 100, array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'shop_id' => $shop->id,
            'status' => OrderStatus::Completed,
            'buyer_email' => 'jan@example.com',
            'items_total' => $itemsTotal,
        ], $attributes));
    }

    /** Porcja naliczona i już dostępna (po karencji). */
    private function availableLot(Shop $shop, float $itemsTotal = 100): LoyaltyEntry
    {
        return $this->ledger->award($this->order($shop, $itemsTotal), now()->subDays($shop->loyaltyDelayDays() + 1));
    }

    public function test_points_are_a_percentage_of_the_amount_in_chosen_point_value(): void
    {
        $shop = $this->shop();

        $this->assertSame(300, $this->ledger->pointsFromBase($shop, 100));
        // Ułamek punktu przepada, nie zaokrągla się w górę.
        $this->assertSame(299, $this->ledger->pointsFromBase($shop, 99.99));

        $shop->loyalty_point_value = 0.10;
        $this->assertSame(30, $this->ledger->pointsFromBase($shop, 100));

        $shop->loyalty_earn_percent = 2.5;
        $shop->loyalty_point_value = 1.00;
        $this->assertSame(2, $this->ledger->pointsFromBase($shop, 100));
    }

    public function test_points_are_counted_from_products_after_discount_without_delivery(): void
    {
        $shop = $this->shop();
        $order = $this->order($shop, 100, ['discount_amount' => 20, 'delivery_cost' => 15]);

        $lot = $this->ledger->award($order);

        $this->assertSame(240, $lot->points);
        $this->assertSame('80.00', $lot->base_amount);
    }

    public function test_awarded_points_wait_for_the_withdrawal_window_by_default(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 7)->setTime(12, 0));
        $shop = $this->shop();

        $lot = $this->ledger->award($this->order($shop));

        // 14 dni ustawowych + 6 dni zapasu na dostawę.
        $this->assertSame(20, $shop->loyaltyDelayDays());
        $this->assertSame('2026-10-27 12:00:00', $lot->available_at->format('Y-m-d H:i:s'));
        // Ważność liczona od dostępności, do końca dnia.
        $this->assertSame('2027-10-27 23:59:59', $lot->expires_at->format('Y-m-d H:i:s'));

        $this->assertSame(0, $this->ledger->balance($shop, 'jan@example.com'));
        $this->assertSame(300, $this->ledger->pending($shop, 'jan@example.com'));

        $this->travel(20)->days();

        $this->assertSame(300, $this->ledger->balance($shop, 'jan@example.com'));
        $this->assertSame(0, $this->ledger->pending($shop, 'jan@example.com'));
    }

    public function test_seller_can_set_own_delay_and_unlimited_validity(): void
    {
        $shop = $this->shop(['loyalty_delay_days' => 45, 'loyalty_validity_months' => null]);

        $lot = $this->ledger->award($this->order($shop));

        $this->assertTrue($lot->available_at->isSameDay(now()->addDays(45)));
        $this->assertNull($lot->expires_at);
    }

    public function test_award_is_idempotent_and_skips_disabled_shops_and_cancelled_orders(): void
    {
        $shop = $this->shop();
        $order = $this->order($shop);

        $first = $this->ledger->award($order);
        $second = $this->ledger->award($order);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, LoyaltyEntry::count());

        $this->assertNull($this->ledger->award($this->order($shop, 100, ['status' => OrderStatus::Cancelled])));
        $this->assertNull($this->ledger->award($this->order($this->shop(['loyalty_enabled' => false]))));
    }

    public function test_email_is_case_insensitive_and_shops_are_isolated(): void
    {
        $shop = $this->shop();
        $this->ledger->award(
            $this->order($shop, 100, ['buyer_email' => ' Jan@Example.COM ']),
            now()->subMonth(),
        );

        $this->assertSame(300, $this->ledger->balance($shop, 'jan@example.com'));
        $this->assertSame(0, $this->ledger->balance($this->shop(), 'jan@example.com'));
    }

    public function test_spending_takes_the_soonest_expiring_points_first(): void
    {
        $shop = $this->shop();
        $older = $this->availableLot($shop);
        $newer = $this->availableLot($shop);
        $older->update(['expires_at' => now()->addMonth()]);
        $newer->update(['expires_at' => now()->addMonths(6)]);

        $entry = $this->ledger->spend($this->order($shop, 50, ['status' => OrderStatus::New]), 'jan@example.com', 400);

        $this->assertSame(-400, $entry->points);
        $this->assertSame(0, $older->fresh()->remaining);
        $this->assertSame(200, $newer->fresh()->remaining);
        $this->assertSame(200, $this->ledger->balance($shop, 'jan@example.com'));
        $this->assertSame([300, 100], $entry->usages()->orderBy('id')->pluck('points')->all());
    }

    public function test_cannot_spend_more_than_available_nor_pending_points(): void
    {
        $shop = $this->shop();
        $this->ledger->award($this->order($shop));

        $this->expectException(LoyaltyException::class);

        $this->ledger->spend($this->order($shop), 'jan@example.com', 1);
    }

    public function test_cancelled_order_gives_spent_points_back_to_the_same_lots_once(): void
    {
        $shop = $this->shop();
        $lot = $this->availableLot($shop);
        $order = $this->order($shop, 50, ['status' => OrderStatus::New]);
        $this->ledger->spend($order, 'jan@example.com', 250);

        $this->assertSame(250, $this->ledger->restore($order));
        $this->assertSame(0, $this->ledger->restore($order));

        $this->assertSame(300, $lot->fresh()->remaining);
        $this->assertSame(300, $this->ledger->balance($shop, 'jan@example.com'));
        $this->assertSame(1, LoyaltyEntry::where('type', LoyaltyEntryType::Restored)->count());
    }

    public function test_points_from_an_expired_lot_are_not_given_back(): void
    {
        $shop = $this->shop();
        $lot = $this->availableLot($shop);
        $order = $this->order($shop, 50, ['status' => OrderStatus::New]);
        $this->ledger->spend($order, 'jan@example.com', 100);
        $lot->update(['expires_at' => now()->subMinute()]);

        $this->assertSame(0, $this->ledger->restore($order));
        $this->assertSame(200, $lot->fresh()->remaining);
    }

    public function test_partial_return_takes_proportional_points_from_the_pending_lot(): void
    {
        $shop = $this->shop();
        $order = $this->order($shop);
        $lot = $this->ledger->award($order);

        // Zwrot połowy: `items_total` maleje tak, jak po OrderTotals::recalculate().
        $order->updateQuietly(['items_total' => 50]);
        // Sprzedawca zmienił procent w międzyczasie — odbieramy od kwoty, nie od nowego procentu.
        $shop->update(['loyalty_earn_percent' => 10]);

        $this->assertSame(150, $this->ledger->reconcile($order->fresh()));
        $this->assertSame(0, $this->ledger->reconcile($order->fresh()));

        $this->assertSame(150, $lot->fresh()->remaining);
        $this->assertSame(150, $this->ledger->pending($shop, 'jan@example.com'));
        $this->assertSame(-150, LoyaltyEntry::where('type', LoyaltyEntryType::Clawback)->sole()->points);
    }

    public function test_cancelling_after_award_takes_all_points_back(): void
    {
        $shop = $this->shop();
        $order = $this->order($shop);
        $this->ledger->award($order);

        $order->updateQuietly(['status' => OrderStatus::Cancelled]);

        $this->assertSame(300, $this->ledger->reconcile($order));
        $this->assertSame(0, $this->ledger->pending($shop, 'jan@example.com'));
    }

    public function test_return_of_already_spent_points_leaves_a_debt_paid_by_later_points(): void
    {
        $shop = $this->shop();
        $order = $this->order($shop);
        $this->ledger->award($order, now()->subDays(30));
        $this->ledger->spend($this->order($shop, 10, ['status' => OrderStatus::New]), 'jan@example.com', 300);

        $order->updateQuietly(['items_total' => 0]);
        $this->ledger->reconcile($order);

        $this->assertSame(-300, $this->ledger->balance($shop, 'jan@example.com'));

        // Kolejne zakupy spłacają dług, zanim punkty da się wydać.
        $this->availableLot($shop, 200);
        $this->assertSame(300, $this->ledger->balance($shop, 'jan@example.com'));

        $this->ledger->spend($this->order($shop, 10, ['status' => OrderStatus::New]), 'jan@example.com', 300);

        $this->assertSame(0, $this->ledger->balance($shop, 'jan@example.com'));
        $this->assertSame(0, (int) LoyaltyEntry::where('remaining', '<', 0)->sum('remaining'));
    }

    public function test_expiry_pays_debt_first_and_writes_the_rest_into_history(): void
    {
        $shop = $this->shop();
        // Porcja jeszcze w karencji, więc ujemna korekta nie ma z czego zdjąć → dług 50.
        $lot = $this->ledger->award($this->order($shop));
        $this->ledger->adjust($shop, 'jan@example.com', -50);
        $this->assertSame(-50, $this->ledger->balance($shop, 'jan@example.com'));

        $lot->update(['available_at' => now()->subYear(), 'expires_at' => now()->subMinute()]);

        $this->assertSame(250, $this->ledger->expire());
        $this->assertSame(0, $lot->fresh()->remaining);
        $this->assertSame(0, (int) LoyaltyEntry::where('remaining', '<', 0)->sum('remaining'));
        $this->assertSame(-250, LoyaltyEntry::where('type', LoyaltyEntryType::Expired)->sole()->points);
        $this->assertSame(0, $this->ledger->balance($shop, 'jan@example.com'));
    }

    public function test_positive_adjustment_is_available_at_once(): void
    {
        $shop = $this->shop();

        $entry = $this->ledger->adjust($shop, 'Jan@example.com', 500, 'Punkty ze starego sklepu');

        $this->assertSame(500, $this->ledger->balance($shop, 'jan@example.com'));
        $this->assertSame('Punkty ze starego sklepu', $entry->note);
        $this->assertNotNull($entry->expires_at);
    }
}
