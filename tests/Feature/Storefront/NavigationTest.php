<?php

namespace Tests\Feature\Storefront;

use App\Models\Page;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Globalny nagłówek i stopka storefrontu: nawigacja (Produkty + rozwijane
 * „Informacje" z jednego źródła Shop::informationMenu), brand, oraz stopka
 * (te same pozycje + NASZA Polityka prywatności + kontakt sklepu).
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    private function host(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain');
    }

    public function test_header_shows_products_and_information_nav(): void
    {
        $shop = Shop::factory()->active()->create();

        $this->get($this->host($shop))
            ->assertOk()
            ->assertSee('Produkty')
            ->assertSee('Informacje');
    }

    public function test_information_menu_lists_published_pages(): void
    {
        $shop = Shop::factory()->active()->create();
        $page = Page::factory()->for($shop)->create(['title' => 'Dostawa i zwroty']);

        $this->get($this->host($shop))
            ->assertOk()
            ->assertSee('Dostawa i zwroty')            // pozycja menu
            ->assertSee($page->storefrontPath(), false) // link do strony
            ->assertSee('Regulamin');                   // strona systemowa też jest w menu
    }

    public function test_information_menu_includes_about_when_description_is_long(): void
    {
        $shop = Shop::factory()->active()->create([
            'description' => '<p>'.str_repeat('a', (int) config('pages.about.menu_threshold') + 50).'</p>',
        ]);

        $this->get($this->host($shop))
            ->assertOk()
            ->assertSee('O sklepie')
            ->assertSee($shop->aboutPath(), false);
    }

    public function test_information_menu_excludes_about_when_description_is_short(): void
    {
        $shop = Shop::factory()->active()->create(['description' => '<p>Krótko.</p>']);

        $this->get($this->host($shop))
            ->assertOk()
            ->assertDontSee($shop->aboutPath(), false);
    }

    public function test_footer_links_to_local_themed_privacy_policy(): void
    {
        $shop = Shop::factory()->active()->create();

        $this->get($this->host($shop))
            ->assertOk()
            ->assertSee('Polityka prywatności')
            // Link lokalny (w motywie sklepu, rodzina /informacje), NIE na centralę.
            ->assertSee('href="/informacje/polityka-prywatnosci"', false);
    }

    public function test_privacy_is_last_information_menu_item(): void
    {
        $shop = Shop::factory()->active()->create();
        $menu = $shop->informationMenu();

        $this->assertSame($shop->privacyPath(), end($menu)['url']);
        $this->assertSame('Polityka prywatności', end($menu)['label']);
    }

    public function test_old_privacy_url_redirects_to_information_family(): void
    {
        $shop = Shop::factory()->active()->create();

        $this->get($this->host($shop).'/polityka-prywatnosci')
            ->assertRedirect($shop->privacyPath());
    }

    public function test_privacy_page_renders_our_content_in_shop_theme(): void
    {
        $shop = Shop::factory()->active()->create();
        // Wyższa wersja niż ewentualnie zaseedowana → to ona jest „bieżąca".
        \App\Models\LegalDocument::create([
            'type' => \App\Enums\LegalDocumentType::Privacy->value,
            'version' => 999,
            'content' => '<p>Administratorem danych jest Kramio.</p>',
            'published_at' => now(),
        ]);

        $this->get($this->host($shop).$shop->privacyPath())
            ->assertOk()
            ->assertSee('Polityka prywatności')
            ->assertSee('Administratorem danych jest Kramio')
            // Renderowana w layoucie sklepu (breadcrumbs z nazwą sklepu).
            ->assertSee($shop->name);
    }

    public function test_footer_shows_company_data_when_present(): void
    {
        $shop = Shop::factory()->active()->create([
            'company_name' => 'Rowery Kowalski sp. z o.o.',
            'street' => 'Kwiatowa',
            'building_number' => '12',
            'apartment_number' => '3',
            'postal_code' => '00-001',
            'city' => 'Warszawa',
        ]);

        $this->get($this->host($shop))
            ->assertOk()
            ->assertSee('Rowery Kowalski sp. z o.o.')
            ->assertSee('Kwiatowa 12/3')
            ->assertSee('00-001 Warszawa');
    }

    public function test_footer_shows_contact_when_present(): void
    {
        $shop = Shop::factory()->active()->create([
            'contact_email' => 'kontakt@sklep.test',
            'contact_phone' => '600700800',
        ]);

        $this->get($this->host($shop))
            ->assertOk()
            ->assertSee('Kontakt')
            ->assertSee('kontakt@sklep.test')
            ->assertSee('mailto:kontakt@sklep.test', false)
            ->assertSee('href="tel:+48600700800"', false);
    }

    public function test_brand_shows_shop_name_without_logo(): void
    {
        $shop = Shop::factory()->active()->create(['name' => 'Srebrny Kram', 'logo_path' => null]);

        $this->get($this->host($shop))
            ->assertOk()
            ->assertSee('Srebrny Kram');
    }
}
