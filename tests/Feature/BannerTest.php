<?php

namespace Tests\Feature;

use App\Livewire\Administrator\ShopManager;
use App\Models\Shop;
use App\Models\ShopBanner;
use App\Models\User;
use App\Services\BannerImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Banery strony głównej: panel (Wygląd → Banery), slider na storefroncie
 * i limit z pakietu (`max_banners`: Kram 1 / Stragan 6 / Pawilon 10,
 * sklep dedykowany 10).
 *
 * Najważniejsze reguły:
 *   - jeden baner = obrazek bez skryptu, kilka = slider;
 *   - po zejściu na niższy pakiet nic nie kasujemy, gasną banery z KOŃCA listy;
 *   - pliki wariantów znikają razem z banerem i przy podmianie obrazka.
 */
class BannerTest extends TestCase
{
    use RefreshDatabase;

    private function seller(string $package = 'pavilion'): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->active()->create(['owner_id' => $seller->id]);
        $shop->comped = true;
        $shop->assignPackage($package);

        return [$seller, $shop->fresh()];
    }

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    private function banner(Shop $shop, array $attributes = [], int $width = 2000, int $height = 1000): ShopBanner
    {
        $image = app(BannerImageService::class)->store(UploadedFile::fake()->image('b.jpg', $width, $height), $shop->id);

        $banner = new ShopBanner(array_merge(['alt' => 'Baner testowy', 'is_active' => true], $attributes));
        $banner->shop_id = $shop->id;
        $banner->position = $attributes['position'] ?? (int) $shop->banners()->max('position') + 1;
        $banner->forceFill($image)->save();

        return $banner;
    }

    // --- Pakiety --------------------------------------------------------------

    public function test_package_limits_are_1_6_10_and_10_for_dedicated(): void
    {
        $this->assertSame(1, config('shop.packages.stall.entitlements.max_banners'));
        $this->assertSame(6, config('shop.packages.booth.entitlements.max_banners'));
        $this->assertSame(10, config('shop.packages.pavilion.entitlements.max_banners'));
        $this->assertSame(10, config('shop.packages.dedicated.entitlements.max_banners'));
    }

    /**
     * Konsola admina buduje snapshot uprawnień WYŁĄCZNIE z kluczy z tej listy —
     * klucz spoza niej ginąłby przy każdym zapisie (trafione już dwa razy).
     */
    public function test_admin_console_knows_the_banner_limit(): void
    {
        $this->assertArrayHasKey('max_banners', (new ShopManager)->numericEntitlements());
    }

    /** Sklep z uprawnieniami sprzed tej funkcji dostaje limit z cennika. */
    public function test_shop_without_the_key_in_its_snapshot_falls_back_to_the_price_list(): void
    {
        [, $shop] = $this->seller('booth');
        $shop->forceFill(['entitlements' => collect($shop->entitlements)->except('max_banners')->all()])->save();

        $this->assertSame(6, (int) $shop->fresh()->entitlement('max_banners'));
    }

    // --- Obrazki ------------------------------------------------------------------

    public function test_image_becomes_webp_variants_no_wider_than_the_original(): void
    {
        [, $shop] = $this->seller();

        $wide = $this->banner($shop, [], 2400, 1200);
        $this->assertSame([640, 1280, 1920], $wide->widths());
        $this->assertSame([1920, 960], [$wide->image_width, $wide->image_height]);

        $narrow = $this->banner($shop, [], 1000, 500);
        $this->assertSame([640, 1000], $narrow->widths());

        foreach ([...$wide->paths(), ...$narrow->paths()] as $path) {
            Storage::disk('public')->assertExists($path);
            $this->assertStringEndsWith('.webp', $path);
        }
    }

    // --- Panel ------------------------------------------------------------------

    public function test_seller_adds_a_banner(): void
    {
        [$seller, $shop] = $this->seller();

        $this->actingAs($seller)
            ->post(route('seller.banners.store'), [
                'image' => UploadedFile::fake()->image('baner.jpg', 1920, 960),
                'alt' => 'Nowa kawa',
                'link_url' => 'aracaffee.pl/kawa',
                'is_active' => '1',
            ])
            ->assertRedirect(route('seller.banners.index'));

        $banner = $shop->banners()->sole();
        $this->assertSame('Nowa kawa', $banner->alt);
        // Adres bez schematu dostaje https:// — sprzedawcy kopiują adresy różnie.
        $this->assertSame('https://aracaffee.pl/kawa', $banner->link_url);
        Storage::disk('public')->assertExists($banner->paths());
    }

    public function test_alt_is_required_and_narrow_images_are_rejected(): void
    {
        [$seller, $shop] = $this->seller();

        $this->actingAs($seller)
            ->post(route('seller.banners.store'), [
                'image' => UploadedFile::fake()->image('waski.jpg', 500, 300),
                'alt' => '',
            ])
            ->assertSessionHasErrors(['image', 'alt']);

        $this->assertSame(0, $shop->banners()->count());
    }

    public function test_link_must_be_a_shop_path_or_a_full_address(): void
    {
        [$seller] = $this->seller();

        $this->actingAs($seller)
            ->post(route('seller.banners.store'), [
                'image' => UploadedFile::fake()->image('b.jpg', 1920, 960),
                'alt' => 'X',
                'link_url' => 'nie jest adresem',
            ])
            ->assertSessionHasErrors('link_url');
    }

    public function test_end_date_cannot_be_before_start_date(): void
    {
        [$seller] = $this->seller();

        $this->actingAs($seller)
            ->post(route('seller.banners.store'), [
                'image' => UploadedFile::fake()->image('b.jpg', 1920, 960),
                'alt' => 'X',
                'starts_on' => '2026-12-10',
                'ends_on' => '2026-12-01',
            ])
            ->assertSessionHasErrors('ends_on');
    }

    public function test_package_limit_blocks_adding_more_banners(): void
    {
        [$seller, $shop] = $this->seller('stall');
        $this->banner($shop);

        $this->actingAs($seller)->get(route('seller.banners.create'))->assertRedirect(route('seller.banners.index'));

        $this->actingAs($seller)
            ->post(route('seller.banners.store'), [
                'image' => UploadedFile::fake()->image('b.jpg', 1920, 960),
                'alt' => 'Drugi',
            ])
            ->assertRedirect(route('seller.banners.index'))
            ->assertSessionHas('error');

        $this->assertSame(1, $shop->banners()->count());
    }

    public function test_replacing_the_image_deletes_the_old_files(): void
    {
        [$seller, $shop] = $this->seller();
        $banner = $this->banner($shop);
        $old = $banner->paths();

        $this->actingAs($seller)
            ->post(route('seller.banners.update', $banner), [
                'image' => UploadedFile::fake()->image('nowy.jpg', 1920, 960),
                'alt' => 'Po zmianie',
                'is_active' => '1',
            ])
            ->assertRedirect(route('seller.banners.index'));

        $banner->refresh();
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($banner->paths());
        $this->assertSame('Po zmianie', $banner->alt);
    }

    public function test_editing_without_a_new_image_keeps_the_files(): void
    {
        [$seller, $shop] = $this->seller();
        $banner = $this->banner($shop);

        $this->actingAs($seller)
            ->post(route('seller.banners.update', $banner), ['alt' => 'Nowy opis', 'is_active' => '0'])
            ->assertRedirect(route('seller.banners.index'));

        Storage::disk('public')->assertExists($banner->paths());
        $this->assertFalse($banner->fresh()->is_active);
    }

    public function test_deleting_a_banner_deletes_its_files(): void
    {
        [$seller, $shop] = $this->seller();
        $banner = $this->banner($shop);
        $paths = $banner->paths();

        $this->actingAs($seller)->post(route('seller.banners.destroy', $banner))->assertRedirect(route('seller.banners.index'));

        $this->assertSame(0, $shop->banners()->count());
        Storage::disk('public')->assertMissing($paths);
    }

    public function test_banners_of_another_shop_are_out_of_reach(): void
    {
        [$seller] = $this->seller();
        [, $other] = $this->seller();
        $foreign = $this->banner($other);

        $this->actingAs($seller)->get(route('seller.banners.edit', $foreign))->assertNotFound();
        $this->actingAs($seller)->post(route('seller.banners.destroy', $foreign))->assertNotFound();
        $this->assertTrue($foreign->exists());
    }

    public function test_reorder_moves_only_own_banners(): void
    {
        [$seller, $shop] = $this->seller();
        $a = $this->banner($shop, ['position' => 0]);
        $b = $this->banner($shop, ['position' => 1]);
        [, $other] = $this->seller();
        $foreign = $this->banner($other, ['position' => 5]);

        $this->actingAs($seller)
            ->postJson(route('seller.banners.reorder'), ['order' => [$b->id, $foreign->id, $a->id]])
            ->assertOk();

        $this->assertSame([$b->id, $a->id], $shop->banners()->pluck('id')->all());
        $this->assertSame(5, $foreign->fresh()->position);
    }

    public function test_interval_is_saved_within_bounds(): void
    {
        [$seller, $shop] = $this->seller();

        $this->actingAs($seller)->post(route('seller.banners.interval'), ['banner_interval' => 8])->assertRedirect();
        $this->assertSame(8, $shop->fresh()->bannerInterval());

        $this->actingAs($seller)->post(route('seller.banners.interval'), ['banner_interval' => 30])->assertSessionHasErrors('banner_interval');
        $this->assertSame(8, $shop->fresh()->bannerInterval());
    }

    // --- Storefront -------------------------------------------------------------

    public function test_single_banner_is_a_plain_image_without_slider(): void
    {
        [, $shop] = $this->seller();
        $this->banner($shop, ['alt' => 'Kawa Pallada']);

        $this->get($this->host($shop).'/')
            ->assertOk()
            ->assertSee('alt="Kawa Pallada"', false)
            ->assertSee('fetchpriority="high"', false)
            ->assertSee('sizes="(min-width: 1152px) 1104px, calc(100vw - 3rem)"', false)
            ->assertDontSee('data-banner-slider', false);
    }

    public function test_several_banners_make_a_slider_with_controls(): void
    {
        [, $shop] = $this->seller();
        $shop->update(['banner_interval' => 7]);
        $this->banner($shop, ['alt' => 'Pierwszy']);
        $this->banner($shop, ['alt' => 'Drugi']);

        $this->get($this->host($shop).'/')
            ->assertOk()
            ->assertSee('data-banner-slider data-interval="7"', false)
            ->assertSee('aria-label="Pokaż baner 2"', false)
            ->assertSee('aria-label="Zatrzymaj pokaz"', false)
            ->assertSeeInOrder(['alt="Pierwszy"', 'alt="Drugi"'], false)
            ->assertSee('loading="lazy"', false);
    }

    public function test_hidden_and_out_of_date_banners_are_not_shown(): void
    {
        [, $shop] = $this->seller();
        $this->travelTo(now()->setDate(2026, 11, 15));
        $this->banner($shop, ['alt' => 'Widoczny']);
        $this->banner($shop, ['alt' => 'Ukryty', 'is_active' => false]);
        $this->banner($shop, ['alt' => 'Przyszly', 'starts_on' => '2026-11-20']);
        $this->banner($shop, ['alt' => 'Zakonczony', 'ends_on' => '2026-11-14']);
        $this->banner($shop, ['alt' => 'Do dzis', 'ends_on' => '2026-11-15']);

        $this->get($this->host($shop).'/')
            ->assertOk()
            ->assertSee('alt="Widoczny"', false)
            ->assertSee('alt="Do dzis"', false)
            ->assertDontSee('alt="Ukryty"', false)
            ->assertDontSee('alt="Przyszly"', false)
            ->assertDontSee('alt="Zakonczony"', false);
    }

    /** Po zejściu na niższy pakiet gasną banery z KOŃCA listy, nic się nie kasuje. */
    public function test_banners_over_the_package_limit_are_not_shown_but_kept(): void
    {
        [, $shop] = $this->seller();
        $this->banner($shop, ['alt' => 'Pierwszy', 'position' => 0]);
        $this->banner($shop, ['alt' => 'Drugi', 'position' => 1]);
        $shop->assignPackage('stall');

        $this->get($this->host($shop->fresh()).'/')
            ->assertOk()
            ->assertSee('alt="Pierwszy"', false)
            ->assertDontSee('alt="Drugi"', false)
            ->assertDontSee('data-banner-slider', false);

        $this->assertSame(2, $shop->banners()->count());
    }

    public function test_external_links_open_in_a_new_window_and_shop_links_do_not(): void
    {
        [, $shop] = $this->seller();
        $inside = $this->banner($shop, ['link_url' => '/produkty']);
        $ownHost = $this->banner($shop, ['link_url' => 'https://'.$shop->host().'/produkty']);
        $outside = $this->banner($shop, ['link_url' => 'https://example.com/promocja']);

        $this->assertFalse($inside->linksOutside($shop));
        $this->assertFalse($ownHost->linksOutside($shop));
        $this->assertTrue($outside->linksOutside($shop));

        $this->get($this->host($shop).'/')
            ->assertSee('<a href="https://example.com/promocja" target="_blank" rel="noopener">', false)
            ->assertSee('<a href="/produkty">', false);
    }

    public function test_shop_without_banners_renders_no_banner_section(): void
    {
        [, $shop] = $this->seller();

        $this->get($this->host($shop).'/')->assertOk()->assertDontSee('class="sbn"', false);
    }
}
