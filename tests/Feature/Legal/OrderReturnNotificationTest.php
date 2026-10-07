<?php

namespace Tests\Feature\Legal;

use App\Models\Customer;
use App\Models\EmailMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\OrderMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Powiadomienia po zgłoszeniu zwrotu (Faza B): sklep dowiaduje się o
 * odstąpieniu, a klient dostaje POTWIERDZENIE — to drugie jest obowiązkiem
 * z art. 30 ust. 2 ustawy o prawach konsumenta, nie uprzejmością. Plus link do
 * formularza tam, gdzie klient go szuka: w mailu po zakupie i w „Moim koncie".
 */
class OrderReturnNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function base(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    /**
     * Treść maila do przeszukania. Outbox trzyma ją w blokach (`intro_lines`,
     * `outro_lines`) plus adres przycisku — sklejamy wszystko w jeden tekst.
     */
    private function bodyOf(EmailMessage $message): string
    {
        return json_encode(
            [$message->intro_lines, $message->outro_lines, $message->action_url],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    private function orderWithItem(?Shop $shop = null): OrderItem
    {
        $shop ??= Shop::factory()->create(['owner_id' => User::factory()->consented()->create()->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'price_gross' => 60.00, 'vat_rate' => '23']);

        $order = Order::factory()->create([
            'shop_id' => $shop->id,
            'buyer_name' => 'Anna',
            'buyer_surname' => 'Kowalska',
            'buyer_email' => 'anna@example.test',
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'name' => 'Doniczka ceramiczna',
            'unit_price_gross' => 60.00,
            'vat_rate' => '23',
            'quantity' => 2,
            'sale_unit' => $product->sale_unit->value,
            'line_total_gross' => 120.00,
        ]);

        app(\App\Services\OrderTotals::class)->recalculate($order->load('items'));

        // Zwrot zgłasza się DOPIERO po wydaniu towaru (od 07.08.2026) — patrz
        // bliźniaczy pomocnik w OrderReturnFormTest.
        $order->statusEvents()->create([
            'from_status' => \App\Enums\OrderStatus::Processing,
            'to_status' => \App\Enums\OrderStatus::Completed,
        ]);
        $order->refresh();

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(OrderItem $item, float $quantity = 1): array
    {
        return [
            'customer_name' => 'Anna Kowalska',
            'customer_address' => 'ul. Polna 7, 00-001 Warszawa',
            'quantities' => [$item->id => $quantity],
        ];
    }

    public function test_submitting_a_return_mails_both_the_shop_and_the_customer(): void
    {
        $item = $this->orderWithItem();
        $shop = $item->order->shop;

        EmailMessage::query()->delete();

        $this->post($this->base($shop).'/zwrot/'.$item->order->paymentToken(), $this->payload($item));

        $toSeller = EmailMessage::where('to_email', $shop->owner->email)->first();
        $this->assertNotNull($toSeller, 'sprzedawca musi wiedzieć o zwrocie');
        $this->assertStringContainsString('Zwrot z zamówienia #'.$item->order->number, $toSeller->subject);
        $this->assertStringContainsString('Doniczka ceramiczna', $this->bodyOf($toSeller));
        $this->assertStringContainsString('60,00', $this->bodyOf($toSeller));
        $this->assertStringContainsString('Anna Kowalska', $this->bodyOf($toSeller));
        $this->assertStringContainsString('ul. Polna 7, 00-001 Warszawa', $this->bodyOf($toSeller));

        // Art. 30 ust. 2: potwierdzenie otrzymania oświadczenia jest obowiązkowe.
        $toCustomer = EmailMessage::where('to_email', 'anna@example.test')->first();
        $this->assertNotNull($toCustomer, 'klient musi dostać potwierdzenie odstąpienia');
        $this->assertStringContainsString('Potwierdzenie odstąpienia od umowy', $toCustomer->subject);
        $this->assertStringContainsString('Doniczka ceramiczna', $this->bodyOf($toCustomer));
    }

    public function test_seller_is_reminded_about_delivery_cost_on_a_full_return(): void
    {
        $item = $this->orderWithItem();
        $item->order->update(['delivery_cost' => 15.00]);
        app(\App\Services\OrderTotals::class)->recalculate($item->order->fresh()->load('items'));

        EmailMessage::query()->delete();

        // Zwrot CAŁOŚCI — ustawa każe oddać też najtańszą oferowaną dostawę.
        $this->post($this->base($item->order->shop).'/zwrot/'.$item->order->paymentToken(), $this->payload($item, 2));

        $toSeller = EmailMessage::where('to_email', $item->order->shop->owner->email)->first();
        $this->assertStringContainsString('koszt dostawy', $this->bodyOf($toSeller));
        $this->assertStringContainsString('15,00', $this->bodyOf($toSeller));
    }

    public function test_partial_return_does_not_mention_delivery_cost(): void
    {
        $item = $this->orderWithItem();
        $item->order->update(['delivery_cost' => 15.00]);
        app(\App\Services\OrderTotals::class)->recalculate($item->order->fresh()->load('items'));

        EmailMessage::query()->delete();

        $this->post($this->base($item->order->shop).'/zwrot/'.$item->order->paymentToken(), $this->payload($item, 1));

        $toSeller = EmailMessage::where('to_email', $item->order->shop->owner->email)->first();
        $this->assertStringNotContainsString('koszt dostawy', $this->bodyOf($toSeller));
    }

    public function test_order_confirmation_carries_a_link_to_the_return_form(): void
    {
        $item = $this->orderWithItem();

        app(OrderMailer::class)->confirmToCustomer($item->order->fresh());

        $mail = EmailMessage::where('to_email', 'anna@example.test')->latest('id')->first();
        $this->assertStringContainsString('/zwrot/', $this->bodyOf($mail));
        $this->assertStringContainsString('formularz zwrotu', $this->bodyOf($mail));
    }

    public function test_account_order_page_shows_the_return_button(): void
    {
        $item = $this->orderWithItem();
        $order = $item->order;

        $customer = Customer::factory()->create(['shop_id' => $order->shop_id, 'email' => $order->buyer_email]);
        $order->update(['customer_id' => $customer->id]);

        $this->actingAs($customer, 'customer')
            ->get($this->base($order->shop).'/moje-konto/zamowienia/'.$order->id)
            ->assertOk()
            ->assertSee('Zgłoś zwrot')
            ->assertSee('/zwrot/', false);
    }

    public function test_account_order_page_hides_empty_return_card_after_deadline(): void
    {
        $item = $this->orderWithItem();
        $order = $item->order;
        $customer = Customer::factory()->create(['shop_id' => $order->shop_id, 'email' => $order->buyer_email]);
        $order->update(['customer_id' => $customer->id]);

        // Towar wydany, termin minął, nic nie zgłoszono — karta nie miałaby treści.
        $this->travel(60)->days();

        $this->actingAs($customer, 'customer')
            ->get($this->base($order->shop).'/moje-konto/zamowienia/'.$order->id)
            ->assertOk()
            ->assertDontSee('st-box-title">Zwrot</h2>', false);
    }

    public function test_account_order_page_lists_reported_returns(): void
    {
        $item = $this->orderWithItem();
        $order = $item->order;

        $this->post($this->base($order->shop).'/zwrot/'.$order->paymentToken(), $this->payload($item, 1));

        $customer = Customer::factory()->create(['shop_id' => $order->shop_id, 'email' => $order->buyer_email]);
        $order->update(['customer_id' => $customer->id]);

        $this->actingAs($customer, 'customer')
            ->get($this->base($order->shop).'/moje-konto/zamowienia/'.$order->id)
            ->assertOk()
            ->assertSee('czeka na rozliczenie')
            ->assertSee('Zgłoś kolejny zwrot');
    }
}
