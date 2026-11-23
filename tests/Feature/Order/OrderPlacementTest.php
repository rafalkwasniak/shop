<?php

namespace Tests\Feature\Order;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Exceptions\CartNeedsReviewException;
use App\Support\OrderFlow;
use App\Models\EmailMessage;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Składanie zamówienia (OrderService): migawka pozycji + sumy VAT, atomowe
 * zdjęcie stanu, czyszczenie koszyka oraz finalna weryfikacja dostępności
 * (spec „Finalna weryfikacja zamówienia").
 */
class OrderPlacementTest extends TestCase
{
    use RefreshDatabase;

    private function shop(): Shop
    {
        return Shop::factory()->create([
            'street' => 'Kwiatowa', 'building_number' => '5', 'postal_code' => '00-001',
            'city' => 'Warszawa', 'province' => 'mazowieckie',
            'pickup_enabled' => true, 'pay_on_pickup_enabled' => true,
        ]);
    }

    private function buyerData(): array
    {
        return [
            'buyer_name' => 'Jan',
            'buyer_surname' => 'Kowalski',
            'buyer_email' => 'jan@example.com',
            'buyer_phone' => '123456789',
            'is_company' => false,
            'delivery_method' => 'pickup',
            'payment_method' => 'pay_on_pickup',
            'note' => null,
        ];
    }

    public function test_place_creates_order_with_snapshot_and_decrements_stock(): void
    {
        $shop = $this->shop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true,
            'track_stock' => true, 'stock' => 5, 'price_gross' => 123.00, 'vat_rate' => '23',
        ]);

        app(CartService::class)->add($product, 2);

        $order = app(OrderService::class)->place($shop, $this->buyerData());

        $this->assertSame(1, $order->number);
        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertCount(1, $order->items);

        $item = $order->items->first();
        $this->assertSame('123.00', $item->unit_price_gross);
        $this->assertSame('2.00', $item->quantity);
        $this->assertSame(\App\Enums\SaleUnit::Piece, $item->sale_unit);
        $this->assertSame('246.00', $item->line_total_gross);

        // Sumy: 246 brutto → netto 200, VAT 46.
        $this->assertSame('246.00', $order->total_gross);
        $this->assertSame('200.00', $order->total_net);
        $this->assertSame('46.00', $order->total_vat);

        // Stan zdjęty i koszyk pusty.
        $this->assertSame('3.00', $product->fresh()->stock);
        $this->assertSame(0, app(CartService::class)->count($shop->id));
    }

    public function test_prepaid_order_starts_awaiting_payment_not_new(): void
    {
        $shop = $this->shop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false,
        ]);

        app(CartService::class)->add($product, 1);

        $order = app(OrderService::class)->place($shop, [
            ...$this->buyerData(),
            'payment_method' => 'bank_transfer',
        ]);

        // Ścieżka przelewu nie zna „Nowego" — od razu czekamy na wpłatę.
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
    }

    public function test_place_aborts_and_adjusts_when_stock_dropped(): void
    {
        $shop = $this->shop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true,
            'track_stock' => true, 'stock' => 5, 'price_gross' => 50.00,
        ]);
        app(CartService::class)->add($product, 5);

        // Stan spadł zanim klient kliknął „Zamawiam".
        $product->update(['stock' => 2]);

        try {
            app(OrderService::class)->place($shop, $this->buyerData());
            $this->fail('Spodziewano się CartNeedsReviewException.');
        } catch (CartNeedsReviewException $e) {
            $this->assertNotEmpty($e->messages);
        }

        // Zamówienie NIE powstało, koszyk uzgodniony do dostępnych 2, brak maili.
        $this->assertSame(0, $shop->orders()->count());
        $this->assertSame(1, app(CartService::class)->count($shop->id));   // jedna pozycja
        $this->assertSame(2.0, app(CartService::class)->raw($shop->id)[$product->id]);
        $this->assertSame('2.00', $product->fresh()->stock);   // stan nietknięty przez zamówienie
        $this->assertSame(0, EmailMessage::count());
    }

    public function test_place_enqueues_customer_and_seller_emails(): void
    {
        $shop = $this->shop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null, 'price_gross' => 30.00,
        ]);
        app(CartService::class)->add($product, 1);

        $order = app(OrderService::class)->place($shop, $this->buyerData());

        $this->assertSame(2, EmailMessage::count());
        // Potwierdzenie do kupującego + powiadomienie do właściciela sklepu, priorytet Mid.
        $this->assertDatabaseHas('email_messages', [
            'to_email' => 'jan@example.com',
            'subject' => 'Potwierdzenie zamówienia #'.$order->number.' — '.$shop->name,
            'priority' => \App\Enums\MailPriority::Mid->value,
        ]);
        $this->assertDatabaseHas('email_messages', ['to_email' => $shop->owner->email]);
    }

    public function test_order_emails_carry_shop_sender_identity(): void
    {
        $shop = $this->shop();
        $shop->update(['name' => 'I like my bike', 'contact_email' => 'kontakt@bike.test']);
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null, 'price_gross' => 30.00,
        ]);
        app(CartService::class)->add($product, 1);

        app(OrderService::class)->place($shop, $this->buyerData());

        // Oba maile (do klienta i do sprzedawcy) niosą tożsamość sklepu: nadawca =
        // nazwa sklepu, Reply-To = e-mail kontaktowy sklepu.
        $this->assertSame(2, EmailMessage::count());
        foreach (EmailMessage::all() as $email) {
            $this->assertSame('I like my bike', $email->from_name);
            $this->assertSame('kontakt@bike.test', $email->reply_to);
        }
    }

    public function test_customer_email_bolds_full_transfer_title_and_amount(): void
    {
        $shop = $this->shop();
        $shop->update([
            'bank_transfer_enabled' => true,
            'bank_account_number' => '43114020040000350275155558',
            'bank_name' => 'mBank',
        ]);
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null, 'price_gross' => 20.00,
        ]);
        app(CartService::class)->add($product, 1);

        $order = app(OrderService::class)->place($shop, array_merge($this->buyerData(), [
            'payment_method' => 'bank_transfer',
        ]));

        $customerEmail = EmailMessage::where('to_email', 'jan@example.com')->firstOrFail();
        $lines = collect($customerEmail->intro_lines)->flatten()->all();

        // Cały tytuł przelewu (nie samo #N) i kwota są pogrubione — to wartości do skopiowania.
        $this->assertContains('Tytuł przelewu: **Zamówienie #'.$order->number.'**', $lines);
        $this->assertContains('Kwota: **'.\App\Support\Money::pln($order->total_gross).'**', $lines);
        // Fraza „zamówienie #N" w zdaniu jest pogrubiona w całości, nie samo #N.
        $this->assertContains('Otrzymaliśmy Twoje **zamówienie #'.$order->number.'** i już się nim zajmujemy.', $lines);
    }

    public function test_customer_email_describes_online_payment_without_pickup_wording(): void
    {
        // Regresja: online to przedpłata — mail nie może mówić „zapłacisz na
        // miejscu przy odbiorze" (dawny błąd wspólnej gałęzi z płatnością przy odbiorze).
        $shop = $this->shop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null, 'price_gross' => 20.00,
        ]);
        app(CartService::class)->add($product, 1);

        $order = app(OrderService::class)->place($shop, array_merge($this->buyerData(), [
            'payment_method' => 'online',
            'payment_provider' => 'przelewy24',
        ]));

        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);

        $lines = collect(EmailMessage::where('to_email', 'jan@example.com')->firstOrFail()->intro_lines)->flatten()->all();
        $joined = implode("\n", $lines);

        // Klient widzi operatora, którym naprawdę płaci — nie „Paynow" na sztywno.
        $this->assertStringContainsString('Sposób płatności: Płatność online (Przelewy24)', $joined);
        $this->assertStringContainsString('czeka na opłacenie', $joined);
        $this->assertStringNotContainsString('przy odbiorze', $joined);
    }

    public function test_seller_email_formats_phone_and_includes_company_address(): void
    {
        $shop = $this->shop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null, 'price_gross' => 10.00,
        ]);
        app(CartService::class)->add($product, 1);

        $data = array_merge($this->buyerData(), [
            'buyer_phone' => '668196229',
            'is_company' => true,
            'company_name' => 'ACME sp. z o.o.',
            'company_nip' => '5252248481',
            'company_street' => 'Polna',
            'company_building_number' => '3',
            'company_postal_code' => '00-002',
            'company_city' => 'Kraków',
        ]);

        app(OrderService::class)->place($shop, $data);

        $sellerEmail = EmailMessage::where('to_email', $shop->owner->email)->firstOrFail();

        // intro_lines to teraz bloki (tablice linii) — spłaszczamy przed szukaniem.
        $lines = collect($sellerEmail->intro_lines)->flatten()->all();
        $this->assertContains('Telefon: +48 668 196 229', $lines);
        $this->assertContains('Adres: Polna 3, 00-002 Kraków', $lines);
        // Nagłówki sekcji są pogrubiane zapisem **...**.
        $this->assertContains('**Dane kupującego:**', $lines);
        $this->assertContains('**Dane do faktury:**', $lines);
        // NIP KUPUJĄCEGO w mailu zostaje — to jego dane do faktury, chce je
        // zweryfikować. Nie mylić z NIP-em NADAWCY, którego stopka świadomie NIE
        // pokazuje (patrz MailFooterTest::test_footer_never_shows_the_senders_nip).
        // Ta asercja jest tam przeciwwagą: bez niej „sprzątanie NIP-ów z maili"
        // wywaliłoby dane do faktury i nic by nie pisnęło.
        $this->assertContains('Firma: ACME sp. z o.o.', $lines);
        $this->assertContains('NIP: 5252248481', $lines);
        // Fraza „zamówienie #N" w zdaniu jest pogrubiona w całości, nie samo #N.
        $order = $shop->orders()->firstOrFail();
        $this->assertContains('W Twoim sklepie **'.$shop->name.'** pojawiło się nowe **zamówienie #'.$order->number.'**.', $lines);
    }

    public function test_place_stores_company_data_when_buying_as_company(): void
    {
        $shop = $this->shop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null, 'price_gross' => 10.00,
        ]);
        app(CartService::class)->add($product, 1);

        $data = array_merge($this->buyerData(), [
            'is_company' => true,
            'company_name' => 'ACME sp. z o.o.',
            'company_nip' => '5252248481',
            'company_street' => 'Kwiatowa',
            'company_building_number' => '5',
            'company_postal_code' => '00-001',
            'company_city' => 'Warszawa',
        ]);

        $order = app(OrderService::class)->place($shop, $data);

        $this->assertTrue($order->is_company);
        $this->assertSame('ACME sp. z o.o.', $order->company_name);
        $this->assertSame('5252248481', $order->company_nip);
        $this->assertSame('Kwiatowa', $order->company_street);
        $this->assertSame('Warszawa', $order->company_city);
    }

    public function test_company_fields_are_null_when_not_company(): void
    {
        $shop = $this->shop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null, 'price_gross' => 10.00,
        ]);
        app(CartService::class)->add($product, 1);

        // Dane firmy podane, ale is_company = false → nie zapisujemy ich.
        $data = array_merge($this->buyerData(), [
            'is_company' => false,
            'company_name' => 'Nie zapisać',
            'company_street' => 'Nie zapisać',
        ]);

        $order = app(OrderService::class)->place($shop, $data);

        $this->assertNull($order->company_name);
        $this->assertNull($order->company_street);
    }

    private function courierShop(?float $freeFrom = null): Shop
    {
        $shop = $this->shop();
        $shop->update([
            'courier_enabled' => true, 'courier_cost' => 15.00, 'courier_free_from' => $freeFrom,
            'bank_transfer_enabled' => true, 'bank_account_number' => '12345678901234567890123456',
        ]);

        return $shop;
    }

    private function courierData(): array
    {
        return array_merge($this->buyerData(), [
            'delivery_method' => 'courier',
            'payment_method' => 'bank_transfer',
            'ship_street' => 'Leśna',
            'ship_building_number' => '12',
            'ship_apartment_number' => '3',
            'ship_postal_code' => '30-001',
            'ship_city' => 'Kraków',
        ]);
    }

    public function test_courier_order_adds_cost_and_stores_ship_address(): void
    {
        $shop = $this->courierShop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null,
            'price_gross' => 100.00, 'vat_rate' => '23',
        ]);
        app(CartService::class)->add($product, 1);

        $order = app(OrderService::class)->place($shop, $this->courierData());

        $this->assertSame(DeliveryMethod::Courier, $order->delivery_method);
        $this->assertSame('15.00', $order->delivery_cost);
        // Suma brutto = produkty (100) + dostawa (15); netto/VAT liczone z produktów.
        $this->assertSame('115.00', $order->total_gross);
        $this->assertSame('81.30', $order->total_net);

        // Migawka adresu dostawy.
        $this->assertSame('Leśna', $order->ship_street);
        $this->assertSame('12', $order->ship_building_number);
        $this->assertSame('3', $order->ship_apartment_number);
        $this->assertSame('30-001', $order->ship_postal_code);
        $this->assertSame('Kraków', $order->ship_city);

        // Ścieżka statusów wysyłki: „Gotowe do wysyłki" zamiast „Gotowe do odbioru".
        $flow = OrderFlow::forOrder($order);
        $this->assertTrue($flow->includes(OrderStatus::ReadyForShipment));
        $this->assertFalse($flow->includes(OrderStatus::ReadyForPickup));
    }

    public function test_courier_free_shipping_threshold_zeroes_cost(): void
    {
        $shop = $this->courierShop(freeFrom: 100.00);
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null,
            'price_gross' => 120.00, 'vat_rate' => '23',
        ]);
        app(CartService::class)->add($product, 1);

        $order = app(OrderService::class)->place($shop, $this->courierData());

        // Koszyk 120 ≥ próg 100 → dostawa gratis, suma = same produkty.
        $this->assertSame('0.00', $order->delivery_cost);
        $this->assertSame('120.00', $order->total_gross);
    }

    private function parcelLockerShop(?float $freeFrom = null): Shop
    {
        $shop = $this->shop();
        $shop->update([
            'parcel_locker_enabled' => true, 'parcel_locker_cost' => 12.00, 'parcel_locker_free_from' => $freeFrom,
            // Kurier włączony CELOWO z innym cennikiem — pilnuje, że paczkomat
            // liczy się ze swojego, a nie podbiera kurierskiego (dawny zrost).
            'courier_enabled' => true, 'courier_cost' => 15.00,
            'bank_transfer_enabled' => true, 'bank_account_number' => '12345678901234567890123456',
        ]);

        return $shop;
    }

    private function parcelLockerData(): array
    {
        return array_merge($this->buyerData(), [
            'delivery_method' => 'parcel_locker',
            'payment_method' => 'bank_transfer',
            'parcel_locker_code' => 'KRA01A',
            'parcel_locker_address' => 'ul. Długa 5, 30-001 Kraków',
        ]);
    }

    public function test_parcel_locker_order_adds_own_cost_and_stores_point(): void
    {
        $shop = $this->parcelLockerShop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null,
            'price_gross' => 100.00, 'vat_rate' => '23',
        ]);
        app(CartService::class)->add($product, 1);

        $order = app(OrderService::class)->place($shop, $this->parcelLockerData());

        $this->assertSame(DeliveryMethod::ParcelLocker, $order->delivery_method);
        // Koszt paczkomatu (12), NIE kuriera (15) — mimo że kurier też włączony.
        $this->assertSame('12.00', $order->delivery_cost);
        $this->assertSame('112.00', $order->total_gross);

        // Migawka punktu.
        $this->assertSame('KRA01A', $order->parcel_locker_code);
        $this->assertSame('ul. Długa 5, 30-001 Kraków', $order->parcel_locker_address);

        // Ścieżka statusów jak przy kurierze: to wysyłka.
        $flow = OrderFlow::forOrder($order);
        $this->assertTrue($flow->includes(OrderStatus::ReadyForShipment));
        $this->assertFalse($flow->includes(OrderStatus::ReadyForPickup));
    }

    public function test_parcel_locker_order_does_not_store_shipping_address(): void
    {
        // Sedno rozdzielenia pojęć: paczkomat JEST wysyłką (ma koszt dostawy),
        // ale adresu klienta nie potrzebuje — paczka jedzie do skrytki.
        // Nawet gdy adres przyjdzie w danych, nie wolno go zapisać.
        $shop = $this->parcelLockerShop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null,
            'price_gross' => 100.00, 'vat_rate' => '23',
        ]);
        app(CartService::class)->add($product, 1);

        $order = app(OrderService::class)->place($shop, array_merge($this->parcelLockerData(), [
            'ship_street' => 'Leśna',
            'ship_building_number' => '12',
            'ship_postal_code' => '30-001',
            'ship_city' => 'Kraków',
        ]));

        $this->assertTrue($order->delivery_method->isShipped());
        $this->assertNull($order->ship_street);
        $this->assertNull($order->ship_building_number);
        $this->assertNull($order->ship_postal_code);
        $this->assertNull($order->ship_city);
    }

    public function test_courier_order_does_not_store_parcel_locker_point(): void
    {
        // Lustro poprzedniego: zamówienie ma albo adres, albo paczkomat.
        $shop = $this->courierShop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null,
            'price_gross' => 100.00, 'vat_rate' => '23',
        ]);
        app(CartService::class)->add($product, 1);

        $order = app(OrderService::class)->place($shop, array_merge($this->courierData(), [
            'parcel_locker_code' => 'KRA01A',
        ]));

        $this->assertSame('Leśna', $order->ship_street);
        $this->assertNull($order->parcel_locker_code);
        $this->assertNull($order->parcel_locker_address);
    }

    public function test_parcel_locker_mail_to_customer_carries_the_point_code(): void
    {
        // Kod paczkomatu MUSI dojść mailem do KLIENTA: to jego jedyne
        // potwierdzenie, gdzie odbierze paczkę. Mail sprzedawcy celowo tego nie
        // niesie — jest powiadomieniem („szczegóły w panelu"), nie dokumentem do
        // nadania, i przy kurierze też nie zawiera adresu dostawy.
        $shop = $this->parcelLockerShop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null,
            'price_gross' => 100.00, 'vat_rate' => '23',
        ]);
        app(CartService::class)->add($product, 1);

        $order = app(OrderService::class)->place($shop, $this->parcelLockerData());

        $mail = EmailMessage::where('to_email', $order->buyer_email)->firstOrFail();
        $flat = json_encode($mail->intro_lines, JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('KRA01A', $flat);
        $this->assertStringContainsString('ul. Długa 5, 30-001 Kraków', $flat);
        $this->assertStringContainsString('Paczkomat InPost', $flat);
        // Adresu dostawy nie ma czego pokazywać — nie wolno go udawać.
        $this->assertStringNotContainsString('Adres dostawy', $flat);
    }

    public function test_parcel_locker_free_shipping_threshold_zeroes_cost(): void
    {
        $shop = $this->parcelLockerShop(freeFrom: 100.00);
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null,
            'price_gross' => 120.00, 'vat_rate' => '23',
        ]);
        app(CartService::class)->add($product, 1);

        $order = app(OrderService::class)->place($shop, $this->parcelLockerData());

        // Koszyk 120 ≥ próg 100 → gratis. Kurier progu nie ma i nadal liczy 15 —
        // progi obu metod są niezależne.
        $this->assertSame('0.00', $order->delivery_cost);
        $this->assertSame('120.00', $order->total_gross);
        $this->assertSame(15.0, $shop->fresh()->deliveryCostFor(DeliveryMethod::Courier, 120.0));
    }

    public function test_multiline_note_keeps_paragraphs_in_email(): void
    {
        $shop = $this->shop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null, 'price_gross' => 10.00,
        ]);
        app(CartService::class)->add($product, 1);

        $note = "Pierwszy akapit.\n\nDrugi akapit.\nDruga linia drugiego akapitu.";

        app(OrderService::class)->place($shop, array_merge($this->buyerData(), ['note' => $note]));

        $sellerEmail = EmailMessage::where('to_email', $shop->owner->email)->firstOrFail();

        // Blok uwag: nagłówek + akapity jako OSOBNE linie (pusta linia = odstęp
        // akapitu po sklejeniu <br>), a nie jedna zlana ściana tekstu.
        $noteBlock = collect($sellerEmail->intro_lines)
            ->first(fn ($block): bool => is_array($block) && ($block[0] ?? null) === '**Uwagi klienta:**');

        $this->assertSame([
            '**Uwagi klienta:**',
            'Pierwszy akapit.',
            '',
            'Drugi akapit.',
            'Druga linia drugiego akapitu.',
        ], $noteBlock);
    }

    public function test_ship_address_ignored_for_pickup(): void
    {
        $shop = $this->shop();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'stock' => null, 'price_gross' => 10.00,
        ]);
        app(CartService::class)->add($product, 1);

        // Adres wysyłki podany, ale metoda to odbiór → nie zapisujemy go, koszt 0.
        $order = app(OrderService::class)->place($shop, array_merge($this->buyerData(), [
            'ship_street' => 'Nie zapisać', 'ship_city' => 'Nie zapisać',
        ]));

        $this->assertNull($order->ship_street);
        $this->assertNull($order->ship_city);
        $this->assertSame('0.00', $order->delivery_cost);
    }
}
