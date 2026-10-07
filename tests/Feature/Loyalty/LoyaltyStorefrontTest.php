<?php

namespace Tests\Feature\Loyalty;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\EmailMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\LoyaltyLedger;
use App\Services\OrderStatusChanger;
use App\Services\OrderTotals;
use App\Support\LoyaltyRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Krok B punktów — to, co widzi klient: informacja na karcie produktu,
 * zakładka „Punkty" w Moim koncie i blok w mailu o realizacji (z zachętą do
 * konta dla gościa). Sklep bez punktów nie pokazuje niczego.
 */
class LoyaltyStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    private function shop(bool $loyalty = true): Shop
    {
        $factory = Shop::factory()->active()->sellable();

        return ($loyalty ? $factory->withLoyalty() : $factory)->create();
    }

    private function product(Shop $shop, float $price = 39.80): Product
    {
        return Product::factory()->create(['shop_id' => $shop->id, 'is_active' => true, 'price_gross' => $price]);
    }

    private function completedOrder(Shop $shop, string $email, float $price = 100): Order
    {
        $order = Order::factory()->create([
            'shop_id' => $shop->id,
            'status' => OrderStatus::Processing,
            'buyer_email' => $email,
        ]);
        $order->items()->create([
            'name' => 'Lemoniada', 'unit_price_gross' => $price, 'vat_rate' => '23',
            'quantity' => 1, 'sale_unit' => 'piece', 'line_total_gross' => $price,
        ]);
        app(OrderTotals::class)->recalculate($order->load('items'));
        app(OrderStatusChanger::class)->change($order->fresh(), OrderStatus::Completed);

        return $order->fresh();
    }

    public function test_product_page_shows_points_with_link_to_rules(): void
    {
        $shop = $this->shop();
        $page = LoyaltyRules::ensurePage($shop);
        $product = $this->product($shop);

        $this->get($this->host($shop).$product->storefrontPath())
            ->assertOk()
            ->assertSee('Za ten zakup dostaniesz')
            ->assertSee('199 pkt')
            ->assertSee('1,99 zł na kolejne zakupy')
            ->assertSee($page->storefrontPath());
    }

    public function test_product_page_without_points_shows_nothing(): void
    {
        $shop = $this->shop(loyalty: false);
        $product = $this->product($shop);

        $this->get($this->host($shop).$product->storefrontPath())
            ->assertOk()
            ->assertDontSee('Za ten zakup dostaniesz');
    }

    public function test_account_shows_points_tab_balance_and_history(): void
    {
        $shop = $this->shop();
        $customer = Customer::factory()->for($shop)->create(['email' => 'anna@example.com']);
        $order = $this->completedOrder($shop, 'anna@example.com');
        app(LoyaltyLedger::class)->adjust($shop, 'anna@example.com', 120, 'Prezent od sklepu');

        $this->actingAs($customer, 'customer')
            ->get($this->host($shop).'/moje-konto')
            ->assertOk()
            ->assertSee('/moje-konto/punkty')
            ->assertSee('Punkty do wykorzystania');

        $this->actingAs($customer, 'customer')
            ->get($this->host($shop).'/moje-konto/punkty')
            ->assertOk()
            ->assertSee('120 pkt')
            ->assertSee('500 pkt')
            ->assertSee('Za zakup')
            ->assertSee('zamówienie #'.$order->number)
            ->assertSee('Prezent od sklepu')
            ->assertSee('do wykorzystania od');
    }

    public function test_points_collected_as_guest_appear_after_registration(): void
    {
        $shop = $this->shop();
        $this->completedOrder($shop, 'Gosc@Example.com');
        $customer = Customer::factory()->for($shop)->create(['email' => 'gosc@example.com']);

        $this->actingAs($customer, 'customer')
            ->get($this->host($shop).'/moje-konto/punkty')
            ->assertOk()
            ->assertSee('500 pkt');
    }

    public function test_points_tab_is_hidden_in_shop_without_points(): void
    {
        $shop = $this->shop(loyalty: false);
        $customer = Customer::factory()->for($shop)->create();

        $this->actingAs($customer, 'customer')
            ->get($this->host($shop).'/moje-konto')
            ->assertOk()
            ->assertDontSee('/moje-konto/punkty');

        $this->actingAs($customer, 'customer')
            ->get($this->host($shop).'/moje-konto/punkty')
            ->assertNotFound();
    }

    public function test_points_stay_visible_after_shop_turns_them_off(): void
    {
        $shop = $this->shop();
        $customer = Customer::factory()->for($shop)->create(['email' => 'anna@example.com']);
        app(LoyaltyLedger::class)->adjust($shop, 'anna@example.com', 300);
        $shop->update(['loyalty_enabled' => false]);

        $this->actingAs($customer, 'customer')
            ->get($this->host($shop).'/moje-konto/punkty')
            ->assertOk()
            ->assertSee('300 pkt');
    }

    public function test_completion_mail_tells_guest_about_points_and_account(): void
    {
        $shop = $this->shop();

        $this->completedOrder($shop, 'gosc@example.com');

        $lines = json_encode(EmailMessage::latest('id')->first()->intro_lines, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('Punkty za to zamówienie: 500 pkt', $lines);
        $this->assertStringContainsString('Masz już łącznie **500 pkt**', $lines);
        $this->assertStringContainsString('/rejestracja', $lines);
    }

    public function test_completion_mail_links_registered_customer_to_points(): void
    {
        $shop = $this->shop();
        Customer::factory()->for($shop)->create([
            'email' => 'anna@example.com',
            'email_verified_at' => now(),
            'password' => 'haslo-do-sklepu',
        ]);

        $this->completedOrder($shop, 'anna@example.com');

        $lines = json_encode(EmailMessage::latest('id')->first()->intro_lines, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('/moje-konto/punkty', $lines);
        $this->assertStringNotContainsString('/rejestracja', $lines);
    }

    public function test_completion_mail_without_points_has_no_points_block(): void
    {
        $this->completedOrder($this->shop(loyalty: false), 'anna@example.com');

        $lines = json_encode(EmailMessage::latest('id')->first()->intro_lines, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString('Punkty za to zamówienie', $lines);
    }
}
