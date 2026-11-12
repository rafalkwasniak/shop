<?php

namespace Tests\Feature\Storefront;

use App\Enums\UserRole;
use App\Livewire\AddToCart;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Strona produktu na storefroncie: URL {id}-{slug}, 301 na kanoniczny slug,
 * scope do sklepu z subdomeny i bramka widoczności (szkic/ukryty → 404 dla
 * publiki, podgląd dla właściciela/admina).
 */
class ProductPageTest extends TestCase
{
    use RefreshDatabase;

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    private function product(Shop $shop, array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'shop_id' => $shop->id,
            'is_active' => true,
        ], $attrs));
    }

    public function test_product_page_renders(): void
    {
        $shop = Shop::factory()->active()->sellable()->create();
        $product = $this->product($shop, ['name' => 'Kubek Ceramiczny', 'price_gross' => 29.90]);

        $this->get($this->host($shop).$product->storefrontPath())
            ->assertOk()
            ->assertSee('Kubek Ceramiczny')
            ->assertSee('29,90 zł')
            ->assertSeeLivewire(AddToCart::class);
    }

    public function test_product_page_shows_delivery_and_payment_summary(): void
    {
        $shop = Shop::factory()->active()->withCourierShipping()->create([
            'street' => 'Kwiatowa', 'building_number' => '5', 'postal_code' => '00-001',
            'city' => 'Warszawa', 'province' => 'mazowieckie',
            'pickup_enabled' => true, 'pay_on_pickup_enabled' => true,
            'courier_enabled' => true, 'courier_cost' => 15.00, 'courier_free_from' => 200.00,
            'bank_account_number' => '12345678901234567890123456', 'bank_transfer_enabled' => true,
        ]);
        $product = $this->product($shop, ['price_gross' => 499.00]);

        $this->get($this->host($shop).$product->storefrontPath())
            ->assertOk()
            ->assertSee('Dostawa i płatność')
            ->assertSee('Odbiór osobisty')
            ->assertSee('Kurier')
            ->assertSee('15,00 zł')
            ->assertSee('gratis od 200,00 zł')
            ->assertSee('Przelew na konto')
            ->assertSee('Płatność przy odbiorze');
    }

    public function test_delivery_summary_hidden_when_shop_offers_no_methods(): void
    {
        // Sklep bez żadnej metody dostawy/płatności → box nie ma czego pokazać.
        $shop = Shop::factory()->active()->create([
            'pickup_enabled' => false, 'pay_on_pickup_enabled' => false,
            'courier_enabled' => false, 'bank_transfer_enabled' => false,
        ]);
        $product = $this->product($shop);

        $this->get($this->host($shop).$product->storefrontPath())
            ->assertOk()
            ->assertDontSee('Dostawa i płatność');
    }

    public function test_wrong_slug_redirects_to_canonical(): void
    {
        $shop = Shop::factory()->active()->create();
        $product = $this->product($shop, ['slug' => 'kubek-ceramiczny']);

        $this->get($this->host($shop).'/produkt/'.$product->id.'-zupelnie-zly-slug')
            ->assertStatus(301)
            ->assertRedirect($product->storefrontPath());
    }

    public function test_product_of_another_shop_returns_404(): void
    {
        $shopA = Shop::factory()->active()->create();
        $shopB = Shop::factory()->active()->create();
        $product = $this->product($shopA);

        // Adres produktu sklepu A wywołany na subdomenie sklepu B.
        $this->get($this->host($shopB).$product->storefrontPath())
            ->assertNotFound();
    }

    public function test_inactive_product_is_hidden_from_guests(): void
    {
        $shop = Shop::factory()->active()->create();
        $product = $this->product($shop, ['is_active' => false]);

        $this->get($this->host($shop).$product->storefrontPath())
            ->assertNotFound();
    }

    public function test_owner_can_preview_inactive_product(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Seller]);
        $shop = Shop::factory()->active()->create(['owner_id' => $owner->id]);
        $product = $this->product($shop, ['is_active' => false, 'name' => 'Ukryty Skarb']);

        $this->actingAs($owner)
            ->get($this->host($shop).$product->storefrontPath())
            ->assertOk()
            ->assertSee('Ukryty Skarb');
    }

    public function test_product_page_shows_clickable_tags(): void
    {
        $shop = Shop::factory()->active()->create();
        $product = $this->product($shop, ['name' => 'Rower Szosowy']);
        $tag = $shop->tags()->create(['name' => 'madone', 'slug' => 'madone']);
        $product->tags()->attach($tag->id);

        $this->get($this->host($shop).$product->storefrontPath())
            ->assertOk()
            ->assertSee('madone')
            ->assertSee('/produkty?tagi=madone', false);
    }

    public function test_back_link_returns_to_filtered_listing(): void
    {
        $shop = Shop::factory()->active()->create();
        $product = $this->product($shop);
        $back = '/produkty?tagi=srebro&sortowanie=nazwa';

        $this->get($this->host($shop).$product->storefrontPath().'?powrot='.urlencode($back))
            ->assertOk()
            // Krótki „← Powrót" doklejony przy okruszkach; href = przefiltrowany wykaz.
            ->assertSee('Powrót')
            ->assertSee('href="/produkty?tagi=srebro', false);
    }

    public function test_back_link_rejects_external_url(): void
    {
        $shop = Shop::factory()->active()->create();
        $product = $this->product($shop);

        // Open-redirect guard: obcy host jest odrzucany, wracamy na główną sklepu.
        $this->get($this->host($shop).$product->storefrontPath().'?powrot='.urlencode('//evil.example'))
            ->assertOk()
            ->assertDontSee('evil.example')
            ->assertSee($shop->name);
    }

    public function test_back_link_defaults_to_shop_home_without_powrot(): void
    {
        $shop = Shop::factory()->active()->create();
        $product = $this->product($shop);

        $this->get($this->host($shop).$product->storefrontPath())
            ->assertOk()
            ->assertSee($shop->name)
            // Bez `powrot` powrót prowadzi na główną sklepu (safeBack → "/").
            ->assertSee('href="/" class="shrink-0', false)
            ->assertSee('← Powrót', false);
    }

    public function test_canonical_redirect_preserves_return_url(): void
    {
        $shop = Shop::factory()->active()->create();
        $product = $this->product($shop, ['slug' => 'kubek']);
        $back = '/produkty?tagi=srebro';

        $this->get($this->host($shop).'/produkt/'.$product->id.'-zly-slug?powrot='.urlencode($back))
            ->assertStatus(301)
            ->assertRedirect($product->storefrontPath().'?powrot='.urlencode($back));
    }

    public function test_product_page_renders_breadcrumbs(): void
    {
        $shop = Shop::factory()->active()->create(['name' => 'Srebrny Kram']);
        $product = $this->product($shop, ['name' => 'Bransoletka']);

        $this->get($this->host($shop).$product->storefrontPath())
            ->assertOk()
            // Widoczna ścieżka: nazwa sklepu → Produkty → produkt (bieżący).
            ->assertSee('aria-label="Ścieżka nawigacji"', false)
            ->assertSee('Srebrny Kram')
            ->assertSee('aria-current="page"', false)
            // SEO: schema.org BreadcrumbList z korzeniem = nazwa sklepu.
            ->assertSee('BreadcrumbList', false)
            ->assertSee('"name":"Srebrny Kram"', false)
            ->assertSee('"name":"Bransoletka"', false);
    }

    public function test_gallery_stacks_all_photos_so_thumbnails_stay_put(): void
    {
        $shop = Shop::factory()->active()->sellable()->create();
        $product = $this->product($shop);

        foreach (range(1, 3) as $position) {
            $product->images()->create([
                'path' => 'products/'.$product->id.'/zdjecie-'.$position.'.webp',
                'position' => $position,
            ]);
        }

        // Wszystkie zdjęcia są w HTML-u od razu, jedno na drugim: komórka ma
        // wysokość najwyższego, więc miniatury nie skaczą przy przełączaniu.
        // Widać pierwsze; reszta ukryta przez `visibility`, ale zajmuje miejsce.
        $html = $this->get($this->host($shop).$product->storefrontPath())
            ->assertOk()
            ->assertSee('data-gallery-interval="5000"', false)
            ->getContent();

        $this->assertSame(3, substr_count($html, ' data-gallery-slide'));
        $this->assertSame(2, substr_count($html, 'opacity: 0; visibility: hidden;'));
        $this->assertSame(3, substr_count($html, ' data-gallery-thumb'));
    }
}
