<?php

namespace Tests\Feature\Storefront;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Livewire\Checkout;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use App\Services\CompanyLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kasa (Livewire): pokazuje tylko włączone metody, waliduje dane i składa
 * zamówienie gościa (spec „Zakup bez rejestracji").
 */
class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function shopReadyForOrders(): Shop
    {
        return Shop::factory()->withCourierShipping()->create([
            'street' => 'Kwiatowa', 'building_number' => '5', 'postal_code' => '00-001',
            'city' => 'Warszawa', 'province' => 'mazowieckie',
            'pickup_enabled' => true, 'pay_on_pickup_enabled' => true,
            'bank_account_number' => '12345678901234567890123456', 'bank_transfer_enabled' => true,
        ]);
    }

    private function cartProduct(Shop $shop): Product
    {
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null, 'price_gross' => 40.00,
        ]);
        app(CartService::class)->add($product, 1);

        return $product;
    }

    public function test_checkout_lists_only_enabled_methods(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->assertSee('Odbiór osobisty')
            ->assertSee('Przelew na konto')
            ->assertSee('Płatność przy odbiorze');
    }

    public function test_terms_must_be_accepted(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '123456789')
            ->set('accept_terms', false)
            ->call('place')
            ->assertHasErrors('accept_terms');

        $this->assertSame(0, Order::count());
    }

    public function test_phone_must_be_a_valid_pl_mobile(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);

        // Za dużo cyfr — po normalizacji to nie jest „48 + 9 cyfr", więc odpada.
        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '123456789012345')
            ->set('delivery_method', 'pickup')
            ->set('payment_method', 'bank_transfer')
            ->set('accept_terms', true)
            ->call('place')
            ->assertHasErrors('buyer_phone')
            ->assertSee('Podaj numer w formacie 48 i 9 cyfr, np. 48 500 600 700.');

        $this->assertSame(0, Order::count());
    }

    public function test_phone_stored_in_canonical_form(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);

        // Zapis „ludzki" (spacje, +48) → w bazie kanoniczne „48" + 9 cyfr.
        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '+48 500 600 700')
            ->set('delivery_method', 'pickup')
            ->set('payment_method', 'bank_transfer')
            ->set('accept_terms', true)
            ->set('accept_privacy', true)
            ->call('place')
            ->assertRedirect('/kasa/dziekujemy');

        $this->assertSame('48500600700', Order::first()->buyer_phone);
    }

    public function test_validation_messages_use_polish_field_names(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_phone', '')
            ->set('accept_terms', true)
            ->call('place')
            ->assertHasErrors('buyer_phone')
            // Czytelna nazwa pola zamiast surowej właściwości „buyer phone".
            ->assertSee('Pole telefon jest wymagane.')
            ->assertDontSee('buyer phone');
    }

    public function test_guest_can_place_order(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '123456789')
            ->set('delivery_method', 'pickup')
            ->set('payment_method', 'bank_transfer')
            ->set('accept_terms', true)
            ->set('accept_privacy', true)
            ->call('place')
            ->assertRedirect('/kasa/dziekujemy');

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertSame('jan@example.com', $order->buyer_email);
        $this->assertSame(1, $order->number);
        $this->assertSame($order->id, session()->get('recent_order_id'));
        // Gość bez „załóż konto" — brak powiązania z kontem.
        $this->assertNull($order->customer_id);
    }

    private function fillValidCheckout(Shop $shop): Testable
    {
        return Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '123456789')
            ->set('delivery_method', 'pickup')
            ->set('payment_method', 'bank_transfer')
            ->set('accept_terms', true)
            ->set('accept_privacy', true);
    }

    public function test_create_account_makes_unactivated_customer_and_links_order(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);

        $this->fillValidCheckout($shop)
            ->set('create_account', true)
            ->call('place')
            ->assertRedirect('/kasa/dziekujemy');

        $customer = Customer::where('shop_id', $shop->id)->where('email', 'jan@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertFalse($customer->isActivated());
        $this->assertSame('Jan', $customer->name);
        // Telefon zapisany w formie kanonicznej (48 + 9 cyfr), jak w zamówieniu.
        $this->assertSame('48123456789', $customer->phone);
        $this->assertSame($customer->id, Order::first()->customer_id);

        // Link aktywacyjny „od sklepu" w kolejce.
        $this->assertDatabaseHas('email_messages', [
            'to_email' => 'jan@example.com',
            'subject' => 'Aktywuj swoje konto — '.$shop->name,
        ]);
    }

    public function test_order_silently_links_to_existing_account_without_activation(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);
        $existing = Customer::factory()->for($shop)->create(['email' => 'jan@example.com']);

        // Nawet z zaznaczonym „załóż konto" — konto istnieje, więc tylko dopięcie.
        $this->fillValidCheckout($shop)
            ->set('create_account', true)
            ->call('place')
            ->assertRedirect('/kasa/dziekujemy');

        $this->assertSame($existing->id, Order::first()->customer_id);
        $this->assertSame(1, Customer::where('shop_id', $shop->id)->count());
        $this->assertDatabaseMissing('email_messages', [
            'to_email' => 'jan@example.com',
            'subject' => 'Aktywuj swoje konto — '.$shop->name,
        ]);
    }

    public function test_matching_is_case_insensitive(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);
        $existing = Customer::factory()->for($shop)->create(['email' => 'jan@example.com']);

        $this->fillValidCheckout($shop)
            ->set('buyer_email', 'JAN@Example.com')
            ->call('place')
            ->assertRedirect('/kasa/dziekujemy');

        $this->assertSame($existing->id, Order::first()->customer_id);
        $this->assertSame(1, Customer::where('shop_id', $shop->id)->count());
    }

    public function test_logged_in_customer_order_links_to_their_account(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);
        $customer = Customer::factory()->for($shop)->create([
            'name' => 'Ala', 'surname' => 'Nowak', 'email' => 'ala@example.com', 'phone' => '48500600700',
        ]);

        $this->actingAs($customer, 'customer');

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            // dane wypełnione z konta w mount() — dokładamy tylko metody i zgody
            ->assertSet('buyer_email', 'ala@example.com')
            ->set('delivery_method', 'pickup')
            ->set('payment_method', 'bank_transfer')
            ->set('accept_terms', true)
            ->set('accept_privacy', true)
            ->call('place')
            ->assertRedirect('/kasa/dziekujemy');

        $this->assertSame($customer->id, Order::first()->customer_id);
    }

    public function test_prefills_from_customers_last_order(): void
    {
        $shop = $this->shopReadyForOrders();
        $shop->update(['courier_enabled' => true, 'courier_cost' => 15.00]);
        $this->cartProduct($shop);

        $customer = Customer::factory()->for($shop)->create([
            'name' => 'Ala', 'surname' => 'Nowak', 'email' => 'ala@example.com', 'phone' => '48500600700',
        ]);

        // Starsze zamówienie (odbiór) — nie może wygrać z nowszym.
        Order::factory()->for($shop)->for($customer)->create([
            'delivery_method' => DeliveryMethod::Pickup,
            'payment_method' => PaymentMethod::PayOnPickup,
            'created_at' => now()->subDays(10),
        ]);

        // Najświeższe zamówienie: firma (FV) + kurier + adres + przelew.
        Order::factory()->for($shop)->for($customer)->create([
            'is_company' => true,
            'company_name' => 'ACME sp. z o.o.',
            'company_nip' => '5252248481',
            'company_street' => 'Firmowa', 'company_building_number' => '7',
            'company_postal_code' => '00-002', 'company_city' => 'Warszawa',
            'delivery_method' => DeliveryMethod::Courier,
            'payment_method' => PaymentMethod::BankTransfer,
            'ship_street' => 'Dostawcza', 'ship_building_number' => '9', 'ship_apartment_number' => '3',
            'ship_postal_code' => '00-003', 'ship_city' => 'Kraków',
        ]);

        $this->actingAs($customer, 'customer');

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->assertSet('is_company', true)
            ->assertSet('company_name', 'ACME sp. z o.o.')
            ->assertSet('company_nip', '5252248481')
            ->assertSet('company_city', 'Warszawa')
            ->assertSet('delivery_method', 'courier')
            ->assertSet('payment_method', 'bank_transfer')
            ->assertSet('ship_street', 'Dostawcza')
            ->assertSet('ship_building_number', '9')
            ->assertSet('ship_apartment_number', '3')
            ->assertSet('ship_postal_code', '00-003')
            ->assertSet('ship_city', 'Kraków');
    }

    public function test_does_not_prefill_method_the_shop_no_longer_offers(): void
    {
        // Sklep bez kuriera; ostatnie zamówienie klienta szło jednak kurierem.
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);

        $customer = Customer::factory()->for($shop)->create();

        Order::factory()->for($shop)->for($customer)->create([
            'delivery_method' => DeliveryMethod::Courier,
            'payment_method' => PaymentMethod::BankTransfer,
            'ship_street' => 'Dostawcza', 'ship_building_number' => '9',
            'ship_postal_code' => '00-003', 'ship_city' => 'Kraków',
        ]);

        $this->actingAs($customer, 'customer');

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->assertSet('delivery_method', 'pickup')   // fallback do pierwszej oferowanej
            ->assertSet('ship_street', '');            // adres nie skopiowany (dostawa nie kurierska)
    }

    public function test_nip_lookup_fills_company_fields(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);

        $this->mock(CompanyLookup::class, function ($mock) {
            $mock->shouldReceive('byNip')->once()->andReturn([
                'company_name' => 'ACME sp. z o.o.',
                'street' => 'Kwiatowa',
                'building_number' => '5',
                'apartment_number' => null,
                'postal_code' => '00-001',
                'city' => 'Warszawa',
            ]);
        });

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('is_company', true)
            ->set('company_nip', '5252248481')
            ->call('lookupCompany')
            ->assertSet('company_name', 'ACME sp. z o.o.')
            ->assertSet('company_street', 'Kwiatowa')
            ->assertSet('company_city', 'Warszawa')
            ->assertHasNoErrors();
    }

    /**
     * Zakup na firmę z pustym NIP-em: klient ma zobaczyć komunikat walidacji,
     * a nie wywrotkę. Normalizacja zwraca dla takiego wejścia null i przed
     * poprawką leciał TypeError na typowanej właściwości.
     */
    public function test_company_purchase_with_blank_nip_fails_validation_not_type(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '123456789')
            ->set('delivery_method', 'pickup')
            ->set('payment_method', 'pay_on_pickup')
            ->set('accept_terms', true)
            ->set('accept_privacy', true)
            ->set('is_company', true)
            ->set('company_name', 'ACME sp. z o.o.')
            ->set('company_nip', '')
            ->call('place')
            ->assertHasErrors('company_nip');

        $this->assertSame(0, Order::count());
    }

    /** Wejście bez cyfr zostaje w polu — klient widzi, co wpisał, i poprawia. */
    public function test_company_nip_without_digits_is_kept_and_rejected(): void
    {
        $shop = $this->shopReadyForOrders();
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '123456789')
            ->set('delivery_method', 'pickup')
            ->set('payment_method', 'pay_on_pickup')
            ->set('accept_terms', true)
            ->set('accept_privacy', true)
            ->set('is_company', true)
            ->set('company_name', 'ACME sp. z o.o.')
            ->set('company_nip', 'brak')
            ->call('place')
            ->assertSet('company_nip', 'brak')
            ->assertHasErrors('company_nip');

        $this->assertSame(0, Order::count());
    }

    public function test_courier_option_requires_shipping_address(): void
    {
        $shop = $this->shopReadyForOrders();
        $shop->update(['courier_enabled' => true, 'courier_cost' => 15.00]);
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->assertSee('Kurier')
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '123456789')
            ->set('delivery_method', 'courier')
            ->set('payment_method', 'bank_transfer')
            ->set('accept_terms', true)
            ->set('accept_privacy', true)
            ->call('place')
            ->assertHasErrors(['ship_street', 'ship_building_number', 'ship_postal_code', 'ship_city']);

        $this->assertSame(0, Order::count());
    }

    public function test_choosing_courier_drops_pay_on_pickup_payment(): void
    {
        $shop = $this->shopReadyForOrders();
        $shop->update(['courier_enabled' => true, 'courier_cost' => 15.00]);
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('delivery_method', 'pickup')
            ->set('payment_method', 'pay_on_pickup')
            // Przełączenie na kuriera zdejmuje „płatność przy odbiorze" i przełącza
            // wybór na pierwszą dostępną metodę (przelew).
            ->set('delivery_method', 'courier')
            ->assertDontSee('Płatność przy odbiorze')
            ->assertSet('payment_method', 'bank_transfer');
    }

    public function test_guest_places_courier_order_with_cost_and_address(): void
    {
        $shop = $this->shopReadyForOrders();
        $shop->update(['courier_enabled' => true, 'courier_cost' => 15.00]);
        $this->cartProduct($shop);   // produkt 40 zł

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '123456789')
            ->set('delivery_method', 'courier')
            ->set('payment_method', 'bank_transfer')
            ->set('ship_street', 'Leśna')
            ->set('ship_building_number', '12')
            ->set('ship_postal_code', '30-001')
            ->set('ship_city', 'Kraków')
            ->set('accept_terms', true)
            ->set('accept_privacy', true)
            ->call('place')
            ->assertRedirect('/kasa/dziekujemy');

        $order = Order::first();
        $this->assertSame(DeliveryMethod::Courier, $order->delivery_method);
        $this->assertSame('15.00', $order->delivery_cost);
        $this->assertSame('55.00', $order->total_gross);   // 40 produkty + 15 dostawa
        $this->assertSame('Leśna', $order->ship_street);
        $this->assertSame('Kraków', $order->ship_city);
    }

    public function test_parcel_locker_option_requires_code_but_not_address(): void
    {
        // Sedno w kasie: paczkomat prosi o KOD, a nie o ulicę i miasto.
        $shop = $this->shopReadyForOrders();
        $shop->update(['parcel_locker_enabled' => true, 'parcel_locker_cost' => 12.00]);
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->assertSee('Paczkomat InPost')
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '123456789')
            ->set('delivery_method', 'parcel_locker')
            ->set('payment_method', 'bank_transfer')
            ->set('accept_terms', true)
            ->set('accept_privacy', true)
            ->call('place')
            ->assertHasErrors('parcel_locker_code')
            ->assertHasNoErrors(['ship_street', 'ship_building_number', 'ship_postal_code', 'ship_city']);

        $this->assertSame(0, Order::count());
    }

    public function test_guest_places_parcel_locker_order_with_own_cost(): void
    {
        $shop = $this->shopReadyForOrders();
        // Kurier włączony CELOWO drożej — paczkomat ma policzyć SWÓJ koszt.
        $shop->update([
            'parcel_locker_enabled' => true, 'parcel_locker_cost' => 12.00,
            'courier_enabled' => true, 'courier_cost' => 15.00,
        ]);
        $this->cartProduct($shop);   // produkt 40 zł

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '123456789')
            ->set('delivery_method', 'parcel_locker')
            ->set('payment_method', 'bank_transfer')
            ->set('parcel_locker_code', 'kra01a')       // z palca, małymi literami
            ->set('accept_terms', true)
            ->set('accept_privacy', true)
            ->call('place')
            ->assertRedirect('/kasa/dziekujemy');

        $order = Order::first();
        $this->assertSame(DeliveryMethod::ParcelLocker, $order->delivery_method);
        $this->assertSame('12.00', $order->delivery_cost);
        $this->assertSame('52.00', $order->total_gross);   // 40 produkty + 12 dostawa
        // Kod znormalizowany do wersalików — klient nie ma za to obrywać.
        $this->assertSame('KRA01A', $order->parcel_locker_code);
        // Adres nie istnieje przy paczkomacie.
        $this->assertNull($order->ship_street);
        $this->assertNull($order->ship_city);
    }

    public function test_confirmation_page_shows_parcel_locker_instead_of_address(): void
    {
        $shop = $this->shopReadyForOrders();
        $shop->update(['parcel_locker_enabled' => true, 'parcel_locker_cost' => 12.00]);
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '123456789')
            ->set('delivery_method', 'parcel_locker')
            ->set('payment_method', 'bank_transfer')
            ->set('parcel_locker_code', 'KRA01A')
            ->set('accept_terms', true)
            ->set('accept_privacy', true)
            ->call('place');

        $order = Order::firstOrFail();
        $base = 'http://'.$shop->slug.'.'.config('tenancy.central_domain');

        $this->withSession(['recent_order_id' => $order->id])
            ->get($base.'/kasa/dziekujemy')
            ->assertOk()
            ->assertSee('Wyślemy do paczkomatu:')
            ->assertSee('KRA01A')
            // Blok adresu nie ma prawa się pokazać — nie ma czego pokazać.
            ->assertDontSee('Wyślemy na adres:');
    }

    public function test_checkout_loads_geowidget_script_when_parcel_locker_and_token_present(): void
    {
        config()->set('services.inpost.geowidget_token', 'test-token-123');

        $shop = $this->shopReadyForOrders();
        $shop->update(['parcel_locker_enabled' => true, 'parcel_locker_cost' => 12.00]);
        $this->cartProduct($shop);

        $base = 'http://'.$shop->slug.'.'.config('tenancy.central_domain');

        // Skrypt mapy w <head> zależy od sklepu (oferuje paczkomat + token),
        // nie od wybranej metody — ładuje się od wejścia na kasę.
        $this->get($base.'/kasa')
            ->assertOk()
            ->assertSee('inpost-geowidget.js');
    }

    public function test_geowidget_element_with_token_appears_only_after_choosing_parcel_locker(): void
    {
        config()->set('services.inpost.geowidget_token', 'test-token-123');

        $shop = $this->shopReadyForOrders();
        $shop->update(['parcel_locker_enabled' => true, 'parcel_locker_cost' => 12.00]);
        $this->cartProduct($shop);

        // Token siedzi w atrybucie <inpost-geowidget> — nie wisi w kodzie, dopóki
        // klient nie wybierze paczkomatu (mniej powierzchni do podebrania).
        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->assertDontSee('test-token-123')
            ->assertDontSee('inpost-geowidget', escape: false)
            ->set('delivery_method', 'parcel_locker')
            ->assertSee('test-token-123')
            ->assertSee('inpost-geowidget', escape: false);
    }

    public function test_parcel_locker_falls_back_to_manual_code_when_token_missing(): void
    {
        config()->set('services.inpost.geowidget_token', null);

        $shop = $this->shopReadyForOrders();
        $shop->update(['parcel_locker_enabled' => true, 'parcel_locker_cost' => 12.00]);
        $this->cartProduct($shop);

        $base = 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
        $this->get($base.'/kasa')->assertOk()->assertDontSee('inpost-geowidget.js');

        // Bez tokenu mapy nie ma, ale paczkomat wciąż da się kupić z palca —
        // blok pokazuje link do mapy InPostu zamiast przycisku „Wybierz na mapie".
        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('delivery_method', 'parcel_locker')
            ->assertDontSee('inpost-geowidget', escape: false)
            ->assertSee('znajdz-paczkomat', escape: false)
            ->assertDontSee('Wybierz na mapie');
    }

    public function test_checkout_omits_geowidget_when_shop_has_no_parcel_locker(): void
    {
        config()->set('services.inpost.geowidget_token', 'test-token-123');

        $shop = $this->shopReadyForOrders();   // paczkomat wyłączony
        $this->cartProduct($shop);

        $base = 'http://'.$shop->slug.'.'.config('tenancy.central_domain');

        // Token jest, ale sklep nie oferuje paczkomatu — skrypt nie ma po co się
        // ładować, nie obciąża kasy tego sklepu.
        $this->get($base.'/kasa')
            ->assertOk()
            ->assertDontSee('inpost-geowidget.js');
    }

    public function test_choosing_parcel_locker_drops_pay_on_pickup_payment(): void
    {
        $shop = $this->shopReadyForOrders();
        $shop->update(['parcel_locker_enabled' => true, 'parcel_locker_cost' => 12.00]);
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('delivery_method', 'pickup')
            ->set('payment_method', 'pay_on_pickup')
            // Paczkomat to wysyłka — „płatność przy odbiorze" nie ma czego dotyczyć.
            ->set('delivery_method', 'parcel_locker')
            ->assertDontSee('Płatność przy odbiorze')
            ->assertSet('payment_method', 'bank_transfer');
    }

    public function test_parcel_locker_option_is_hidden_when_shop_has_it_disabled(): void
    {
        $shop = $this->shopReadyForOrders();   // paczkomat domyślnie wyłączony
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->assertDontSee('Paczkomat InPost')
            ->set('delivery_method', 'parcel_locker')
            ->set('payment_method', 'bank_transfer')
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '123456789')
            ->set('accept_terms', true)
            ->set('accept_privacy', true)
            ->call('place')
            // Metoda spoza oferty sklepu nie przechodzi walidacji, choćby
            // ktoś podstawił ją z palca w polu formularza.
            ->assertHasErrors('delivery_method');

        $this->assertSame(0, Order::count());
    }

    public function test_confirmation_page_shows_order_number(): void
    {
        $shop = $this->shopReadyForOrders();
        $product = $this->cartProduct($shop);
        $order = $shop->orders()->create([
            'number' => 7,
            'buyer_name' => 'Jan', 'buyer_surname' => 'Kowalski', 'buyer_email' => 'jan@example.com',
            'delivery_method' => 'pickup', 'payment_method' => 'pay_on_pickup', 'delivery_cost' => 0,
            'items_total' => 40, 'total_net' => 32.52, 'total_vat' => 7.48, 'total_gross' => 40,
        ]);

        $base = 'http://'.$shop->slug.'.'.config('tenancy.central_domain');

        $this->withSession(['recent_order_id' => $order->id])
            ->get($base.'/kasa/dziekujemy')
            ->assertOk()
            ->assertSee('#7');
    }

    public function test_courier_and_parcel_hidden_in_checkout_without_entitlement(): void
    {
        // Brama pakietu: sklep z włączonym kurierem i paczkomatem, ale bez
        // uprawnienia `courier_shipping` (Kram), oferuje w kasie TYLKO odbiór.
        $shop = Shop::factory()->create([
            'street' => 'Kwiatowa', 'building_number' => '5', 'postal_code' => '00-001',
            'city' => 'Warszawa', 'province' => 'mazowieckie',
            'pickup_enabled' => true,
            'courier_enabled' => true, 'courier_cost' => 15.00,
            'parcel_locker_enabled' => true, 'parcel_locker_cost' => 12.00,
        ]);
        $this->assertFalse($shop->entitlement('courier_shipping'));

        $options = Livewire::test(Checkout::class, ['shopId' => $shop->id])->instance()->deliveryOptions();
        $this->assertArrayHasKey(DeliveryMethod::Pickup->value, $options);
        $this->assertArrayNotHasKey(DeliveryMethod::Courier->value, $options);
        $this->assertArrayNotHasKey(DeliveryMethod::ParcelLocker->value, $options);
    }
}
