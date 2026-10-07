<?php

namespace App\Http\Controllers\Seller;

use App\Enums\IntegrationType;
use App\Enums\SaleUnit;
use App\Enums\SendingMethod;
use App\Enums\VatRate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\LoyaltySettingsRequest;
use App\Http\Requests\Seller\ShopSettingsRequest;
use App\Models\Page;
use App\Services\LoyaltyLedger;
use App\Support\LoyaltyRules;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ustawienia sklepu — typowane pola (domyślny VAT), fiszki metod (przelew) oraz
 * WŁĄCZNIKI usług konfigurowanych w Integracjach (Google Analytics). Podział:
 * tutaj włączasz/wyłączasz, konfigurujesz w „Integracje". Edycja przez POST.
 */
class ShopSettingsController extends Controller
{
    public function edit(Request $request): Renderable|RedirectResponse
    {
        $shop = $request->user()->currentShop();

        if ($shop === null) {
            return redirect()->route('seller.dashboard');
        }

        return view('seller.settings.edit', [
            'shop' => $shop,
            'vatRates' => VatRate::cases(),
            'saleUnits' => SaleUnit::cases(),
            'googleAnalyticsId' => $shop->googleAnalyticsId(),
            'googleAnalyticsEnabled' => (bool) $shop->integration(IntegrationType::GoogleAnalytics)?->enabled,
            'fakturowniaConfigured' => $shop->invoicingConfigured(),
            'fakturowniaEnabled' => (bool) $shop->integration(IntegrationType::Invoicing)?->enabled,
            'paynowConfigured' => $shop->onlinePaymentsConfigured(),
            'paynowEnabled' => (bool) $shop->integration(IntegrationType::Payments)?->enabled,
            'paynowAutoInvoice' => $shop->autoInvoiceAfterPayment(),
            'shipxConfigured' => $shop->shipxConfigured(),
            'shipxEnabled' => (bool) $shop->integration(IntegrationType::Shipping)?->enabled,
            'sendingMethods' => SendingMethod::cases(),
            'loyaltyHasBalances' => app(LoyaltyLedger::class)->hasOutstanding($shop),
            'loyaltyRulesPage' => $shop->pages()->where('system_key', Page::LOYALTY_RULES)->first(),
        ]);
    }

    public function update(ShopSettingsRequest $request): RedirectResponse
    {
        $shop = $request->user()->currentShop();

        // Pola typowane sklepu (VAT, przelew) — bez włączników integracji ani flagi
        // auto-FV, które żyją na wierszach shop_integrations, nie na kolumnach shops.
        $shop->fill($request->safe()->except([
            'google_analytics_enabled',
            'fakturownia_enabled',
            'paynow_enabled',
            'paynow_auto_invoice',
            'shipx_enabled',
        ]));
        $shop->save();

        // Włączniki działają tylko, gdy integracja jest skonfigurowana (istnieje
        // wiersz). Bez konfiguracji checkbox jest wyłączony i nie ma czego przełączać.
        $shop->integration(IntegrationType::GoogleAnalytics)
            ?->update(['enabled' => $request->boolean('google_analytics_enabled')]);

        $shop->integration(IntegrationType::Invoicing)
            ?->update(['enabled' => $request->boolean('fakturownia_enabled')]);

        // Paynow: włącznik + wciśnięta pod nim decyzja auto-FV. Flaga leży w configu
        // integracji, więc merge'ujemy ją, nie ruszając kluczy (api_key/signature_key).
        if ($payments = $shop->integration(IntegrationType::Payments)) {
            $config = $payments->config;
            $config['auto_invoice'] = $request->boolean('paynow_auto_invoice');

            $payments->update([
                'enabled' => $request->boolean('paynow_enabled'),
                'config' => $config,
            ]);
        }

        $shop->integration(IntegrationType::Shipping)
            ?->update(['enabled' => $request->boolean('shipx_enabled')]);

        return redirect()
            ->route('seller.settings.edit')
            ->with('success', 'Zapisano ustawienia sklepu.');
    }

    /**
     * Ustawienia punktów za zakupy. Zmiana wartości punktu idzie przez
     * `LoyaltyLedger::revalue()`, które przelicza salda klientów w tej samej
     * transakcji — nigdy przez zwykły zapis kolumny, bo ten zmieniłby wartość
     * cudzych punktów po cichu.
     */
    public function updateLoyalty(LoyaltySettingsRequest $request, LoyaltyLedger $ledger): RedirectResponse
    {
        $shop = $request->user()->currentShop();
        $data = $request->validated();

        DB::transaction(function () use ($shop, $data, $ledger): void {
            $shop->fill([
                'loyalty_enabled' => $data['loyalty_enabled'],
                'loyalty_earn_percent' => $data['loyalty_earn_percent'],
                'loyalty_delay_days' => $data['loyalty_delay_days'],
                'loyalty_validity_months' => $data['loyalty_validity_months'],
                'loyalty_max_redeem_percent' => $data['loyalty_max_redeem_percent'],
                'loyalty_min_redeem_points' => $data['loyalty_min_redeem_points'],
            ])->save();

            $ledger->revalue($shop, (float) $data['loyalty_point_value']);

            if ($shop->loyalty_enabled) {
                LoyaltyRules::ensurePage($shop);
            }
        });

        return redirect()
            ->to(route('seller.settings.edit').'#punkty')
            ->with('success', 'Zapisano ustawienia punktów. Jeśli zmieniły się zasady, zajrzyj na stronę „Zasady punktów" i wstaw treść domyślną od nowa.');
    }
}
