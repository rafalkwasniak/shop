<?php

namespace Tests\Feature\Loyalty;

use App\Enums\LoyaltyEntryType;
use App\Models\LoyaltyEntry;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Services\LoyaltyLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Krok D punktów: kartoteka klientów pokazuje saldo (na liście i na karcie),
 * historię punktów i pozwala sprzedawcy ręcznie dopisać albo odjąć punkty.
 */
class LoyaltyCustomerDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private LoyaltyLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(LoyaltyLedger::class);
    }

    /** @return array{User, Shop} */
    private function seller(bool $loyalty = true): array
    {
        $seller = User::factory()->consented()->create();
        $factory = Shop::factory();
        $shop = ($loyalty ? $factory->withLoyalty() : $factory)->create(['owner_id' => $seller->id]);

        // Klient istnieje w kartotece, bo kupił.
        Order::factory()->create(['shop_id' => $shop->id, 'buyer_email' => 'anna@example.com', 'total_gross' => 50]);

        return [$seller, $shop];
    }

    public function test_list_shows_points_balance_badge(): void
    {
        [$seller, $shop] = $this->seller();
        $this->ledger->adjust($shop, 'anna@example.com', 1094);

        $this->actingAs($seller)->get(route('seller.customers.index'))
            ->assertOk()
            ->assertSee('1094 pkt');
    }

    public function test_customer_card_shows_balance_history_and_correction_form(): void
    {
        [$seller, $shop] = $this->seller();
        $this->ledger->adjust($shop, 'anna@example.com', 300, 'Powitanie');

        $this->actingAs($seller)->get(route('seller.customers.show', ['email' => 'anna@example.com']))
            ->assertOk()
            ->assertSee('Historia punktów')
            ->assertSee('300 pkt')
            ->assertSee('3,00 zł')
            ->assertSee('Powitanie')
            ->assertSee('Korekta punktów');
    }

    public function test_seller_can_add_and_subtract_points_with_a_note(): void
    {
        [$seller, $shop] = $this->seller();
        $url = route('seller.customers.points', ['email' => 'anna@example.com']);

        $this->actingAs($seller)->post($url, ['points' => '+500', 'note' => 'Przeprosiny za opóźnienie'])
            ->assertRedirect(route('seller.customers.show', ['email' => 'anna@example.com']))
            ->assertSessionHas('success', 'Dopisano 500 pkt.');

        $this->actingAs($seller)->post($url, ['points' => '−700', 'note' => 'Pomyłka przy dopisaniu'])
            ->assertSessionHas('success', 'Odjęto 700 pkt.');

        // Odjęcie ponad saldo daje dług, który spłacą kolejne punkty.
        $this->assertSame(-200, $this->ledger->balance($shop, 'anna@example.com'));
        $this->assertSame('Przeprosiny za opóźnienie', LoyaltyEntry::where('type', LoyaltyEntryType::Adjustment)->orderBy('id')->first()->note);
    }

    public function test_correction_needs_a_note_and_a_non_zero_number(): void
    {
        [$seller] = $this->seller();
        $url = route('seller.customers.points', ['email' => 'anna@example.com']);

        $this->actingAs($seller)->post($url, ['points' => '0', 'note' => ''])
            ->assertSessionHasErrors(['points', 'note']);
        $this->actingAs($seller)->post($url, ['points' => '2,5', 'note' => 'x'])
            ->assertSessionHasErrors('points');

        $this->assertSame(0, LoyaltyEntry::count());
    }

    public function test_unknown_customer_or_shop_without_points_cannot_be_corrected(): void
    {
        [$seller] = $this->seller();

        $this->actingAs($seller)
            ->post(route('seller.customers.points', ['email' => 'nikt@example.com']), ['points' => '100', 'note' => 'x'])
            ->assertNotFound();

        [$other] = $this->seller(loyalty: false);

        $this->actingAs($other)->get(route('seller.customers.show', ['email' => 'anna@example.com']))
            ->assertOk()
            ->assertDontSee('Korekta punktów');
        $this->actingAs($other)
            ->post(route('seller.customers.points', ['email' => 'anna@example.com']), ['points' => '100', 'note' => 'x'])
            ->assertNotFound();
    }

    public function test_bulk_balances_match_single_balance(): void
    {
        [, $shop] = $this->seller();
        // Dostępne 300, oczekujące 500 (nie liczą się), wygasłe 200 (nie liczą się), dług −50.
        $this->ledger->adjust($shop, 'anna@example.com', 300);
        $pending = $this->ledger->adjust($shop, 'anna@example.com', 500);
        $pending->update(['available_at' => now()->addWeek()]);
        $expired = $this->ledger->adjust($shop, 'anna@example.com', 200);
        $expired->update(['expires_at' => now()->subDay()]);
        $this->ledger->adjust($shop, 'ewa@example.com', -50);

        $balances = $this->ledger->balances($shop);

        $this->assertSame($this->ledger->balance($shop, 'anna@example.com'), $balances['anna@example.com']);
        $this->assertSame(300, $balances['anna@example.com']);
        $this->assertSame(-50, $balances['ewa@example.com']);
    }
}
