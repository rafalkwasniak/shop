<?php

namespace Tests\Feature;

use App\Livewire\Administrator\ShopManager;
use App\Models\PageImage;
use App\Models\Shop;
use App\Models\User;
use App\Services\HtmlSanitizer;
use App\Services\PageImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Zdjęcia w treści stron „Informacje": przycisk w edytorze, limit z pakietu
 * na CAŁY sklep (Kram 5 / Stragan 20 / Pawilon 40), sprzątanie nieużywanych.
 *
 * Najważniejsze tu jest bezpieczeństwo filtra: `<img>` przechodzi WYŁĄCZNIE
 * w treści stron i wyłącznie z plików tego sklepu.
 */
class PageImageTest extends TestCase
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

    private function image(Shop $shop): PageImage
    {
        return app(PageImageService::class)->store(UploadedFile::fake()->image('z.jpg', 800, 600), $shop);
    }

    /** Treść tak, jak ją wysyła Trix: ramka załącznika z podpisem. */
    private function trix(PageImage $image, string $caption = ''): string
    {
        return '<div>Krok pierwszy</div><figure data-trix-attachment="{&quot;url&quot;:&quot;x&quot;}" class="attachment attachment--preview">'
            .'<img src="'.$image->src().'" width="'.$image->width.'" height="'.$image->height.'">'
            .'<figcaption class="attachment__caption">'.$caption.'</figcaption></figure>';
    }

    // --- Pakiety ------------------------------------------------------------------

    public function test_package_limits_are_5_20_40(): void
    {
        $this->assertSame(5, config('shop.packages.stall.entitlements.max_page_images'));
        $this->assertSame(20, config('shop.packages.booth.entitlements.max_page_images'));
        $this->assertSame(40, config('shop.packages.pavilion.entitlements.max_page_images'));
        $this->assertGreaterThanOrEqual(100000, config('shop.packages.dedicated.entitlements.max_page_images'));
        $this->assertArrayHasKey('max_page_images', (new ShopManager)->numericEntitlements());
    }

    // --- Wgrywanie ---------------------------------------------------------------

    public function test_upload_returns_a_relative_address_of_a_resized_webp(): void
    {
        [$seller, $shop] = $this->seller();

        $response = $this->actingAs($seller)
            ->postJson(route('seller.pages.images.store'), ['image' => UploadedFile::fake()->image('duze.jpg', 4000, 2000)])
            ->assertOk()
            ->assertJson(['width' => 1600, 'height' => 800, 'used' => 1, 'limit' => 40]);

        $image = PageImage::query()->sole();
        $this->assertSame($image->src(), $response->json('src'));
        $this->assertStringStartsWith('/', $response->json('src'));
        $this->assertStringEndsWith('.webp', $response->json('src'));
        Storage::disk('public')->assertExists($image->path());
    }

    public function test_limit_counts_the_whole_shop_and_blocks_new_uploads(): void
    {
        [$seller, $shop] = $this->seller('stall');
        foreach (range(1, 5) as $i) {
            $this->image($shop);
        }

        $this->actingAs($seller)
            ->postJson(route('seller.pages.images.store'), ['image' => UploadedFile::fake()->image('z.jpg', 800, 600)])
            ->assertStatus(422)
            ->assertJson(['message' => 'Twój pakiet pozwala na 5 zdjęć w stronach. Usuń któreś, żeby dodać nowe.']);

        $this->assertSame(5, PageImage::query()->count());
    }

    /** Po zejściu na niższy pakiet zdjęcia zostają; komunikat mówi, ile usunąć. */
    public function test_after_downgrade_existing_images_stay_and_the_message_says_how_many(): void
    {
        [$seller, $shop] = $this->seller();
        foreach (range(1, 7) as $i) {
            $this->image($shop);
        }
        $shop->assignPackage('stall');

        $this->actingAs($seller)
            ->postJson(route('seller.pages.images.store'), ['image' => UploadedFile::fake()->image('z.jpg', 800, 600)])
            ->assertStatus(422)
            ->assertJson(['message' => 'Masz 7 zdjęć w stronach, a Twój pakiet pozwala na 5. Usuń zdjęcia z którejś strony, żeby dodać nowe.']);

        $this->assertSame(7, PageImage::query()->count());
    }

    // --- Filtr treści: bezpieczeństwo -------------------------------------------

    public function test_page_keeps_own_images_from_trix_markup(): void
    {
        [, $shop] = $this->seller();
        $image = $this->image($shop);

        $html = app(HtmlSanitizer::class)->cleanPage($this->trix($image), $shop->id);

        $this->assertStringContainsString('<img src="'.$image->src().'" alt="" width="'.$image->width.'" height="'.$image->height.'" loading="lazy">', $html);
        $this->assertStringNotContainsString('figure', $html);
        $this->assertStringNotContainsString('data-trix', $html);
    }

    public function test_foreign_images_never_pass(): void
    {
        [, $shop] = $this->seller();
        [, $other] = $this->seller();
        $own = $this->image($shop);
        $foreign = $this->image($other);

        $sanitizer = app(HtmlSanitizer::class);

        foreach ([
            '<img src="https://zly.example/sledzik.gif">',
            '<img src="'.$foreign->src().'">',
            '<img src="/storage/shops/'.$shop->id.'/pages/../../../.env">',
            '<img src="javascript:alert(1)">',
            '<img src="'.$own->src().'" onerror="alert(1)">',
        ] as $attack) {
            $clean = $sanitizer->cleanPage($attack, $shop->id);
            $this->assertStringNotContainsString('zly.example', $clean);
            $this->assertStringNotContainsString($foreign->uuid, $clean);
            $this->assertStringNotContainsString('.env', $clean);
            $this->assertStringNotContainsString('javascript', $clean);
            $this->assertStringNotContainsString('onerror', $clean);
        }
    }

    /** Pełny adres — nawet z obcym hostem — zostaje sprowadzony do naszej ścieżki. */
    public function test_absolute_address_is_reduced_to_a_relative_path(): void
    {
        [, $shop] = $this->seller();
        $image = $this->image($shop);

        $clean = app(HtmlSanitizer::class)->cleanPage('<img src="https://obcy.example'.$image->src().'">', $shop->id);

        $this->assertStringContainsString('src="'.$image->src().'"', $clean);
        $this->assertStringNotContainsString('obcy.example', $clean);
    }

    /** Opisy produktów, opis sklepu i newslettery dalej nie mają zdjęć. */
    public function test_other_content_still_drops_images(): void
    {
        [, $shop] = $this->seller();
        $image = $this->image($shop);

        $this->assertStringNotContainsString('<img', app(HtmlSanitizer::class)->clean($this->trix($image)));
    }

    public function test_non_empty_caption_stays_as_text(): void
    {
        [, $shop] = $this->seller();
        $image = $this->image($shop);

        $clean = app(HtmlSanitizer::class)->cleanPage($this->trix($image, 'Krok 2: wpisz kod'), $shop->id);

        $this->assertStringContainsString('<div>Krok 2: wpisz kod</div>', $clean);
        $this->assertStringNotContainsString('figcaption', $clean);
    }

    // --- Zapis strony i sprzątanie ------------------------------------------------

    public function test_saved_page_shows_the_image_on_the_storefront(): void
    {
        [$seller, $shop] = $this->seller();
        $image = $this->image($shop);
        $page = $shop->pages()->create(['title' => 'Jak zdobyć rabat', 'slug' => 'jak-zdobyc-rabat', 'content' => '<div>x</div>', 'published' => true, 'position' => 5]);

        $this->actingAs($seller)
            ->post(route('seller.pages.update', $page), ['title' => 'Jak zdobyć rabat', 'content' => $this->trix($image), 'published' => '1'])
            ->assertRedirect();

        $this->get($this->host($shop).$page->storefrontPath())
            ->assertOk()
            ->assertSee('src="'.$image->src().'"', false)
            ->assertSee('loading="lazy"', false);
    }

    public function test_image_cut_from_the_text_frees_the_slot_at_once(): void
    {
        [$seller, $shop] = $this->seller();
        $image = $this->image($shop);
        $page = $shop->pages()->create(['title' => 'Kroki', 'slug' => 'kroki', 'content' => $this->trix($image), 'published' => true, 'position' => 5]);

        $this->actingAs($seller)
            ->post(route('seller.pages.update', $page), ['title' => 'Kroki', 'content' => '<div>Bez zdjęcia</div>', 'published' => '1'])
            ->assertRedirect();

        $this->assertSame(0, PageImage::query()->count());
        Storage::disk('public')->assertMissing($image->path());
    }

    /** Zdjęcie użyte na dwóch stronach nie znika, gdy wypadnie tylko z jednej. */
    public function test_image_still_used_elsewhere_is_kept(): void
    {
        [$seller, $shop] = $this->seller();
        $image = $this->image($shop);
        $a = $shop->pages()->create(['title' => 'A', 'slug' => 'a', 'content' => $this->trix($image), 'published' => true, 'position' => 5]);
        $shop->pages()->create(['title' => 'B', 'slug' => 'b', 'content' => '<img src="'.$image->src().'">', 'published' => true, 'position' => 6]);

        $this->actingAs($seller)->post(route('seller.pages.update', $a), ['title' => 'A', 'content' => '<div>już bez</div>', 'published' => '1']);

        $this->assertTrue($image->exists());
        Storage::disk('public')->assertExists($image->path());
    }

    public function test_deleting_a_page_deletes_its_images(): void
    {
        [$seller, $shop] = $this->seller();
        $image = $this->image($shop);
        $page = $shop->pages()->create(['title' => 'Do usunięcia', 'slug' => 'do-usuniecia', 'content' => $this->trix($image), 'published' => true, 'position' => 5]);

        $this->actingAs($seller)->post(route('seller.pages.destroy', $page))->assertRedirect();

        $this->assertSame(0, PageImage::query()->count());
        Storage::disk('public')->assertMissing($image->path());
    }

    public function test_prune_removes_only_old_orphans(): void
    {
        [, $shop] = $this->seller();
        $used = $this->image($shop);
        $orphan = $this->image($shop);
        $shop->pages()->create(['title' => 'Z', 'slug' => 'z', 'content' => $this->trix($used), 'published' => true, 'position' => 5]);

        $this->travel(25)->hours();
        $fresh = $this->image($shop);

        $this->artisan('pages:prune-images')->assertSuccessful();

        $this->assertTrue($used->exists());
        $this->assertTrue($fresh->exists());
        $this->assertNull(PageImage::query()->find($orphan->id));
        Storage::disk('public')->assertMissing($orphan->path());
    }

    // --- Panel ------------------------------------------------------------------

    public function test_page_editor_has_the_button_and_the_counter_but_product_editor_does_not(): void
    {
        [$seller, $shop] = $this->seller('booth');
        $this->image($shop);

        $this->actingAs($seller)
            ->get(route('seller.pages.create'))
            ->assertOk()
            ->assertSee('data-image-button', false)
            ->assertSeeInOrder(['Zdjęcia w stronach:', '1', 'z 20'], false);

        $this->actingAs($seller)
            ->get(route('seller.products.create'))
            ->assertOk()
            ->assertDontSee('data-image-button', false);
    }
}
