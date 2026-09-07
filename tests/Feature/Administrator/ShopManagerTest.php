<?php

namespace Tests\Feature\Administrator;

use App\Livewire\Administrator\ShopManager;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Konsola admina — edytor sklepu: dostęp, preset pakietu, zapis snapshotu
 * (uprawnienia + cena + data + comped) oraz zachowanie ręcznych nadpisań.
 */
class ShopManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_editor(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('booth')->create(['name' => 'Kwiaciarnia Zosia']);

        $this->actingAs($admin)
            ->get(route('administrator.shops.edit', $shop))
            ->assertOk()
            ->assertSee('Kwiaciarnia Zosia')
            ->assertSee('Nadaj pakiet');
    }

    public function test_seller_cannot_open_editor(): void
    {
        $seller = User::factory()->create();
        $shop = Shop::factory()->create();

        $this->actingAs($seller)
            ->get(route('administrator.shops.edit', $shop))
            ->assertForbidden();
    }

    public function test_apply_preset_fills_form_without_saving(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('stall')->create();

        Livewire::actingAs($admin)
            ->test(ShopManager::class, ['shop' => $shop])
            ->call('applyPreset', 'pavilion')
            ->assertSet('package', 'pavilion')
            ->assertSet('max_products', 600)
            ->assertSet('order_editing', true)
            ->assertSet('bulk_mail', true)
            ->assertSet('price_yearly', '1500');

        // Preset nie zapisuje — sklep wciąż stall dopóki nie ma „Zapisz".
        $this->assertSame('stall', $shop->fresh()->package);
    }

    public function test_save_writes_snapshot_to_shop(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('stall')->create();

        Livewire::actingAs($admin)
            ->test(ShopManager::class, ['shop' => $shop])
            ->call('applyPreset', 'booth')
            ->set('subscription_ends_at', '2027-01-15')
            ->call('save')
            ->assertRedirect(route('administrator.shops.edit', $shop));

        $fresh = $shop->fresh();
        $this->assertSame('booth', $fresh->package);
        $this->assertTrue($fresh->entitlement('online_payments'));
        $this->assertTrue($fresh->entitlement('invoices'));
        $this->assertFalse($fresh->entitlement('order_editing'));
        $this->assertSame(300, $fresh->entitlement('max_products'));
        $this->assertSame(750.0, $fresh->priceYearly());
        $this->assertSame('2027-01-15', $fresh->subscription_ends_at->format('Y-m-d'));
    }

    public function test_save_preserves_manual_override_beyond_package(): void
    {
        // Dobry klient na Straganie dostaje korespondencję seryjną (spoza pakietu).
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('booth')->create();

        Livewire::actingAs($admin)
            ->test(ShopManager::class, ['shop' => $shop])
            ->set('bulk_mail', true)   // nadpisanie ponad pakiet
            ->call('save');

        $fresh = $shop->fresh();
        $this->assertSame('booth', $fresh->package);       // pakiet zostaje Straganem
        $this->assertTrue($fresh->entitlement('bulk_mail')); // ale moduł włączony
    }

    public function test_save_sets_comped_and_individual_price(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('booth')->create();

        Livewire::actingAs($admin)
            ->test(ShopManager::class, ['shop' => $shop])
            ->set('comped', true)
            ->set('price_yearly', '50')
            ->call('save');

        $fresh = $shop->fresh();
        $this->assertTrue($fresh->comped);
        $this->assertSame(50.0, $fresh->priceYearly());
    }

    /**
     * SEDNO KONSOLI: uprawnienie nadaje się GESTEM, poza pakietem. Kram nie ma
     * kont pracowniczych w cenniku, ale admin musi móc je dać konkretnemu
     * sklepowi — dokładnie tak, jak daje wysyłkę kurierską.
     */
    public function test_admin_grants_employee_seats_outside_the_package(): void
    {
        $shop = Shop::factory()->package('stall')->create();
        $this->assertFalse($shop->allowsEmployees());

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(ShopManager::class, ['shop' => $shop])
            ->set('max_employees', 3)
            ->call('save')
            ->assertHasNoErrors();

        $fresh = $shop->fresh();
        $this->assertTrue($fresh->allowsEmployees());
        $this->assertSame(3, $fresh->entitlement('max_employees'));
        $this->assertSame(3, $fresh->employeeSlotsLeft());
        // Pakiet zostaje Kramem — to nadanie, nie awans.
        $this->assertSame('stall', $fresh->package);
    }

    /**
     * REGRESJA NA PUŁAPKĘ, KTÓRA ZADZIAŁAŁA JUŻ DWA RAZY. Zapis odbudowuje cały
     * snapshot, więc uprawnienie liczbowe bez pola w formularzu znikało przy
     * każdym „Zapisz" — najpierw pula AI, potem konta pracownicze. Ten test
     * pilnuje KAŻDEGO klucza z listy naraz, więc złapie też następny dołożony.
     */
    public function test_saving_preserves_every_numeric_entitlement(): void
    {
        $shop = Shop::factory()->package('pavilion')->create();
        $shop->forceFill(['entitlements' => array_merge($shop->entitlements, [
            'max_products' => 999,
            'ai_weekly_limit' => 777,
            'max_employees' => 9,
        ])])->save();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(ShopManager::class, ['shop' => $shop->fresh()])
            ->call('save')
            ->assertHasNoErrors();

        $snapshot = $shop->fresh()->entitlements;

        foreach (['max_products' => 999, 'ai_weekly_limit' => 777, 'max_employees' => 9] as $key => $value) {
            $this->assertArrayHasKey($key, $snapshot, "Zapis konsoli zgubil uprawnienie {$key}.");
            $this->assertSame($value, $snapshot[$key]);
        }
    }

    public function test_preset_fills_employee_seats_from_the_package(): void
    {
        $shop = Shop::factory()->package('stall')->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(ShopManager::class, ['shop' => $shop])
            ->call('applyPreset', 'pavilion')
            ->assertSet('max_employees', (int) config('shop.packages.pavilion.entitlements.max_employees'))
            ->call('applyPreset', 'stall')
            ->assertSet('max_employees', 0);
    }
}
