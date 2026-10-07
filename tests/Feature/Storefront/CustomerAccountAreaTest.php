<?php

namespace Tests\Feature\Storefront;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Panel „Moje konto" (Krok 4): dostęp tylko dla zalogowanego klienta, historia i
 * szczegół zamówień scope'owane do konta, edycja danych, zmiana hasła oraz
 * usunięcie konta (zamówienia odpinane do gościa, nie kasowane).
 */
class CustomerAccountAreaTest extends TestCase
{
    use RefreshDatabase;

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $shop = Shop::factory()->create();

        $this->get($this->host($shop).'/moje-konto')->assertRedirect('/logowanie');
    }

    public function test_customer_sees_only_their_orders(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $other = Customer::factory()->for($shop)->create();

        $mine = Order::factory()->for($shop)->create(['customer_id' => $customer->id, 'number' => 101]);
        $theirs = Order::factory()->for($shop)->create(['customer_id' => $other->id, 'number' => 202]);
        $guest = Order::factory()->for($shop)->create(['customer_id' => null, 'number' => 303]);

        $this->actingAs($customer, 'customer')
            ->get($this->host($shop).'/moje-konto')
            ->assertOk()
            ->assertSee('#101')
            ->assertDontSee('#202')
            ->assertDontSee('#303');
    }

    public function test_orders_list_shows_the_same_stats_as_the_overview(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        Order::factory()->for($shop)->create(['customer_id' => $customer->id, 'total_gross' => 100.50]);
        Order::factory()->for($shop)->create(['customer_id' => $customer->id, 'total_gross' => 20.00]);
        // Anulowane nie jest zakupem — nie podbija liczby ani kwoty.
        Order::factory()->for($shop)->create(['customer_id' => $customer->id, 'total_gross' => 999.00, 'status' => \App\Enums\OrderStatus::Cancelled]);

        $this->actingAs($customer, 'customer')
            ->get($this->host($shop).'/moje-konto/zamowienia')
            ->assertOk()
            ->assertSee('Złożone zamówienia')
            ->assertSee('120,50 zł')
            ->assertDontSee('1 119,50 zł');
    }

    public function test_orders_list_shows_all_customer_orders(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $other = Customer::factory()->for($shop)->create();

        Order::factory()->for($shop)->create(['customer_id' => $customer->id, 'number' => 101]);
        Order::factory()->for($shop)->create(['customer_id' => $customer->id, 'number' => 102]);
        Order::factory()->for($shop)->create(['customer_id' => $other->id, 'number' => 202]);

        $this->actingAs($customer, 'customer')
            ->get($this->host($shop).'/moje-konto/zamowienia')
            ->assertOk()
            ->assertSee('#101')
            ->assertSee('#102')
            ->assertDontSee('#202');
    }

    public function test_orders_list_shows_fv_badge_only_for_invoiced_orders(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();

        Order::factory()->for($shop)->create(['customer_id' => $customer->id, 'number' => 101])
            ->forceFill(['invoice_id' => 555])->save();
        Order::factory()->for($shop)->create(['customer_id' => $customer->id, 'number' => 102]); // bez FV

        $response = $this->actingAs($customer, 'customer')
            ->get($this->host($shop).'/moje-konto/zamowienia')
            ->assertOk();

        $this->assertSame(1, substr_count($response->getContent(), '>FV<'));
    }

    public function test_orders_list_paginates_by_ten(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();

        // 11 zamówień (numery bez kolizji podłańcuchów): najnowsze (#1011) na
        // 1. stronie, najstarsze (#1001) spycha się na 2. — dowód podziału po 10.
        for ($n = 1001; $n <= 1011; $n++) {
            Order::factory()->for($shop)->create(['customer_id' => $customer->id, 'number' => $n]);
        }

        $this->actingAs($customer, 'customer');

        $this->get($this->host($shop).'/moje-konto/zamowienia')
            ->assertOk()
            ->assertSee('Zamówienie #1011')
            ->assertDontSee('Zamówienie #1001');

        $this->get($this->host($shop).'/moje-konto/zamowienia?page=2')
            ->assertOk()
            ->assertSee('Zamówienie #1001');
    }

    public function test_order_detail_is_scoped_to_customer(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $other = Customer::factory()->for($shop)->create();

        $mine = Order::factory()->for($shop)->create(['customer_id' => $customer->id]);
        $theirs = Order::factory()->for($shop)->create(['customer_id' => $other->id]);

        $this->actingAs($customer, 'customer');

        $this->get($this->host($shop).'/moje-konto/zamowienia/'.$mine->id)->assertOk()->assertSee('#'.$mine->number);
        $this->get($this->host($shop).'/moje-konto/zamowienia/'.$theirs->id)->assertNotFound();
    }

    public function test_order_detail_shows_the_status_history_with_notes(): void
    {
        // Klient widzi „kiedy co się wydarzyło”: status początkowy + kolejne
        // przejścia z datą, a notatka sprzedawcy (ta sama, którą dostał mailem)
        // stoi przy właściwym kroku.
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $order = Order::factory()->for($shop)->create([
            'customer_id' => $customer->id,
            'status' => \App\Enums\OrderStatus::Processing,
        ]);
        $order->statusEvents()->create([
            'from_status' => \App\Enums\OrderStatus::New,
            'to_status' => \App\Enums\OrderStatus::Processing,
            'note' => 'Pakujemy dzisiaj',
        ]);

        $this->actingAs($customer, 'customer');

        $this->get($this->host($shop).'/moje-konto/zamowienia/'.$order->id)
            ->assertOk()
            ->assertSee('Historia zamówienia')
            ->assertSee('Złożone')
            ->assertSee('W realizacji')
            ->assertSee('Pakujemy dzisiaj');
    }

    public function test_order_detail_describes_online_payment_without_pickup_wording(): void
    {
        // Regresja: online w „Moje konto" mówił „płatność przy odbiorze" (dawna
        // gałąź @else). Nieopłacone → „oczekuje na płatność online", bez odbioru.
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $order = Order::factory()->for($shop)->create([
            'customer_id' => $customer->id,
            'status' => \App\Enums\OrderStatus::AwaitingPayment,
            'payment_method' => \App\Enums\PaymentMethod::Online,
        ]);

        $this->actingAs($customer, 'customer');

        $this->get($this->host($shop).'/moje-konto/zamowienia/'.$order->id)
            ->assertOk()
            ->assertSee('Oczekuje na płatność online')
            ->assertDontSee('przy odbiorze');
    }

    public function test_order_detail_offers_invoice_download_when_invoiced(): void
    {
        $shop = Shop::factory()->create();
        $shop->integrations()->create([
            'type' => \App\Enums\IntegrationType::Invoicing,
            'enabled' => true,
            'config' => ['account_url' => 'https://sklep.fakturownia.pl', 'api_token' => 'SECRET'],
        ]);
        $customer = Customer::factory()->for($shop)->create();

        $invoiced = Order::factory()->for($shop)->create(['customer_id' => $customer->id]);
        $invoiced->forceFill(['invoice_id' => 555, 'invoice_number' => '9/2026', 'invoice_token' => 'tok'])->save();
        $plain = Order::factory()->for($shop)->create(['customer_id' => $customer->id]);

        $this->actingAs($customer, 'customer');

        $this->get($this->host($shop).'/moje-konto/zamowienia/'.$invoiced->id)
            ->assertOk()
            ->assertSee('Pobierz fakturę VAT')
            ->assertSee('https://sklep.fakturownia.pl/invoice/tok.pdf', false)
            ->assertSee('9/2026');

        $this->get($this->host($shop).'/moje-konto/zamowienia/'.$plain->id)
            ->assertOk()
            ->assertDontSee('Pobierz fakturę VAT');
    }

    public function test_order_detail_back_link_remembers_listing_page(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $order = Order::factory()->for($shop)->create(['customer_id' => $customer->id]);

        $this->actingAs($customer, 'customer')
            ->get($this->host($shop).'/moje-konto/zamowienia/'.$order->id.'?powrot='.urlencode('/moje-konto/zamowienia?page=3'))
            ->assertOk()
            ->assertSee('/moje-konto/zamowienia?page=3');
    }

    public function test_order_detail_back_link_rejects_external_url(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $order = Order::factory()->for($shop)->create(['customer_id' => $customer->id]);

        $this->actingAs($customer, 'customer')
            ->get($this->host($shop).'/moje-konto/zamowienia/'.$order->id.'?powrot='.urlencode('https://evil.example/phish'))
            ->assertOk()
            ->assertDontSee('evil.example');
    }

    public function test_profile_can_be_updated(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();

        $this->actingAs($customer, 'customer')
            ->post($this->host($shop).'/moje-konto/dane', [
                'name' => 'Nowa', 'surname' => 'Nazwa', 'phone' => '+48 500 600 700',
            ])
            ->assertRedirect('/moje-konto/dane');

        $customer->refresh();
        $this->assertSame('Nowa', $customer->name);
        $this->assertSame('Nazwa', $customer->surname);
        $this->assertSame('48500600700', $customer->phone);
    }

    public function test_password_change_requires_correct_current(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create(['password' => Hash::make('password')]);

        $this->actingAs($customer, 'customer');

        // Złe obecne hasło → błąd, hasło niezmienione.
        $this->post($this->host($shop).'/moje-konto/haslo', [
            'current_password' => 'zle-haslo',
            'password' => 'nowe-haslo-123',
            'password_confirmation' => 'nowe-haslo-123',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password', $customer->fresh()->password));

        // Poprawne obecne hasło → zmiana.
        $this->post($this->host($shop).'/moje-konto/haslo', [
            'current_password' => 'password',
            'password' => 'nowe-haslo-123',
            'password_confirmation' => 'nowe-haslo-123',
        ])->assertRedirect('/moje-konto/dane');
        $this->assertTrue(Hash::check('nowe-haslo-123', $customer->fresh()->password));
    }

    public function test_account_deletion_detaches_orders_and_logs_out(): void
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();
        $order = Order::factory()->for($shop)->create(['customer_id' => $customer->id]);

        $this->actingAs($customer, 'customer')
            ->post($this->host($shop).'/moje-konto/usun')
            ->assertRedirect('/');

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertNull($order->fresh()->customer_id);
        $this->assertGuest('customer');
    }
}
