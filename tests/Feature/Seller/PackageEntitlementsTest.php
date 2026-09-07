<?php

namespace Tests\Feature\Seller;

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PackageEntitlementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_package_snapshots_entitlements_from_config(): void
    {
        $shop = Shop::factory()->create();

        $shop->assignPackage('pavilion');

        $fresh = $shop->fresh();
        $this->assertSame('pavilion', $fresh->package);
        $this->assertSame(600, $fresh->entitlement('max_products'));
        $this->assertTrue($fresh->entitlement('order_editing'));
        // Snapshot obejmuje też cenę roczną (BRUTTO) pakietu.
        $this->assertSame(1500.0, $fresh->priceYearly());
    }

    public function test_assign_unknown_package_throws(): void
    {
        $shop = Shop::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $shop->assignPackage('nieistniejacy');
    }

    public function test_entitlement_reads_snapshot_not_current_config(): void
    {
        // Sklep kupił „stall" z limitem 60 (snapshot na sklepie).
        $shop = Shop::factory()->package('stall')->create();

        // Później zmieniamy definicję pakietu w configu — snapshot NIE drgnie.
        config(['shop.packages.stall.entitlements.max_products' => 999]);

        $this->assertSame(60, $shop->entitlement('max_products'));
    }

    public function test_entitlement_falls_back_to_config_when_not_in_snapshot(): void
    {
        // Sklep bez snapshotu (legacy) — resolver sięga do configu aktualnego pakietu.
        $shop = Shop::factory()->create(['package' => 'booth', 'entitlements' => null]);

        $this->assertSame(300, $shop->entitlement('max_products'));
        $this->assertTrue($shop->entitlement('online_payments'));
    }

    public function test_entitlement_falls_back_for_key_missing_from_snapshot(): void
    {
        // Nowe uprawnienie dodane do configu po zakupie — brak go w snapshocie,
        // więc resolver bierze wartość z definicji pakietu.
        $shop = Shop::factory()->create([
            'package' => 'stall',
            'entitlements' => ['max_products' => 60], // stary, niepełny snapshot
        ]);
        config(['shop.packages.stall.entitlements.new_feature' => true]);

        $this->assertTrue($shop->entitlement('new_feature'));
    }

    public function test_package_name_resolves_polish_label_from_slug(): void
    {
        $shop = Shop::factory()->package('booth')->create();

        $this->assertSame('Stragan', $shop->packageName());

        // Nazwa zmienialna z configu bez ruszania sluga w bazie.
        config(['shop.packages.booth.name' => 'Bazar']);
        $this->assertSame('Bazar', $shop->fresh()->packageName());
        $this->assertSame('booth', $shop->fresh()->package);
    }

    public function test_factory_materializes_default_package_snapshot(): void
    {
        $shop = Shop::factory()->create();

        $this->assertSame('stall', $shop->package);
        $this->assertSame(60, $shop->entitlement('max_products'));
        $this->assertFalse($shop->entitlement('online_payments'));
    }

    /**
     * Zatwierdzona macierz 3 tierów (Kram/Stragan/Pawilon) — 8 kluczy.
     *
     * @param  array<string, mixed>  $expected
     */
    #[DataProvider('matrixProvider')]
    public function test_package_matrix_matches_agreed_values(string $slug, array $expected): void
    {
        $shop = Shop::factory()->package($slug)->create();

        foreach ($expected as $key => $value) {
            $this->assertSame(
                $value,
                $shop->entitlement($key),
                "Pakiet {$slug}: uprawnienie {$key} niezgodne z macierzą."
            );
        }
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function matrixProvider(): array
    {
        return [
            'Kram (stall)' => ['stall', [
                'max_products' => 60,
                'online_payments' => false,
                'courier_shipping' => false,
                'invoices' => false,
                'ga_analytics' => false,
                'order_editing' => false,
                'discount_codes' => false,
                'bulk_mail' => false,
            ]],
            'Stragan (booth)' => ['booth', [
                'max_products' => 300,
                'online_payments' => true,
                'courier_shipping' => true,
                'invoices' => true,
                'ga_analytics' => true,
                'order_editing' => false,
                'discount_codes' => false,
                'bulk_mail' => false,
            ]],
            'Pawilon (pavilion)' => ['pavilion', [
                'max_products' => 600,
                'online_payments' => true,
                'courier_shipping' => true,
                'invoices' => true,
                'ga_analytics' => true,
                'order_editing' => true,
                'discount_codes' => true,
                'bulk_mail' => true,
            ]],
        ];
    }

    public function test_prices_match_agreed_ladder(): void
    {
        // BRUTTO, rok = 10× miesiąc (0 / 75 / 150 zł/mc).
        $this->assertSame(0.0, Shop::factory()->package('stall')->create()->priceYearly());
        $this->assertSame(750.0, Shop::factory()->package('booth')->create()->priceYearly());
        $this->assertSame(1500.0, Shop::factory()->package('pavilion')->create()->priceYearly());
    }

    public function test_price_yearly_prefers_per_shop_snapshot(): void
    {
        // Indywidualna cena (deal per klient) wygrywa nad cennikiem pakietu.
        $shop = Shop::factory()->create(['package' => 'booth', 'price_yearly' => 50]);

        $this->assertSame(50.0, $shop->priceYearly());
    }

    public function test_price_yearly_falls_back_to_config_when_snapshot_null(): void
    {
        $shop = Shop::factory()->create(['package' => 'booth', 'price_yearly' => null]);

        $this->assertSame(750.0, $shop->priceYearly());
    }

    public function test_custom_domain_key_is_removed_from_config(): void
    {
        // Domena odłożona — klucz świadomie wycięty; test broni przed powrotem.
        foreach (['stall', 'booth', 'pavilion'] as $slug) {
            $this->assertArrayNotHasKey(
                'custom_domain',
                config("shop.packages.{$slug}.entitlements")
            );
        }
    }
}
