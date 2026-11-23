<?php

namespace App\Http\Controllers\Seller;

use App\Enums\IntegrationType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SitemapController;
use App\Http\Requests\Seller\IntegrationRequest;
use App\Models\Shop;
use App\Services\Przelewy24Service;
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
            'paynowEnabled' => $shop->paynowEnabled(),
            'paynowEnvironment' => $shop->paynowEnvironment(),
            'paynowWebhookUrl' => $shop->paynowWebhookUrl(),
            'przelewy24MerchantId' => $shop->przelewy24MerchantId(),
            // POS ID pokazujemy tylko, gdy różni się od Merchant ID — inaczej
            // pole zostaje puste („taki sam"), zgodnie z domyślną regułą P24.
            'przelewy24PosId' => $shop->integration(IntegrationType::Przelewy24)?->config['pos_id'] ?? null,
            'przelewy24Configured' => $shop->przelewy24Configured(),
            'przelewy24Enabled' => $shop->przelewy24Enabled(),
            'przelewy24Environment' => $shop->przelewy24Environment(),
            'serverIp' => config('services.przelewy24.server_ip'),
            'shipxOrganizationId' => $shop->shipxOrganizationId(),
            'shipxConfigured' => $shop->shipxConfigured(),
            'shipxEnabled' => $shop->shipxEnabled(),
            'shipxEnvironment' => $shop->shipxEnvironment(),
            'siteVerification' => $shop->googleSiteVerification(),
            'sitemapUrl' => SitemapController::urlFor($shop),
        ]);
    }

    public function update(IntegrationRequest $request, Przelewy24Service $p24): RedirectResponse
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

            // Druga bramka pod tym samym uprawnieniem — `online_payments` otwiera
            // płatności online jako takie, niezależnie od liczby operatorów.
            $p24Before = $this->przelewy24Connection($shop);
            $this->savePrzelewy24($shop, $data);
            $shop->load('integrations');

            // Test połączenia TYLKO po zmianie danych połączenia P24 — sprzedawca
            // od razu wie, czy klucze, środowisko i IP w panelu P24 są dobre, a
            // zapis innych integracji nie odpytuje P24 za każdym razem.
            if ($shop->przelewy24Configured() && $this->przelewy24Connection($shop) !== $p24Before) {
                $p24Connected = $p24->testAccess($shop);
            }
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

        $redirect = redirect()
            ->route('seller.integrations.edit')
            ->with('success', ($p24Connected ?? null) === true
                ? 'Zapisano ustawienia integracji. Połączenie z Przelewy24 działa.'
                : 'Zapisano ustawienia integracji.');

        // Dane zapisujemy mimo nieudanego testu — przyczyna bywa po stronie P24
        // (np. IP jeszcze niewpisane w panelu), a poprawka nie wymaga ponownego
        // wklejania kluczy.
        if (($p24Connected ?? null) === false) {
            $redirect->with('error', 'Przelewy24 nie przyjęły połączenia. Sprawdź ID sprzedawcy, klucz do raportów, środowisko (sandbox/produkcja) oraz czy adres IP serwera jest wpisany w panelu Przelewy24.');
        }

        return $redirect;
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
     * Zapis konfiguracji Przelewy24. Odłączanie sterowane Merchant ID (jawny,
     * wraca w polu): puste = usunięcie integracji. CRC i klucz API to sekrety —
     * puste pole znaczy „zostaw zapisany" (FormRequest pilnuje kompletu przy
     * pierwszej konfiguracji). POS ID puste = taki sam jak Merchant ID. Flagę
     * `auto_invoice` (Ustawienia) przepisujemy 1:1, jak przy Paynow.
     *
     * @param  array<string, mixed>  $data
     */
    private function savePrzelewy24(Shop $shop, array $data): void
    {
        $integration = $shop->integration(IntegrationType::Przelewy24);

        if (blank($data['przelewy24_merchant_id'] ?? null)) {
            $integration?->delete();

            return;
        }

        $stored = $integration?->config ?? [];

        $config = [
            'merchant_id' => (int) $data['przelewy24_merchant_id'],
            'pos_id' => filled($data['przelewy24_pos_id'] ?? null) ? (int) $data['przelewy24_pos_id'] : null,
            'crc_key' => filled($data['przelewy24_crc_key'] ?? null) ? $data['przelewy24_crc_key'] : ($stored['crc_key'] ?? null),
            'api_key' => filled($data['przelewy24_api_key'] ?? null) ? $data['przelewy24_api_key'] : ($stored['api_key'] ?? null),
            'environment' => $data['przelewy24_environment'] ?? 'sandbox',
            'auto_invoice' => $stored['auto_invoice'] ?? false,
        ];

        if ($integration !== null) {
            $integration->update(['config' => $config]);
        } else {
            $shop->integrations()->create([
                'type' => IntegrationType::Przelewy24,
                'enabled' => true,
                'config' => $config,
            ]);
        }
    }

    /**
     * Dane, od których zależy połączenie z P24 — bez flag z Ustawień (np.
     * `auto_invoice`), żeby ich zmiana nie wywoływała testu połączenia.
     *
     * @return array<string, mixed>|null
     */
    private function przelewy24Connection(Shop $shop): ?array
    {
        $config = $shop->integration(IntegrationType::Przelewy24)?->config;

        if ($config === null) {
            return null;
        }

        return [
            'merchant_id' => $config['merchant_id'] ?? null,
            'pos_id' => $config['pos_id'] ?? null,
            'crc_key' => $config['crc_key'] ?? null,
            'api_key' => $config['api_key'] ?? null,
            'environment' => $config['environment'] ?? null,
        ];
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
