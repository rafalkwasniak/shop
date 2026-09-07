<?php

namespace Tests\Feature\Seller;

use App\Livewire\Administrator\ShopManager;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Wygaśnięcie abonamentu jako STAN ODCZYTU, nie przepisanie danych.
 *
 * Snapshot uprawnień zostaje nietknięty na zawsze; po terminie `entitlement()`
 * czyta uprawnienia pakietu darmowego, a `rawEntitlement()` dalej pokazuje, co
 * klient kupił. Dzięki temu odnowienie to zmiana JEDNEJ DATY i wracają też
 * ręczne nadania — moduły dane komuś gestem poza pakietem.
 *
 * Daty „wygasłe" są tu o 30 dni w tyle, bo między terminem a wyłączeniem stoi
 * KARENCJA (`shop.subscription.grace_days`) — dzień po terminie funkcje jeszcze
 * działają. Testy karencji poniżej.
 */
class SubscriptionExpiryTest extends TestCase
{
    use RefreshDatabase;

    private function paidShop(array $attributes = []): Shop
    {
        return Shop::factory()->create([
            'package' => 'pavilion',
            'entitlements' => config('shop.packages.pavilion.entitlements'),
            'price_yearly' => 1500,
            ...$attributes,
        ]);
    }

    public function test_free_package_never_expires(): void
    {
        $shop = Shop::factory()->create(['package' => 'stall', 'subscription_ends_at' => now()->subYear()]);

        // Kramu nie ma jak wygasić — nie ma czego opłacać.
        $this->assertTrue($shop->subscriptionActive());
    }

    public function test_paid_package_with_a_future_date_is_active(): void
    {
        $shop = $this->paidShop(['subscription_ends_at' => now()->addMonth()]);

        $this->assertTrue($shop->subscriptionActive());
        $this->assertTrue($shop->entitlement('bulk_mail'));
        $this->assertSame(600, $shop->entitlement('max_products'));
    }

    public function test_expired_package_falls_back_to_free_entitlements(): void
    {
        $shop = $this->paidShop(['subscription_ends_at' => now()->subDays(30)]);

        $this->assertFalse($shop->subscriptionActive());

        // Efektywnie sklep ma uprawnienia Kramu…
        $this->assertFalse($shop->entitlement('bulk_mail'));
        $this->assertFalse($shop->entitlement('invoices'));
        $this->assertSame(60, $shop->entitlement('max_products'));

        // …ale SNAPSHOT jest nietknięty, więc widać, co klient kupił.
        $this->assertTrue($shop->rawEntitlement('bulk_mail'));
        $this->assertSame(600, $shop->rawEntitlement('max_products'));
    }

    public function test_renewal_is_a_single_date_change(): void
    {
        $shop = $this->paidShop(['subscription_ends_at' => now()->subDays(30)]);
        $this->assertFalse($shop->entitlement('discount_codes'));

        $shop->forceFill(['subscription_ends_at' => now()->addYear()])->save();

        // Nic nie trzeba odtwarzać — snapshot cały czas tam był.
        $this->assertTrue($shop->fresh()->entitlement('discount_codes'));
    }

    public function test_manual_grants_survive_expiry_and_come_back_after_renewal(): void
    {
        // Stragan z korespondencją seryjną nadaną gestem poza pakietem.
        $shop = Shop::factory()->create([
            'package' => 'booth',
            // `array_merge`, nie `+` — operator sumy tablic NIE nadpisuje
            // istniejących kluczy, a Stragan ma `bulk_mail => false`.
            'entitlements' => array_merge(config('shop.packages.booth.entitlements'), ['bulk_mail' => true]),
            'price_yearly' => 750,
            'subscription_ends_at' => now()->subDays(30),
        ]);

        $this->assertFalse($shop->entitlement('bulk_mail'), 'po wygaśnięciu nie działa');
        $this->assertTrue($shop->rawEntitlement('bulk_mail'), 'ale nadanie nie znika');

        $shop->forceFill(['subscription_ends_at' => now()->addYear()])->save();
        $this->assertTrue($shop->fresh()->entitlement('bulk_mail'), 'po opłacie wraca samo');
    }

    public function test_comped_shop_never_expires(): void
    {
        $shop = $this->paidShop(['comped' => true, 'subscription_ends_at' => now()->subYear()]);

        $this->assertTrue($shop->subscriptionActive());
        $this->assertTrue($shop->entitlement('bulk_mail'));
    }

    public function test_empty_date_on_a_paid_package_means_indefinitely(): void
    {
        // Konsola pozwala daty nie ustawić. Gdyby to znaczyło „wygasło",
        // dotychczasowe ręczne nadania zgasłyby z dnia na dzień.
        $shop = $this->paidShop(['subscription_ends_at' => null]);

        $this->assertTrue($shop->subscriptionActive());
        $this->assertTrue($shop->entitlement('bulk_mail'));
    }

    public function test_expiry_closes_real_gates_in_the_app(): void
    {
        $seller = User::factory()->consented()->create();
        $shop = $this->paidShop(['owner_id' => $seller->id, 'subscription_ends_at' => now()->subDays(30)]);

        // Bramki pytają `entitlement()`, więc gasną bez żadnych zmian u siebie.
        $this->actingAs($seller)->get(route('seller.mailings.index'))
            ->assertOk()
            ->assertSee('Wiadomości do klientów w pakiecie Pawilon');

        $this->actingAs($seller)->get(route('seller.mailings.create'))->assertForbidden();
    }

    public function test_grace_keeps_full_features_after_the_deadline(): void
    {
        $shop = $this->paidShop(['subscription_ends_at' => now()->subDay()]);

        // Spóźniony przelew nie może zgasić sklepu z dnia na dzień.
        $this->assertTrue($shop->subscriptionActive());
        $this->assertTrue($shop->inSubscriptionGrace());
        $this->assertTrue($shop->entitlement('bulk_mail'));
        $this->assertSame(600, $shop->entitlement('max_products'));
    }

    public function test_features_die_when_the_grace_period_runs_out(): void
    {
        $grace = (int) config('shop.subscription.grace_days');
        $shop = $this->paidShop(['subscription_ends_at' => now()->subDays($grace + 1)]);

        $this->assertFalse($shop->subscriptionActive());
        $this->assertFalse($shop->inSubscriptionGrace(), 'karencja się skończyła');
        $this->assertFalse($shop->entitlement('bulk_mail'));
    }

    public function test_grace_is_counted_from_the_paid_through_date(): void
    {
        $grace = (int) config('shop.subscription.grace_days');
        $endsAt = now()->subDays(2);
        $shop = $this->paidShop(['subscription_ends_at' => $endsAt]);

        // Data wyłączenia = termin + karencja; termin na fakturze zostaje ten sam.
        $this->assertSame(
            $endsAt->copy()->addDays($grace)->format('Y-m-d'),
            $shop->subscriptionLocksAt()->format('Y-m-d'),
        );
        $this->assertSame($grace - 2, $shop->graceDaysLeft());
    }

    public function test_free_and_comped_shops_have_nothing_to_lock(): void
    {
        $free = Shop::factory()->create(['package' => 'stall', 'subscription_ends_at' => now()->subYear()]);
        $comped = $this->paidShop(['comped' => true, 'subscription_ends_at' => now()->subYear()]);

        $this->assertNull($free->subscriptionLocksAt());
        $this->assertNull($comped->subscriptionLocksAt());
        $this->assertFalse($free->inSubscriptionGrace());
        $this->assertFalse($comped->inSubscriptionGrace());
    }

    public function test_effective_package_reads_as_the_free_one_after_expiry(): void
    {
        $shop = $this->paidShop(['subscription_ends_at' => now()->subDays(30)]);

        // Sprzedawca widzi Kram (decyzja Rafała), ale w bazie snapshot i pakiet
        // zostają — inaczej odnowienie nie byłoby zmianą jednej daty.
        $this->assertSame('stall', $shop->effectivePackage());
        $this->assertSame('Kram', $shop->effectivePackageName());
        $this->assertSame('pavilion', $shop->package);
        $this->assertSame('Pawilon', $shop->packageName());
    }

    public function test_admin_console_shows_what_the_client_bought_even_after_expiry(): void
    {
        $admin = User::factory()->consented()->create(['role' => 'admin']);
        $shop = $this->paidShop(['subscription_ends_at' => now()->subDays(30)]);

        $this->actingAs($admin);

        // Konsola musi pokazać snapshot (Pawilon), nie stan efektywny (Kram) —
        // inaczej zapis formularza wykasowałby to, co klient kupił.
        Livewire::test(ShopManager::class, ['shop' => $shop])
            ->assertSet('bulk_mail', true)
            ->assertSet('max_products', 600);
    }

    public function test_admin_console_keeps_the_ai_limit_in_the_snapshot(): void
    {
        $admin = User::factory()->consented()->create(['role' => 'admin']);
        $shop = $this->paidShop(['subscription_ends_at' => now()->addYear()]);

        $this->actingAs($admin);

        // Ręcznie podniesiony limit AI musi przeżyć zapis całego snapshotu.
        Livewire::test(ShopManager::class, ['shop' => $shop])
            ->set('ai_weekly_limit', 2000)
            ->call('save');

        $this->assertSame(2000, $shop->fresh()->rawEntitlement('ai_weekly_limit'));
        $this->assertSame(2000, $shop->fresh()->entitlement('ai_weekly_limit'));
    }
}
