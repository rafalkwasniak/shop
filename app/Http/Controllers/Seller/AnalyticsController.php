<?php

namespace App\Http\Controllers\Seller;

use App\Enums\AnalyticsPeriod;
use App\Http\Controllers\Controller;
use App\Services\ShopAnalytics;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Dział „Analityka" sprzedawcy (Poziom 1): dashboard z danych, które już mamy —
 * zero śledzenia ruchu, zero zapisów przy żądaniu. Dostępny dla WSZYSTKICH
 * pakietów (własna analityka = baza sklepu; GA/GTM to płatny dodatek osobno).
 * Okno czasowe z parametru `okres` (kroczące, domyślnie 30 dni).
 */
class AnalyticsController extends Controller
{
    public function index(Request $request, ShopAnalytics $analytics): Renderable|RedirectResponse
    {
        $shop = $request->user()->currentShop();

        if ($shop === null) {
            return redirect()->route('seller.dashboard');
        }

        $period = AnalyticsPeriod::fromValue($request->query('okres'));

        return view('seller.analytics.index', [
            'shop' => $shop,
            'period' => $period,
            'periods' => AnalyticsPeriod::cases(),
            'analytics' => $analytics->for($shop, $period),
        ]);
    }
}
