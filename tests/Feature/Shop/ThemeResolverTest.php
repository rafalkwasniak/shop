<?php

namespace Tests\Feature\Shop;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Resolver motywu sklepu (Shop::templateSlug/templateName/themePalette/themeTokens).
 * Motyw to referencja (kolumny template + theme JSON) rozwiązywana z config/themes.php,
 * z siatką bezpieczeństwa na wycofane szablony/palety.
 */
class ThemeResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_shop_uses_default_template_and_palette(): void
    {
        $shop = Shop::factory()->create();

        $this->assertSame(config('themes.default_template'), $shop->templateSlug());
        $this->assertSame('velvet_cloud', $shop->templateSlug());
        $this->assertSame('sky', $shop->themePalette());
        $this->assertSame('Aksamitna chmurka', $shop->templateName());
    }

    public function test_default_palette_tokens_are_resolved(): void
    {
        $shop = Shop::factory()->create();

        $tokens = $shop->themeTokens();

        $this->assertSame(
            ['brand', 'brand_ink', 'surface', 'ink'],
            array_keys($tokens),
        );
        $this->assertSame('#3B82F6', $tokens['brand']);
    }

    public function test_chosen_template_and_palette_win(): void
    {
        $shop = Shop::factory()->create([
            'template' => 'green_nook',
            'theme' => ['palette' => 'moss'],
        ]);

        $this->assertSame('green_nook', $shop->templateSlug());
        $this->assertSame('Zielony zakątek', $shop->templateName());
        $this->assertSame('moss', $shop->themePalette());
        $this->assertSame('#6B8E4E', $shop->themeTokens()['brand']);
    }

    public function test_template_chrome_resolves_with_neutral_fallback(): void
    {
        // Stara rodzina: szary chrome (dotychczasowy wygląd).
        $this->assertSame('neutral', Shop::factory()->create()->templateChrome());

        // Nowa rodzina biało-kolorowa: pasek i stopka w kolorze marki.
        $shop = Shop::factory()->create(['template' => 'white_red']);
        $this->assertSame('brand', $shop->templateChrome());

        // Szablon bez klucza `chrome` (albo z nieznaną wartością) spada na neutral.
        config(['themes.templates.white_red.chrome' => 'zigzag']);
        $this->assertSame('neutral', $shop->templateChrome());
    }

    public function test_template_chrome_texture_resolves_with_none_fallback(): void
    {
        // Stara rodzina: gładki chrome, bez wzoru.
        $this->assertSame('none', Shop::factory()->create()->templateChromeTexture());

        // Nowa rodzina deklaruje fakturę per szablon.
        $shop = Shop::factory()->create(['template' => 'white_red']);
        $this->assertSame('dots', $shop->templateChromeTexture());

        // Nieznana wartość spada na brak wzoru.
        config(['themes.templates.white_red.chrome_texture' => 'zebra']);
        $this->assertSame('none', $shop->templateChromeTexture());
    }

    public function test_template_card_mix_resolves_with_global_default(): void
    {
        // Stara rodzina nie deklaruje `card_mix` → globalny domyślny (subtelny).
        $this->assertSame(config('themes.card_mix'), Shop::factory()->create()->templateCardMix());

        // Biała rodzina nadpisuje domyślny własną (mocniejszą) wartością.
        // Liczbę bierzemy z configu, nie na sztywno — jest dostrajana na oko.
        $shop = Shop::factory()->create(['template' => 'white_blue']);
        $this->assertSame(config('themes.templates.white_blue.card_mix'), $shop->templateCardMix());
        $this->assertNotSame(config('themes.card_mix'), $shop->templateCardMix());

        // Wartość spoza zakresu procentów jest przycinana.
        config(['themes.templates.white_blue.card_mix' => 250]);
        $this->assertSame(100, $shop->templateCardMix());
    }

    public function test_listing_density_scales_with_active_product_count(): void
    {
        $shop = Shop::factory()->create();
        $addActive = fn (int $n) => Product::factory()->count($n)->create(['shop_id' => $shop->id, 'is_active' => true]);

        // Mały katalog: 3 kolumny, 9 na stronę (duże, wyraziste kafle).
        $addActive(5); // 5
        $this->assertSame(['columns' => 3, 'per_page' => 9], $shop->listingDensity());

        $addActive(23); // 28 → 3×4 = 12 (żeby zmieścić w 3 podstronach)
        $this->assertSame(['columns' => 3, 'per_page' => 12], $shop->listingDensity());

        $addActive(18); // 46 → skok na 4 kolumny, 4×4 = 16
        $this->assertSame(['columns' => 4, 'per_page' => 16], $shop->listingDensity());

        $addActive(30); // 76 → sufit 4×5 = 20 (dalej rosną tylko podstrony)
        $this->assertSame(['columns' => 4, 'per_page' => 20], $shop->listingDensity());
    }

    public function test_listing_density_ignores_hidden_products(): void
    {
        $shop = Shop::factory()->create();
        Product::factory()->count(5)->create(['shop_id' => $shop->id, 'is_active' => true]);
        Product::factory()->count(60)->create(['shop_id' => $shop->id, 'is_active' => false]);

        // Liczą się tylko aktywne (5) — ukryte nie podbijają skali.
        $this->assertSame(['columns' => 3, 'per_page' => 9], $shop->listingDensity());
    }

    public function test_unknown_template_falls_back_to_default(): void
    {
        $shop = Shop::factory()->create(['template' => 'does_not_exist']);

        $this->assertSame('velvet_cloud', $shop->templateSlug());
        $this->assertSame('sky', $shop->themePalette());
    }

    public function test_palette_not_in_template_falls_back_to_template_default(): void
    {
        // 'moss' należy do green_nook, nie do velvet_cloud → spada na domyślną 'sky'.
        $shop = Shop::factory()->create([
            'template' => 'velvet_cloud',
            'theme' => ['palette' => 'moss'],
        ]);

        $this->assertSame('sky', $shop->themePalette());
    }
}
