<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Migracja `payment_provider` uzupełnia stare zamówienia: każde zamówienie
 * online sprzed drugiej bramki płaciło przez Paynow (innej nie było), a
 * zamówienia bez płatności online zostają bez bramki. Także usunięte logicznie —
 * historia zamówienia nie może kłamać, czym je opłacono.
 */
class PaymentProviderBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_online_orders_are_marked_as_paynow(): void
    {
        $shop = Shop::factory()->create();
        $online = Order::factory()->for($shop)->create([
            'status' => OrderStatus::Paid,
            'payment_method' => PaymentMethod::Online,
        ]);
        $deleted = Order::factory()->for($shop)->create([
            'status' => OrderStatus::AwaitingPayment,
            'payment_method' => PaymentMethod::Online,
        ]);
        $deleted->delete();
        $transfer = Order::factory()->for($shop)->create([
            'status' => OrderStatus::AwaitingPayment,
            'payment_method' => PaymentMethod::BankTransfer,
        ]);

        // Stan sprzed migracji: kolumny nie ma, potem migracja wraca.
        $migration = require database_path('migrations/2026_11_23_120000_add_payment_provider_to_orders_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame(PaymentProvider::Paynow, $online->refresh()->payment_provider);
        $this->assertSame(PaymentProvider::Paynow, Order::withTrashed()->find($deleted->id)->payment_provider);
        $this->assertNull($transfer->refresh()->payment_provider);
    }
}
