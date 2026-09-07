<?php

namespace Tests\Feature\Seller;

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `packages:sync-entitlements` — dosypanie istniejącym sklepom tego, co ich
 * pakiet daje dziś. Cała wartość tej komendy siedzi w jednym słowie: W GÓRĘ.
 */
class SyncPackageEntitlementsTest extends TestCase
{
    use RefreshDatabase;

    private function shopWithSnapshot(string $package, array $snapshot): Shop
    {
        $shop = Shop::factory()->package($package)->create();
        $shop->forceFill(['entitlements' => array_merge($shop->entitlements ?? [], $snapshot)])->save();

        return $shop->fresh();
    }

    public function test_raised_limit_reaches_an_existing_shop(): void
    {
        $shop = $this->shopWithSnapshot('stall', ['max_products' => 24]);

        $this->artisan('packages:sync-entitlements', ['--apply' => true])->assertSuccessful();

        $this->assertSame(
            (int) config('shop.packages.stall.entitlements.max_products'),
            $shop->fresh()->entitlement('max_products'),
        );
    }

    /**
     * Sedno bezpieczeństwa tej komendy. Sklep, który ma WIĘCEJ, niż daje dziś
     * jego pakiet, nie może stracić — to albo ręczne nadanie, albo pozostałość
     * po hojniejszym cenniku, za którą ktoś zapłacił. Obniżka ma własną,
     * świadomą ścieżkę: zakup niższego pakietu, który ukrywa nadwyżkę i tłumaczy
     * to mailem.
     */
    public function test_a_more_generous_snapshot_is_never_lowered(): void
    {
        $shop = $this->shopWithSnapshot('stall', ['max_products' => 5000]);

        $this->artisan('packages:sync-entitlements', ['--apply' => true])->assertSuccessful();

        $this->assertSame(5000, $shop->fresh()->entitlement('max_products'));
    }

    public function test_manual_grant_survives(): void
    {
        $shop = $this->shopWithSnapshot('stall', ['bulk_mail' => true]);

        $this->artisan('packages:sync-entitlements', ['--apply' => true])->assertSuccessful();

        $this->assertTrue($shop->fresh()->entitlement('bulk_mail'));
    }

    /**
     * Uprawnienie, które powstało PO zakupie, nie ma w snapshocie ani wartości
     * `false` — nie ma go wcale. Musi dojść, inaczej pakiet obiecuje w cenniku
     * coś, czego sklep nie dostaje.
     */
    public function test_entitlement_added_after_purchase_is_filled_in(): void
    {
        $shop = Shop::factory()->package('pavilion')->create();
        $snapshot = $shop->entitlements;
        unset($snapshot['max_employees']);
        $shop->forceFill(['entitlements' => $snapshot])->save();

        $this->artisan('packages:sync-entitlements', ['--apply' => true])->assertSuccessful();

        $this->assertSame(
            (int) config('shop.packages.pavilion.entitlements.max_employees'),
            $shop->fresh()->entitlement('max_employees'),
        );
    }

    public function test_without_apply_nothing_is_written(): void
    {
        $shop = $this->shopWithSnapshot('stall', ['max_products' => 24]);

        $this->artisan('packages:sync-entitlements')->assertSuccessful();

        $this->assertSame(24, $shop->fresh()->entitlement('max_products'));
    }
}
