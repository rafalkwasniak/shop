<?php

namespace Tests\Feature\Shop;

use App\Models\Customer;
use App\Models\EmailMessage;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ReservedSlug;
use App\Models\Shop;
use App\Models\ShopEmployee;
use App\Services\ShopEraser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Silnik usuwania sklepu. Sprawdza przede wszystkim to, czego kaskada FK NIE
 * robi sama: pliki, tabele bez klucza obcego i kwarantannę adresu.
 */
class ShopEraserTest extends TestCase
{
    use RefreshDatabase;

    private function eraser(): ShopEraser
    {
        return app(ShopEraser::class);
    }

    public function test_erase_removes_shop_owner_and_whole_tenant_tree(): void
    {
        $shop = Shop::factory()->create(['slug' => 'kwiatki-anny']);
        $owner = $shop->owner;
        $product = Product::factory()->for($shop)->create();
        $order = Order::factory()->for($shop)->create();
        $customer = Customer::factory()->for($shop)->create();
        $page = Page::factory()->for($shop)->create();

        $this->eraser()->erase($shop);

        $this->assertDatabaseMissing('shops', ['id' => $shop->id]);
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }

    public function test_erase_removes_files_of_products_shop_and_owner(): void
    {
        Storage::fake('public');

        $shop = Shop::factory()->create();
        $product = Product::factory()->for($shop)->create();

        Storage::disk('public')->put('products/'.$product->id.'/zdjecie.webp', 'x');
        Storage::disk('public')->put('shops/'.$shop->id.'/logo.png', 'x');
        Storage::disk('public')->put('og/'.$shop->id.'/karta.jpg', 'x');
        Storage::disk('public')->put('users/'.$shop->owner->id.'/awatar.jpg', 'x');

        $this->eraser()->erase($shop);

        Storage::disk('public')->assertMissing('products/'.$product->id.'/zdjecie.webp');
        Storage::disk('public')->assertMissing('shops/'.$shop->id.'/logo.png');
        Storage::disk('public')->assertMissing('og/'.$shop->id.'/karta.jpg');
        Storage::disk('public')->assertMissing('users/'.$shop->owner->id.'/awatar.jpg');
    }

    /**
     * Zdjęcia produktu miękko usuniętego wciąż leżą na dysku — hook
     * `ProductImage::deleting` ich nie tknął, bo rekord produktu tylko dostał
     * `deleted_at`. Kaskada FK też ich nie ruszy, więc muszą zniknąć tutaj.
     */
    public function test_erase_removes_files_of_soft_deleted_products(): void
    {
        Storage::fake('public');

        $shop = Shop::factory()->create();
        $product = Product::factory()->for($shop)->create();
        $product->images()->create(['path' => 'products/'.$product->id.'/stare.webp', 'position' => 0]);
        Storage::disk('public')->put('products/'.$product->id.'/stare.webp', 'x');
        $product->delete();

        $this->eraser()->erase($shop);

        Storage::disk('public')->assertMissing('products/'.$product->id.'/stare.webp');
    }

    public function test_erase_clears_tables_without_foreign_keys(): void
    {
        $shop = Shop::factory()->create();
        $owner = $shop->owner;

        EmailMessage::create([
            'shop_id' => $shop->id,
            'to_email' => 'klient@example.test',
            'subject' => 'Zamówienie przyjęte',
            'heading' => 'Dziękujemy',
        ]);

        DB::table('sessions')->insert([
            'id' => 'sesja-wlasciciela',
            'user_id' => $owner->id,
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => $owner->email,
            'token' => 'token',
            'created_at' => now(),
        ]);

        $this->eraser()->erase($shop);

        $this->assertDatabaseMissing('email_messages', ['shop_id' => $shop->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesja-wlasciciela']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $owner->email]);
    }

    /**
     * Pożegnanie ma `shop_id = null` właśnie po to, żeby przeżyć czystkę
     * `email_messages` tego sklepu — inaczej nigdy by nie wyszło z outboxu.
     */
    public function test_farewell_email_survives_the_purge(): void
    {
        $shop = Shop::factory()->create(['name' => 'Kwiatki Anny']);
        $owner = $shop->owner;

        $this->eraser()->erase($shop);

        $message = EmailMessage::where('to_email', $owner->email)->firstOrFail();

        $this->assertNull($message->shop_id);
        $this->assertNull($message->sent_at);
        $this->assertStringContainsString('Kwiatki Anny', $message->subject);
    }

    public function test_erase_puts_the_address_into_quarantine(): void
    {
        $shop = Shop::factory()->create(['slug' => 'kwiatki-anny']);

        $this->eraser()->erase($shop);

        $reserved = ReservedSlug::where('slug', 'kwiatki-anny')->firstOrFail();

        $this->assertTrue(
            $reserved->released_at->isSameDay(now()->addDays(config('shop.deletion.slug_quarantine_days'))),
        );
    }

    public function test_registration_rejects_a_quarantined_address(): void
    {
        ReservedSlug::create(['slug' => 'wiazanki-malgosi', 'released_at' => now()->addDays(30)]);

        $this->post(route('register.store'), [
            'shop_name' => 'Wiązanki Małgosi',
            'name' => 'Anna',
            'surname' => 'Nowak',
            'email' => 'anna@sklep.test',
            'terms' => '1',
            'privacy' => '1',
        ])->assertSessionHasErrors('slug');

        $this->assertDatabaseMissing('users', ['email' => 'anna@sklep.test']);
    }

    public function test_registration_accepts_an_address_after_quarantine_ends(): void
    {
        ReservedSlug::create(['slug' => 'wiazanki-malgosi', 'released_at' => now()->subDay()]);

        $this->post(route('register.store'), [
            'shop_name' => 'Wiązanki Małgosi',
            'name' => 'Anna',
            'surname' => 'Nowak',
            'email' => 'anna@sklep.test',
            'terms' => '1',
            'privacy' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shops', ['slug' => 'wiazanki-malgosi']);
    }

    public function test_purge_erases_only_shops_past_their_deadline(): void
    {
        $due = Shop::factory()->create();
        $due->forceFill(['deletion_scheduled_at' => now()->subMinute()])->save();

        $waiting = Shop::factory()->create();
        $waiting->forceFill(['deletion_scheduled_at' => now()->addDays(3)])->save();

        $untouched = Shop::factory()->create();

        $this->artisan('shops:purge')->assertSuccessful();

        $this->assertDatabaseMissing('shops', ['id' => $due->id]);
        $this->assertDatabaseHas('shops', ['id' => $waiting->id]);
        $this->assertDatabaseHas('shops', ['id' => $untouched->id]);
    }

    public function test_purge_releases_expired_reservations_only(): void
    {
        ReservedSlug::create(['slug' => 'stary-adres', 'released_at' => now()->subDay()]);
        ReservedSlug::create(['slug' => 'swiezy-adres', 'released_at' => now()->addDays(30)]);

        $this->artisan('shops:purge')->assertSuccessful();

        $this->assertDatabaseMissing('reserved_slugs', ['slug' => 'stary-adres']);
        $this->assertDatabaseHas('reserved_slugs', ['slug' => 'swiezy-adres']);
    }

    /**
     * Kasujemy JEDEN sklep, nie bazę — sąsiad z własnym właścicielem, produktami
     * i mailami musi wyjść z operacji nietknięty.
     */
    public function test_erase_leaves_other_shops_alone(): void
    {
        $shop = Shop::factory()->create();
        $neighbour = Shop::factory()->create();
        $neighbourProduct = Product::factory()->for($neighbour)->create();

        EmailMessage::create([
            'shop_id' => $neighbour->id,
            'to_email' => 'klient@example.test',
            'subject' => 'Zamówienie przyjęte',
            'heading' => 'Dziękujemy',
        ]);

        $this->eraser()->erase($shop);

        $this->assertDatabaseHas('shops', ['id' => $neighbour->id]);
        $this->assertDatabaseHas('users', ['id' => $neighbour->owner_id]);
        $this->assertDatabaseHas('products', ['id' => $neighbourProduct->id]);
        $this->assertDatabaseHas('email_messages', ['shop_id' => $neighbour->id]);
    }

    /**
     * LUKA WYŁAPANA 07.09, po uznaniu modułu pracowników za skończony. Kaskada
     * FK zdejmowała członkostwo, ale KONTO pracownika zostawało: imię, nazwisko
     * i adres e-mail obcej osoby, trzymane po sklepie, którego już nie ma —
     * razem z działającym logowaniem. Sprzedawcy mówimy, że usuwamy wszystko.
     */
    public function test_erasing_a_shop_removes_its_employees_accounts(): void
    {
        $shop = Shop::factory()->create();
        $employment = ShopEmployee::factory()->create(['shop_id' => $shop->getKey()]);
        $employee = $employment->user;

        app(ShopEraser::class)->erase($shop->fresh());

        $this->assertDatabaseMissing('users', ['id' => $employee->getKey()]);
        $this->assertDatabaseMissing('shop_employees', ['id' => $employment->getKey()]);
    }

    /**
     * Osoba pracująca TAKŻE gdzie indziej traci wyłącznie to jedno członkostwo.
     * Skasowanie jej konta odcięłoby ją od cudzego sklepu, który z tą decyzją
     * nie ma nic wspólnego.
     */
    public function test_employee_working_elsewhere_keeps_their_account(): void
    {
        $closing = Shop::factory()->create();
        // Drugi sklep na Pawilonie: bez pakietu dającego konta pracownicze
        // `currentShop()` i tak zwróciłby null — i test badałby co innego,
        // niż zamierza (patrz ShopEmployee::isEffective()).
        $other = Shop::factory()->create();
        $other->assignPackage('pavilion');
        $other->save();
        $other = $other->fresh();

        $employment = ShopEmployee::factory()->create(['shop_id' => $closing->getKey()]);
        $employee = $employment->user;

        $second = new ShopEmployee(['permissions' => ['orders'], 'invited_at' => now(), 'accepted_at' => now()]);
        $second->user_id = $employee->getKey();
        $other->employees()->save($second);

        app(ShopEraser::class)->erase($closing->fresh());

        $this->assertDatabaseHas('users', ['id' => $employee->getKey()]);
        $this->assertTrue($other->is($employee->fresh()->currentShop()));
    }
}
