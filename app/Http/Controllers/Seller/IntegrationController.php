<?php

namespace App\Http\Controllers\Seller;

use App\Enums\IntegrationType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SitemapController;
use App\Http\Requests\Seller\IntegrationRequest;
use App\Models\Shop;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Integracje sklepu (kategoria 3 z plan-shop-settings-storage). Tu sprzedawca
 * KONFIGURUJE usługi (wpisuje identyfikatory/klucze); WŁĄCZA/wyłącza je w
 * Ustawieniach. Obecnie: Google Analytics / Tag Manager oraz Fakturownia (FV).
 * Edycja przez POST (FOUNDATION sek. 5).
 */
class IntegrationController extends Controller
{
    public function edit(Request $request): Renderable|RedirectResponse
    {
        $shop = $request->user()->currentShop();

        if ($shop === null) {
            return redirect()->route('seller.dashboard');
        }

        return view('seller.integrations.edit', [
            'shop' => $shop,
            'googleAnalyticsId' => $shop->googleAnalyticsId(),
            'fakturowniaUrl' => $shop->fakturowniaAccountUrl(),
            'fakturowniaConfigured' => $shop->invoicingConfigured(),
            'fakturowniaEnabled' => $shop->invoicingEnabled(),
            'paynowApiKey' => $shop->paynowApiKey(),
            'paynowConfigured' => $shop->onlinePaymentsConfigured(),
            'paynowEnabled' => $shop->onlinePaymentsEnabled(),
            'paynowEnvironment' => $shop->paynowEnvironment(),
            'paynowWebhookUrl' => $shop->paynowWebhookUrl(),
            'shipxOrganizationId' => $shop->shipxOrganizationId(),
            'shipxConfigured' => $shop->shipxConfigured(),
            'shipxEnabled' => $shop->shipxEnabled(),
            'shipxEnvironment' => $shop->shipxEnvironment(),
            'siteVerification' => $shop->googleSiteVerification(),
            'sitemapUrl' => SitemapController::urlFor($shop),
        ]);
    }

    public function update(IntegrationRequest $request): RedirectResponse
    {
        $shop = $request->user()->currentShop();
        $data = $request->validated();

        // GA/GTM bramkowane uprawnieniem pakietu (Stragan+) — bez niego karta się
        // nie renderuje, ale nie ufamy widokowi: zapis tylko gdy wolno.
        if ($shop->entitlement('ga_analytics')) {
            $this->saveGoogleAnalytics($shop, $data['google_analytics_id'] ?? null);
        }

        // Fakturownia bramkowana uprawnieniem pakietu — bez niego pola i tak nie
        // renderują się w formularzu, ale nie ufamy widokowi: zapis tylko gdy wolno.
        if ($shop->entitlement('invoices')) {
            $this->saveFakturownia($shop, $data['fakturownia_url'] ?? null, $data['fakturownia_token'] ?? null);
        }

        // Paynow bramkowany uprawnieniem pakietu — bez niego karta i tak się nie
        // renderuje, ale nie ufamy widokowi: zapis tylko gdy wolno.
        if ($shop->entitlement('online_payments')) {
            $this->savePaynow(
                $shop,
                $data['paynow_api_key'] ?? null,
                $data['paynow_signature_key'] ?? null,
                $data['paynow_environment'] ?? 'sandbox',
            );
        }

        // Nadawanie przesyłek bramkowane tym samym uprawnieniem co płatna wysyłka
        // (`courier_shipping`) — etykiety to jej część, nie osobny produkt.
        if ($shop->entitlement('courier_shipping')) {
            $this->saveShipx(
                $shop,
                $data['shipx_token'] ?? null,
                $data['shipx_organization_id'] ?? null,
                $data['shipx_environment'] ?? 'sandbox',
            );
        }

        // Weryfikacja Search Console CELOWO bez bramki pakietu — to nie analityka,
        // tylko jedyna droga zgłoszenia mapy strony, którą dostają wszystkie sklepy.
        $this->saveSearchConsole($shop, $data['google_site_verification'] ?? null);

        return redirect()
            ->route('seller.integrations.edit')
            ->with('success', 'Zapisano ustawienia integracji.');
    }

    /**
     * Zapis identyfikatora GA: puste = usunięcie integracji (z nią znika włącznik),
     * inaczej aktualizacja lub pierwsza konfiguracja (od razu włączona — mniej
     * klikania; sprzedawca i tak może ją wyłączyć w Ustawieniach).
     */
    private function saveGoogleAnalytics(Shop $shop, ?string $id): void
    {
        $integration = $shop->integration(IntegrationType::GoogleAnalytics);

        if (blank($id)) {
            $integration?->delete();
        } elseif ($integration !== null) {
            $integration->update(['config' => ['tracking_id' => $id]]);
        } else {
            $shop->integrations()->create([
                'type' => IntegrationType::GoogleAnalytics,
                'enabled' => true,
                'config' => ['tracking_id' => $id],
            ]);
        }
    }

    /**
     * Zapis kodu weryfikacyjnego Search Console. Puste = usunięcie. Integracja
     * nie ma włącznika w Ustawieniach (inaczej niż GA): meta tag niczego nie
     * śledzi i nie stawia ciasteczek, więc „wyłączona weryfikacja" znaczyłaby
     * tylko tyle, co brak kodu.
     */
    private function saveSearchConsole(Shop $shop, ?string $code): void
    {
        $integration = $shop->integration(IntegrationType::SearchConsole);

        if (blank($code)) {
            $integration?->delete();
        } elseif ($integration !== null) {
            $integration->update(['config' => ['verification_code' => $code]]);
        } else {
            $shop->integrations()->create([
                'type' => IntegrationType::SearchConsole,
                'enabled' => true,
                'config' => ['verification_code' => $code],
            ]);
        }
    }

    /**
     * Zapis konfiguracji Fakturowni. Reguła odłączania trzyma się adresu: pusty
     * adres = usunięcie integracji. Przy obecnym adresie pusty token znaczy
     * „zostaw dotychczasowy" (sekretu nie odbijamy w formularzu, więc nie każemy
     * go przepisywać przy każdej edycji) — FormRequest pilnuje, by token istniał,
     * gdy konfigurujemy od zera.
     */
    private function saveFakturownia(Shop $shop, ?string $url, ?string $token): void
    {
        $integration = $shop->integration(IntegrationType::Invoicing);

        if (blank($url)) {
            $integration?->delete();

            return;
        }

        $config = [
            'account_url' => $url,
            'api_token' => filled($token) ? $token : ($integration?->config['api_token'] ?? null),
        ];

        if ($integration !== null) {
            $integration->update(['config' => $config]);
        } else {
            $shop->integrations()->create([
                'type' => IntegrationType::Invoicing,
                'enabled' => true,
                'config' => $config,
            ]);
        }
    }

    /**
     * Zapis konfiguracji Paynow. Reguła odłączania trzyma się klucza API: pusty
     * klucz API = usunięcie integracji. Klucz podpisu to sekret — nie odbijamy go
     * w formularzu, więc puste pole znaczy „zostaw dotychczasowy" (FormRequest
     * pilnuje, by istniał przy konfiguracji od zera). Środowisko zapisujemy zawsze.
     * Flagę `auto_invoice` ustawia się w Ustawieniach (pod włącznikiem Paynow) — tu
     * ją tylko przepisujemy 1:1, żeby zapis kluczy nie skasował zapisanej decyzji.
     */
    private function savePaynow(Shop $shop, ?string $apiKey, ?string $signatureKey, string $environment): void
    {
        $integration = $shop->integration(IntegrationType::Payments);

        if (blank($apiKey)) {
            $integration?->delete();

            return;
        }

        $config = [
            'api_key' => $apiKey,
            'signature_key' => filled($signatureKey) ? $signatureKey : ($integration?->config['signature_key'] ?? null),
            'environment' => $environment,
            'auto_invoice' => $integration?->config['auto_invoice'] ?? false,
        ];

        if ($integration !== null) {
            $integration->update(['config' => $config]);
        } else {
            $shop->integrations()->create([
                'type' => IntegrationType::Payments,
                'enabled' => true,
                'config' => $config,
            ]);
        }
    }

    /**
     * Zapis danych ShipX. Kasowanie sterowane Organization ID, a NIE tokenem:
     * token jest sekretem (pole zawsze puste przy wejściu, puste = „zostaw"),
     * więc gdyby on decydował, każdy zapis formularza kasowałby integrację.
     * Organization ID jest jawne i wraca w polu, więc jego wyczyszczenie to
     * świadome „rozłącz konto".
     */
    private function saveShipx(Shop $shop, ?string $token, ?string $organizationId, string $environment): void
    {
        $integration = $shop->integration(IntegrationType::Shipping);

        if (blank($organizationId)) {
            $integration?->delete();

            return;
        }

        $config = [
            'token' => filled($token) ? $token : ($integration?->config['token'] ?? null),
            'organization_id' => $organizationId,
            'environment' => $environment,
        ];

        if ($integration !== null) {
            $integration->update(['config' => $config]);
        } else {
            $shop->integrations()->create([
                'type' => IntegrationType::Shipping,
                'enabled' => true,
                'config' => $config,
            ]);
        }
    }
}
