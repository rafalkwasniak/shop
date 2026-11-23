<?php

namespace Tests\Feature\Seller;

use App\Enums\IntegrationType;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integracje sklepu: konfiguracja usług (na razie Google Analytics / Tag
 * Manager) na stronie „Integracje" oraz włącznik na „Ustawieniach". Podział:
 * config = Integracje, enabled = Ustawienia; jeden wiersz shop_integrations.
 */
class IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithShop(array $shopAttributes = [], bool $invoicing = false, bool $onlinePayments = false, bool $gaAnalytics = false, bool $shipping = false): array
    {
        $seller = User::factory()->consented()->create();
        $factory = Shop::factory();
        if ($invoicing) {
            $factory = $factory->withInvoicing();
        }
        if ($onlinePayments) {
            $factory = $factory->withOnlinePayments();
        }
        if ($gaAnalytics) {
            $factory = $factory->withGaAnalytics();
        }
        if ($shipping) {
            $factory = $factory->withCourierShipping();
        }
        $shop = $factory->create(array_merge(['owner_id' => $seller->id], $shopAttributes));

        return [$seller, $shop];
    }

    public function test_seller_can_view_integrations_page(): void
    {
        [$seller] = $this->sellerWithShop(gaAnalytics: true);

        $this->actingAs($seller)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            ->assertSee('Google Analytics')
            ->assertSee('Identyfikator śledzenia');
    }

    public function test_seller_can_configure_ga4_id_and_it_is_enabled_by_default(): void
    {
        [$seller, $shop] = $this->sellerWithShop(gaAnalytics: true);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), ['google_analytics_id' => 'G-ABC123XYZ'])
            ->assertRedirect(route('seller.integrations.edit'))
            ->assertSessionHas('success');

        $integration = $shop->integrations()->where('type', IntegrationType::GoogleAnalytics)->first();

        $this->assertNotNull($integration);
        $this->assertTrue($integration->enabled);
        $this->assertSame('G-ABC123XYZ', $integration->config['tracking_id']);
    }

    public function test_gtm_id_is_accepted(): void
    {
        [$seller, $shop] = $this->sellerWithShop(gaAnalytics: true);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), ['google_analytics_id' => 'GTM-ABCD12'])
            ->assertSessionHas('success');

        $this->assertSame('GTM-ABCD12', $shop->fresh()->googleAnalyticsId());
    }

    public function test_id_is_trimmed_and_uppercased(): void
    {
        [$seller, $shop] = $this->sellerWithShop(gaAnalytics: true);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), ['google_analytics_id' => '  g-abc123  ']);

        $this->assertSame('G-ABC123', $shop->fresh()->googleAnalyticsId());
    }

    public function test_invalid_id_is_rejected(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), ['google_analytics_id' => 'UA-12345-1'])
            ->assertSessionHasErrors('google_analytics_id');

        $this->assertNull($shop->fresh()->googleAnalyticsId());
    }

    public function test_clearing_id_removes_integration(): void
    {
        [$seller, $shop] = $this->sellerWithShop(gaAnalytics: true);
        $shop->integrations()->create([
            'type' => IntegrationType::GoogleAnalytics,
            'enabled' => true,
            'config' => ['tracking_id' => 'G-ABC123'],
        ]);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), ['google_analytics_id' => '']);

        $this->assertSame(0, ShopIntegration::count());
    }

    public function test_reconfiguring_keeps_enabled_state(): void
    {
        [$seller, $shop] = $this->sellerWithShop(gaAnalytics: true);
        $shop->integrations()->create([
            'type' => IntegrationType::GoogleAnalytics,
            'enabled' => false,                     // wcześniej wyłączona w Ustawieniach
            'config' => ['tracking_id' => 'G-OLD123'],
        ]);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), ['google_analytics_id' => 'G-NEW456']);

        $integration = $shop->integrations()->first();
        $this->assertSame('G-NEW456', $integration->config['tracking_id']);
        $this->assertFalse($integration->enabled, 'Zmiana ID nie powinna sama włączać usługi.');
    }

    public function test_settings_toggle_enables_and_disables_configured_ga(): void
    {
        [$seller, $shop] = $this->sellerWithShop(['default_vat_rate' => '23']);
        $integration = $shop->integrations()->create([
            'type' => IntegrationType::GoogleAnalytics,
            'enabled' => true,
            'config' => ['tracking_id' => 'G-ABC123'],
        ]);

        // Wyłączenie: checkbox odznaczony = pole nieobecne w POST.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), ['default_vat_rate' => '23'])
            ->assertSessionHas('success');

        $this->assertFalse($integration->fresh()->enabled);

        // Włączenie z powrotem.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'google_analytics_enabled' => '1',
            ]);

        $this->assertTrue($integration->fresh()->enabled);
    }

    public function test_settings_toggle_noop_without_configured_integration(): void
    {
        [$seller, $shop] = $this->sellerWithShop(['default_vat_rate' => '23']);

        // Brak integracji: przesłanie włącznika niczego nie tworzy.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'google_analytics_enabled' => '1',
            ])
            ->assertSessionHas('success');

        $this->assertSame(0, ShopIntegration::count());
    }

    public function test_integrations_page_shows_fakturownia_card(): void
    {
        [$seller] = $this->sellerWithShop(invoicing: true);

        $this->actingAs($seller)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            ->assertSee('Fakturownia')
            ->assertSee('Token API');
    }

    public function test_seller_can_configure_fakturownia_and_url_is_normalized(): void
    {
        [$seller, $shop] = $this->sellerWithShop(invoicing: true);

        // Bez schematu i z końcowym ukośnikiem — normalizujemy do https:// bez „/".
        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'fakturownia_url' => 'twojadomena.fakturownia.pl/',
                'fakturownia_token' => 'SECRET-TOKEN-123',
            ])
            ->assertRedirect(route('seller.integrations.edit'))
            ->assertSessionHas('success');

        $integration = $shop->integrations()->where('type', IntegrationType::Invoicing)->first();

        $this->assertNotNull($integration);
        $this->assertTrue($integration->enabled);
        $this->assertSame('https://twojadomena.fakturownia.pl', $integration->config['account_url']);
        $this->assertSame('SECRET-TOKEN-123', $integration->config['api_token']);
        $this->assertTrue($shop->fresh()->invoicingConfigured());
    }

    public function test_fakturownia_url_without_token_is_rejected_when_not_yet_configured(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'fakturownia_url' => 'https://twojadomena.fakturownia.pl',
                'fakturownia_token' => '',
            ])
            ->assertSessionHasErrors('fakturownia_token');

        $this->assertSame(0, ShopIntegration::where('type', IntegrationType::Invoicing)->count());
    }

    public function test_blank_token_on_resave_keeps_stored_token(): void
    {
        [$seller, $shop] = $this->sellerWithShop(invoicing: true);
        $shop->integrations()->create([
            'type' => IntegrationType::Invoicing,
            'enabled' => true,
            'config' => ['account_url' => 'https://old.fakturownia.pl', 'api_token' => 'KEEP-ME'],
        ]);

        // Zmiana samego adresu; puste pole tokenu = „zostaw dotychczasowy".
        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'fakturownia_url' => 'https://new.fakturownia.pl',
                'fakturownia_token' => '',
            ])
            ->assertSessionHasNoErrors();

        $config = $shop->integrations()->where('type', IntegrationType::Invoicing)->first()->config;
        $this->assertSame('https://new.fakturownia.pl', $config['account_url']);
        $this->assertSame('KEEP-ME', $config['api_token']);
    }

    public function test_clearing_fakturownia_url_removes_integration(): void
    {
        [$seller, $shop] = $this->sellerWithShop(invoicing: true);
        $shop->integrations()->create([
            'type' => IntegrationType::Invoicing,
            'enabled' => true,
            'config' => ['account_url' => 'https://x.fakturownia.pl', 'api_token' => 'T'],
        ]);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), ['fakturownia_url' => '', 'fakturownia_token' => '']);

        $this->assertSame(0, ShopIntegration::where('type', IntegrationType::Invoicing)->count());
    }

    public function test_settings_toggle_enables_and_disables_configured_fakturownia(): void
    {
        [$seller, $shop] = $this->sellerWithShop(['default_vat_rate' => '23']);
        $integration = $shop->integrations()->create([
            'type' => IntegrationType::Invoicing,
            'enabled' => true,
            'config' => ['account_url' => 'https://x.fakturownia.pl', 'api_token' => 'T'],
        ]);

        // Wyłączenie: checkbox odznaczony = pole nieobecne w POST.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), ['default_vat_rate' => '23'])
            ->assertSessionHas('success');

        $this->assertFalse($integration->fresh()->enabled);
        $this->assertFalse($shop->fresh()->invoicingEnabled());

        // Włączenie z powrotem.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'fakturownia_enabled' => '1',
            ]);

        $this->assertTrue($integration->fresh()->enabled);
        $this->assertTrue($shop->fresh()->invoicingEnabled());
    }

    public function test_fakturownia_hidden_and_not_saved_without_entitlement(): void
    {
        // Sklep z pakietu bez uprawnienia do faktur (na wypadek przyszłej decyzji
        // o płatności) — karta nie renderuje się, a zapis jest ignorowany.
        [$seller, $shop] = $this->sellerWithShop(['entitlements' => ['invoices' => false]]);

        $this->actingAs($seller)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            ->assertDontSee('Token API');

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'fakturownia_url' => 'https://x.fakturownia.pl',
                'fakturownia_token' => 'SECRET',
            ]);

        $this->assertSame(0, ShopIntegration::where('type', IntegrationType::Invoicing)->count());
    }

    // --- Paynow (płatności online) — bliźniak Fakturowni, bramkowany `online_payments` ---

    public function test_paynow_card_is_gated_by_entitlement(): void
    {
        // Bez uprawnienia `online_payments` (Kram) — karta Paynow się nie renderuje.
        [$sellerFree] = $this->sellerWithShop();
        $this->actingAs($sellerFree)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            ->assertDontSee('Płatności online (Paynow)');

        // Z uprawnieniem (Stragan+) — karta widoczna z kluczami i adresem webhooka.
        [$sellerPaid] = $this->sellerWithShop(onlinePayments: true);
        $this->actingAs($sellerPaid)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            ->assertSee('Płatności online (Paynow)')
            ->assertSee('Klucz obliczania podpisu')
            ->assertSee('/platnosci/paynow/webhook')
            ->assertSee('Adres powiadomień (webhook)');
    }

    public function test_seller_can_configure_paynow_and_it_is_enabled_by_default(): void
    {
        [$seller, $shop] = $this->sellerWithShop(onlinePayments: true);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'paynow_api_key' => '14d59738-4b18-4c83-86ea-131320c3d337',
                'paynow_signature_key' => '57b7a81d-4f0f-4c2a-bf44-0234fd916f8a',
                'paynow_sandbox' => '1',
            ])
            ->assertRedirect(route('seller.integrations.edit'))
            ->assertSessionHas('success');

        $integration = $shop->integrations()->where('type', IntegrationType::Payments)->first();
        $this->assertNotNull($integration);
        $this->assertTrue($integration->enabled);
        $this->assertSame('14d59738-4b18-4c83-86ea-131320c3d337', $integration->config['api_key']);
        $this->assertSame('57b7a81d-4f0f-4c2a-bf44-0234fd916f8a', $integration->config['signature_key']);
        $this->assertSame('sandbox', $integration->config['environment']);
        $this->assertTrue($shop->fresh()->onlinePaymentsEnabled());
    }

    public function test_auto_invoice_checkbox_lives_in_settings_not_integrations(): void
    {
        // Przenosiny: decyzja o auto-FV widnieje w Ustawieniach (pod Paynow), a nie
        // w Integracjach (gdzie są tylko dane połączenia z zewnętrznymi narzędziami).
        [$seller, $shop] = $this->sellerWithShop(onlinePayments: true, invoicing: true);
        $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => true,
            'config' => ['api_key' => 'API', 'signature_key' => 'SIGN', 'environment' => 'sandbox'],
        ]);

        $this->actingAs($seller)
            ->get(route('seller.settings.edit'))
            ->assertOk()
            ->assertSee('Wystaw fakturę VAT automatycznie po opłaceniu');

        $this->actingAs($seller)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            ->assertDontSee('Wystaw fakturę VAT automatycznie po opłaceniu');
    }

    public function test_paynow_auto_invoice_flag_is_saved_from_settings(): void
    {
        // Auto-FV to decyzja systemowa (nie dane połączenia) — mieszka w Ustawieniach,
        // wcięta pod włącznikiem Paynow. Zapisuje się do configu integracji płatności.
        [$seller, $shop] = $this->sellerWithShop(onlinePayments: true, invoicing: true);
        $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => true,
            'config' => ['api_key' => 'API', 'signature_key' => 'SIGN', 'environment' => 'sandbox'],
        ]);

        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'paynow_enabled' => '1',
                'paynow_auto_invoice' => '1',
            ])
            ->assertRedirect(route('seller.settings.edit'))
            ->assertSessionHas('success');

        $config = $shop->integrations()->where('type', IntegrationType::Payments)->first()->config;
        $this->assertTrue($config['auto_invoice']);
        $this->assertTrue($shop->fresh()->autoInvoiceAfterPayment());
        // Klucze nietknięte — merge, nie nadpisanie.
        $this->assertSame('API', $config['api_key']);
        $this->assertSame('SIGN', $config['signature_key']);
    }

    public function test_saving_paynow_keys_preserves_existing_auto_invoice_flag(): void
    {
        // Flaga auto-FV żyje w Ustawieniach; ponowny zapis kluczy w Integracjach nie
        // może jej skasować (config przepisujemy 1:1 z zachowaniem `auto_invoice`).
        [$seller, $shop] = $this->sellerWithShop(onlinePayments: true);
        $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => true,
            'config' => ['api_key' => 'OLD', 'signature_key' => 'SIGN', 'environment' => 'sandbox', 'auto_invoice' => true],
        ]);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'paynow_api_key' => '14d59738-4b18-4c83-86ea-131320c3d337',
                'paynow_signature_key' => '57b7a81d-4f0f-4c2a-bf44-0234fd916f8a',
                'paynow_sandbox' => '1',
            ])
            ->assertSessionHas('success');

        $this->assertTrue($shop->fresh()->autoInvoiceAfterPayment());
    }

    public function test_paynow_api_key_without_signature_is_rejected_when_not_yet_configured(): void
    {
        [$seller] = $this->sellerWithShop(onlinePayments: true);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'paynow_api_key' => '14d59738-4b18-4c83-86ea-131320c3d337',
                'paynow_sandbox' => '1',
            ])
            ->assertSessionHasErrors('paynow_signature_key');

        $this->assertSame(0, ShopIntegration::where('type', IntegrationType::Payments)->count());
    }

    public function test_blank_signature_on_resave_keeps_stored_signature(): void
    {
        [$seller, $shop] = $this->sellerWithShop(onlinePayments: true);
        $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => true,
            'config' => ['api_key' => 'OLD-KEY', 'signature_key' => 'KEEP-ME', 'environment' => 'sandbox'],
        ]);

        // Klucz API zmieniony, pole podpisu puste — sekret zostaje bez zmian.
        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'paynow_api_key' => 'NEW-KEY',
                'paynow_signature_key' => '',
                'paynow_sandbox' => '0',
            ]);

        $config = $shop->integrations()->where('type', IntegrationType::Payments)->first()->config;
        $this->assertSame('NEW-KEY', $config['api_key']);
        $this->assertSame('KEEP-ME', $config['signature_key']);
        $this->assertSame('production', $config['environment']);
    }

    public function test_clearing_paynow_api_key_removes_integration(): void
    {
        [$seller, $shop] = $this->sellerWithShop(onlinePayments: true);
        $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => true,
            'config' => ['api_key' => 'K', 'signature_key' => 'S', 'environment' => 'sandbox'],
        ]);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'paynow_api_key' => '',
                'paynow_signature_key' => '',
                'paynow_sandbox' => '1',
            ]);

        $this->assertSame(0, ShopIntegration::where('type', IntegrationType::Payments)->count());
    }

    public function test_settings_toggle_enables_and_disables_configured_paynow(): void
    {
        [$seller, $shop] = $this->sellerWithShop(['default_vat_rate' => '23'], onlinePayments: true);
        $integration = $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => true,
            'config' => ['api_key' => 'K', 'signature_key' => 'S', 'environment' => 'sandbox'],
        ]);

        // Wyłączenie: checkbox odznaczony = pole nieobecne w POST.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), ['default_vat_rate' => '23'])
            ->assertSessionHas('success');

        $this->assertFalse($integration->fresh()->enabled);
        $this->assertFalse($shop->fresh()->onlinePaymentsEnabled());

        // Włączenie z powrotem.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'paynow_enabled' => '1',
            ]);

        $this->assertTrue($integration->fresh()->enabled);
        $this->assertTrue($shop->fresh()->onlinePaymentsEnabled());
    }

    public function test_integrations_page_shows_empty_state_for_free_package(): void
    {
        // Kram — płatne karty zastąpione zachętą, ale Search Console zostaje:
        // jest w KAŻDYM pakiecie, więc jest też co zapisać.
        [$seller] = $this->sellerWithShop(); // domyślny Kram

        $this->actingAs($seller)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            // Nazwa pakietu czytana z configu, nie wpisana w treść — patrz
            // PackageFeatures::cheapestWith().
            ->assertSee('Integracje w pakiecie Stragan')
            // Zachęta prowadzi do zakupu; wcześniej kończyła się na „nie masz".
            ->assertSee(route('seller.package.show'), false)
            ->assertSee('Zobacz pakiet Stragan')
            ->assertDontSee('Klucz obliczania podpisu')   // pole karty Paynow
            ->assertDontSee('Identyfikator śledzenia')     // pole karty GA
            ->assertDontSee('Token API')                   // pole karty Fakturowni
            ->assertSee('Google Search Console')
            ->assertSee('Zapisz integracje');
    }

    public function test_search_console_verification_works_without_a_paid_package(): void
    {
        // Sedno decyzji: to nie analityka, tylko JEDYNA droga, żeby sprzedawca
        // potwierdził Google własność subdomeny (pliku nie wgra, DNS-u nie ruszy,
        // weryfikacja przez GA jest płatna). Bez tego mapa strony, którą dostają
        // wszystkie sklepy, byłaby dla darmowego Kramu nie do zgłoszenia.
        [$seller, $shop] = $this->sellerWithShop(); // domyślny Kram

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'google_site_verification' => 'AbC123_xyz-9876543210QwErTy',
                'paynow_sandbox' => '1',
            ])
            ->assertRedirect(route('seller.integrations.edit'));

        $this->assertSame('AbC123_xyz-9876543210QwErTy', $shop->fresh()->googleSiteVerification());
    }

    public function test_pasted_meta_tag_is_reduced_to_the_code_itself(): void
    {
        // Sprzedawca kopiuje z Google CAŁY znacznik, nie sam kod — odbijanie się
        // od walidacji byłoby karaniem za poprawną odpowiedź na złe pytanie.
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'google_site_verification' => '<meta name="google-site-verification" content="AbC123_xyz-9876543210QwErTy" />',
                'paynow_sandbox' => '1',
            ])
            ->assertRedirect(route('seller.integrations.edit'));

        $this->assertSame('AbC123_xyz-9876543210QwErTy', $shop->fresh()->googleSiteVerification());
    }

    public function test_verification_code_reaches_the_storefront_head(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)->post(route('seller.integrations.update'), [
            'google_site_verification' => 'AbC123_xyz-9876543210QwErTy',
            'paynow_sandbox' => '1',
        ]);

        Product::factory()->for($shop)->create(['is_active' => true]);
        $shop->refreshVisibility();

        $this->get('https://'.$shop->fresh()->host().'/')
            ->assertOk()
            ->assertSee('<meta name="google-site-verification" content="AbC123_xyz-9876543210QwErTy">', false);
    }

    // --- InPost ShipX (nadawanie przesyłek) — bramkowany `courier_shipping` ---

    public function test_shipx_card_is_gated_by_entitlement(): void
    {
        [$sellerFree] = $this->sellerWithShop();
        $this->actingAs($sellerFree)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            ->assertDontSee('Nadawanie przesyłek InPost');

        [$sellerPaid] = $this->sellerWithShop(shipping: true);
        $this->actingAs($sellerPaid)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            ->assertSee('Nadawanie przesyłek InPost')
            ->assertSee('Organization ID')
            ->assertSee('Skąd wziąć token i Organization ID?');
    }

    public function test_seller_can_configure_shipx_and_it_is_enabled_by_default(): void
    {
        [$seller, $shop] = $this->sellerWithShop(shipping: true);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'shipx_token' => 'eyJhbGciOiJSUzI1NiJ9.TESTOWY.TOKEN',
                'shipx_organization_id' => '6700',
                'shipx_sandbox' => '1',
            ])
            ->assertRedirect(route('seller.integrations.edit'))
            ->assertSessionHas('success');

        $integration = $shop->integrations()->where('type', IntegrationType::Shipping)->first();
        $this->assertNotNull($integration);
        $this->assertTrue($integration->enabled);
        $this->assertSame('eyJhbGciOiJSUzI1NiJ9.TESTOWY.TOKEN', $integration->config['token']);
        $this->assertSame('6700', $integration->config['organization_id']);
        $this->assertSame('sandbox', $integration->config['environment']);

        // Domyślne środowisko chroni przed nadaniem prawdziwej paczki „przez pomyłkę".
        $this->assertSame('https://sandbox-api-shipx-pl.easypack24.net', $shop->fresh()->shipxBaseUrl());
    }

    public function test_shipx_production_environment_is_saved_when_sandbox_unchecked(): void
    {
        [$seller, $shop] = $this->sellerWithShop(shipping: true);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'shipx_token' => 'PRODUKCYJNY',
                'shipx_organization_id' => '203242',
                'shipx_sandbox' => '0',
            ])
            ->assertRedirect(route('seller.integrations.edit'));

        $this->assertSame('production', $shop->fresh()->shipxEnvironment());
        $this->assertSame('https://api-shipx-pl.easypack24.net', $shop->fresh()->shipxBaseUrl());
    }

    public function test_saving_shipx_without_token_keeps_the_stored_one(): void
    {
        [$seller, $shop] = $this->sellerWithShop(shipping: true);
        $shop->integrations()->create([
            'type' => IntegrationType::Shipping,
            'enabled' => true,
            'config' => ['token' => 'ZAPISANY', 'organization_id' => '6700', 'environment' => 'sandbox'],
        ]);

        // Puste pole tokenu = „zostaw bez zmian" (pole jest sekretem i nie wraca do formularza).
        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'shipx_token' => '',
                'shipx_organization_id' => '6700',
                'shipx_sandbox' => '1',
            ])
            ->assertRedirect(route('seller.integrations.edit'));

        $this->assertSame('ZAPISANY', $shop->fresh()->shipxToken());
    }

    public function test_clearing_organization_id_disconnects_shipx(): void
    {
        [$seller, $shop] = $this->sellerWithShop(shipping: true);
        $shop->integrations()->create([
            'type' => IntegrationType::Shipping,
            'enabled' => true,
            'config' => ['token' => 'ZAPISANY', 'organization_id' => '6700', 'environment' => 'sandbox'],
        ]);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), ['shipx_organization_id' => ''])
            ->assertRedirect(route('seller.integrations.edit'));

        $this->assertNull($shop->fresh()->integration(IntegrationType::Shipping));
        $this->assertFalse($shop->fresh()->shipxConfigured());
    }

    public function test_shipx_token_without_organization_id_is_rejected(): void
    {
        [$seller] = $this->sellerWithShop(shipping: true);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), ['shipx_token' => 'TOKEN'])
            ->assertSessionHasErrors('shipx_organization_id');
    }

    public function test_shipx_organization_id_must_be_numeric(): void
    {
        [$seller] = $this->sellerWithShop(shipping: true);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'shipx_token' => 'TOKEN',
                'shipx_organization_id' => 'org-6700',
            ])
            ->assertSessionHasErrors('shipx_organization_id');
    }

    public function test_shipx_is_not_saved_without_entitlement(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'shipx_token' => 'TOKEN',
                'shipx_organization_id' => '6700',
            ])
            ->assertRedirect(route('seller.integrations.edit'));

        $this->assertNull($shop->fresh()->integration(IntegrationType::Shipping));
    }

    /**
     * Zapisana konfiguracja Przelewy24 (komplet danych) — punkt wyjścia testów
     * zapisu i Ustawień.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function configurePrzelewy24(Shop $shop, array $overrides = []): ShopIntegration
    {
        return $shop->integrations()->create([
            'type' => IntegrationType::Przelewy24,
            'enabled' => true,
            'config' => array_merge([
                'merchant_id' => 123456,
                'pos_id' => null,
                'crc_key' => 'CRC-KEEP',
                'api_key' => 'API-KEEP',
                'environment' => 'sandbox',
            ], $overrides),
        ]);
    }

    public function test_przelewy24_card_is_gated_by_entitlement_and_shows_server_ip(): void
    {
        config()->set('services.przelewy24.server_ip', '203.0.113.7');

        [$sellerFree] = $this->sellerWithShop();
        $this->actingAs($sellerFree)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            ->assertDontSee('Płatności online (Przelewy24)');

        // P24 wpuszcza API tylko z IP wpisanych w panelu sprzedawcy — bez tego
        // adresu na karcie sprzedawca nie ma jak uruchomić płatności.
        [$sellerPaid] = $this->sellerWithShop(onlinePayments: true);
        $this->actingAs($sellerPaid)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            ->assertSee('Płatności online (Przelewy24)')
            ->assertSee('Klucz CRC')
            ->assertSee('Adres IP do wpisania w Przelewy24')
            ->assertSee('203.0.113.7');
    }

    public function test_przelewy24_card_hides_ip_box_when_server_ip_is_unknown(): void
    {
        config()->set('services.przelewy24.server_ip', null);
        [$seller] = $this->sellerWithShop(onlinePayments: true);

        $this->actingAs($seller)
            ->get(route('seller.integrations.edit'))
            ->assertOk()
            ->assertSee('Płatności online (Przelewy24)')
            ->assertDontSee('Adres IP do wpisania w Przelewy24');
    }

    public function test_seller_can_configure_przelewy24_and_it_is_enabled_by_default(): void
    {
        Http::fake(['*/api/v1/testAccess' => Http::response(['data' => true])]);
        [$seller, $shop] = $this->sellerWithShop(onlinePayments: true);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'przelewy24_merchant_id' => ' 123456 ',
                'przelewy24_pos_id' => '',
                'przelewy24_crc_key' => 'a1b2c3d4e5f6a7b8',
                'przelewy24_api_key' => 'f0e1d2c3b4a5968778695a4b3c2d1e0f',
                'przelewy24_sandbox' => '1',
            ])
            ->assertRedirect(route('seller.integrations.edit'))
            ->assertSessionHas('success');

        $integration = $shop->integrations()->where('type', IntegrationType::Przelewy24)->first();
        $this->assertNotNull($integration);
        $this->assertTrue($integration->enabled);
        $this->assertSame(123456, $integration->config['merchant_id']);
        $this->assertSame('a1b2c3d4e5f6a7b8', $integration->config['crc_key']);
        $this->assertSame('f0e1d2c3b4a5968778695a4b3c2d1e0f', $integration->config['api_key']);
        $this->assertSame('sandbox', $integration->config['environment']);

        $shop = $shop->fresh();
        $this->assertTrue($shop->przelewy24Enabled());
        // Puste POS ID = taki sam jak Merchant ID (domyślna reguła P24).
        $this->assertSame(123456, $shop->przelewy24PosId());
    }

    public function test_przelewy24_distinct_pos_id_is_kept(): void
    {
        Http::fake(['*/api/v1/testAccess' => Http::response(['data' => true])]);
        [$seller, $shop] = $this->sellerWithShop(onlinePayments: true);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'przelewy24_merchant_id' => '123456',
                'przelewy24_pos_id' => '654321',
                'przelewy24_crc_key' => 'CRC',
                'przelewy24_api_key' => 'API',
                'przelewy24_sandbox' => '0',
            ])
            ->assertSessionHas('success');

        $shop = $shop->fresh();
        $this->assertSame(654321, $shop->przelewy24PosId());
        $this->assertSame('production', $shop->przelewy24Environment());
    }

    public function test_przelewy24_merchant_id_without_secrets_is_rejected_when_not_yet_configured(): void
    {
        [$seller] = $this->sellerWithShop(onlinePayments: true);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'przelewy24_merchant_id' => '123456',
                'przelewy24_sandbox' => '1',
            ])
            ->assertSessionHasErrors(['przelewy24_crc_key', 'przelewy24_api_key']);

        $this->assertSame(0, ShopIntegration::where('type', IntegrationType::Przelewy24)->count());
    }

    public function test_przelewy24_secrets_without_merchant_id_are_rejected(): void
    {
        // Sekrety bez Merchant ID zostałyby po cichu wyrzucone (puste Merchant ID
        // = odłączenie) — sprzedawca myślałby, że coś podłączył.
        [$seller] = $this->sellerWithShop(onlinePayments: true);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'przelewy24_crc_key' => 'CRC',
                'przelewy24_api_key' => 'API',
                'przelewy24_sandbox' => '1',
            ])
            ->assertSessionHasErrors('przelewy24_merchant_id');
    }

    public function test_przelewy24_merchant_id_must_be_numeric(): void
    {
        [$seller] = $this->sellerWithShop(onlinePayments: true);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'przelewy24_merchant_id' => 'abc-123',
                'przelewy24_crc_key' => 'CRC',
                'przelewy24_api_key' => 'API',
                'przelewy24_sandbox' => '1',
            ])
            ->assertSessionHasErrors('przelewy24_merchant_id');
    }

    public function test_blank_przelewy24_secrets_on_resave_keep_stored_ones_and_auto_invoice(): void
    {
        Http::fake(['*/api/v1/testAccess' => Http::response(['data' => true])]);
        [$seller, $shop] = $this->sellerWithShop(onlinePayments: true);
        $this->configurePrzelewy24($shop, ['auto_invoice' => true]);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'przelewy24_merchant_id' => '777777',
                'przelewy24_crc_key' => '',
                'przelewy24_api_key' => '',
                'przelewy24_sandbox' => '1',
            ])
            ->assertSessionHas('success');

        $config = $shop->integrations()->where('type', IntegrationType::Przelewy24)->first()->config;
        $this->assertSame(777777, $config['merchant_id']);
        $this->assertSame('CRC-KEEP', $config['crc_key']);
        $this->assertSame('API-KEEP', $config['api_key']);
        // Decyzja z Ustawień nie ginie przy zapisie danych połączenia.
        $this->assertTrue($config['auto_invoice']);
    }

    public function test_clearing_przelewy24_merchant_id_disconnects_only_przelewy24(): void
    {
        [$seller, $shop] = $this->sellerWithShop(onlinePayments: true);
        $this->configurePrzelewy24($shop);
        $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => true,
            'config' => ['api_key' => 'K', 'signature_key' => 'S', 'environment' => 'sandbox'],
        ]);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'paynow_api_key' => 'K',
                'paynow_sandbox' => '1',
                'przelewy24_merchant_id' => '',
                'przelewy24_sandbox' => '1',
            ])
            ->assertSessionHas('success');

        $this->assertSame(0, ShopIntegration::where('type', IntegrationType::Przelewy24)->count());
        // Druga bramka żyje dalej — każda ma własny wiersz.
        $this->assertTrue($shop->fresh()->onlinePaymentsEnabled());
    }

    public function test_przelewy24_is_not_saved_without_entitlement(): void
    {
        [$seller, $shop] = $this->sellerWithShop();

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), [
                'przelewy24_merchant_id' => '123456',
                'przelewy24_crc_key' => 'CRC',
                'przelewy24_api_key' => 'API',
                'przelewy24_sandbox' => '1',
            ])
            ->assertRedirect(route('seller.integrations.edit'));

        $this->assertNull($shop->fresh()->integration(IntegrationType::Przelewy24));
    }

    public function test_settings_toggle_and_auto_invoice_for_przelewy24_are_independent_of_paynow(): void
    {
        [$seller, $shop] = $this->sellerWithShop(['default_vat_rate' => '23'], onlinePayments: true, invoicing: true);
        $p24 = $this->configurePrzelewy24($shop);
        $paynow = $shop->integrations()->create([
            'type' => IntegrationType::Payments,
            'enabled' => true,
            'config' => ['api_key' => 'K', 'signature_key' => 'S', 'environment' => 'sandbox'],
        ]);

        $this->actingAs($seller)
            ->get(route('seller.settings.edit'))
            ->assertOk()
            ->assertSee('Płatności online (Przelewy24)');

        // Paynow wyłączony, P24 włączony z auto-FV — każda bramka osobno.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), [
                'default_vat_rate' => '23',
                'przelewy24_enabled' => '1',
                'przelewy24_auto_invoice' => '1',
            ])
            ->assertSessionHas('success');

        $p24 = $p24->fresh();
        $this->assertTrue($p24->enabled);
        $this->assertTrue($p24->autoInvoiceAfterPayment());
        // Klucze nietknięte — merge, nie nadpisanie.
        $this->assertSame('CRC-KEEP', $p24->config['crc_key']);
        $this->assertFalse($paynow->fresh()->enabled);
        $this->assertFalse($paynow->fresh()->autoInvoiceAfterPayment());

        // Wyłączenie P24: checkbox odznaczony = pole nieobecne w POST.
        $this->actingAs($seller)
            ->post(route('seller.settings.update'), ['default_vat_rate' => '23']);

        $this->assertFalse($p24->fresh()->enabled);
        $this->assertFalse($shop->fresh()->przelewy24Enabled());
    }

    public function test_przelewy24_toggle_is_disabled_until_configured(): void
    {
        [$seller] = $this->sellerWithShop(onlinePayments: true);

        $this->actingAs($seller)
            ->get(route('seller.settings.edit'))
            ->assertOk()
            ->assertSee('najpierw podaj dane Przelewy24', false);
    }

    /**
     * @return array<string, string>
     */
    private function przelewy24Form(array $overrides = []): array
    {
        return array_merge([
            'przelewy24_merchant_id' => '431164',
            'przelewy24_crc_key' => 'CRC',
            'przelewy24_api_key' => 'API',
            'przelewy24_sandbox' => '1',
        ], $overrides);
    }

    public function test_saving_przelewy24_checks_the_connection_and_confirms_it(): void
    {
        Http::fake(['sandbox.przelewy24.pl/api/v1/testAccess' => Http::response(['data' => true])]);
        [$seller] = $this->sellerWithShop(onlinePayments: true);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), $this->przelewy24Form())
            ->assertSessionHas('success', 'Zapisano ustawienia integracji. Połączenie z Przelewy24 działa.')
            ->assertSessionMissing('error');

        Http::assertSentCount(1);
    }

    public function test_failed_przelewy24_connection_keeps_data_and_explains_what_to_check(): void
    {
        // Najczęstsza przyczyna to IP niewpisane w panelu P24 — dane zostają
        // zapisane, żeby po poprawce w P24 nie trzeba było wklejać kluczy.
        Http::fake(['*/api/v1/testAccess' => Http::response(['error' => 'Incorrect authentication', 'code' => 401], 401)]);
        [$seller, $shop] = $this->sellerWithShop(onlinePayments: true);

        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), $this->przelewy24Form())
            ->assertSessionHas('success', 'Zapisano ustawienia integracji.')
            ->assertSessionHas('error');

        $this->assertTrue($shop->fresh()->przelewy24Configured());
    }

    public function test_resave_without_przelewy24_changes_does_not_call_przelewy24(): void
    {
        Http::fake();
        [$seller, $shop] = $this->sellerWithShop(onlinePayments: true);
        $this->configurePrzelewy24($shop, ['merchant_id' => 431164]);

        // Ten sam Merchant ID, puste sekrety = „zostaw" — nic się nie zmieniło.
        $this->actingAs($seller)
            ->post(route('seller.integrations.update'), $this->przelewy24Form([
                'przelewy24_crc_key' => '',
                'przelewy24_api_key' => '',
            ]))
            ->assertSessionHas('success', 'Zapisano ustawienia integracji.');

        Http::assertNothingSent();
    }
}
