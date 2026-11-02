<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\BannerIntervalRequest;
use App\Http\Requests\Seller\BannerRequest;
use App\Models\Shop;
use App\Models\ShopBanner;
use App\Services\BannerImageService;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Banery strony głównej (Wygląd → Banery). Jeden baner = statyczny obrazek pod
 * nazwą sklepu, dwa i więcej = slider. Liczba z pakietu (`max_banners`).
 *
 * Limit pilnujemy przy DODAWANIU (jak produkty): liczą się wszystkie banery,
 * także ukryte. Po zejściu na niższy pakiet nic nie kasujemy — nadmiarowe
 * z końca listy po prostu nie wychodzą na stronę (Shop::storefrontBanners),
 * a lista w panelu mówi, które to.
 */
class BannerController extends Controller
{
    public function index(Request $request): Renderable
    {
        $shop = $this->shop($request);
        $limit = $this->limit($shop);

        return view('seller.banners.index', [
            'shop' => $shop,
            'banners' => $shop->banners()->get(),
            'limit' => $limit,
            'limitReached' => $shop->banners()->count() >= $limit,
        ]);
    }

    public function create(Request $request): Renderable|RedirectResponse
    {
        $shop = $this->shop($request);

        if ($redirect = $this->refuseOverLimit($shop)) {
            return $redirect;
        }

        return view('seller.banners.form', ['shop' => $shop, 'banner' => new ShopBanner(['is_active' => true])]);
    }

    public function store(BannerRequest $request, BannerImageService $images): RedirectResponse
    {
        $shop = $this->shop($request);

        if ($redirect = $this->refuseOverLimit($shop)) {
            return $redirect;
        }

        $banner = new ShopBanner($request->safe()->except('image'));
        $banner->shop_id = $shop->id;
        $banner->position = (int) $shop->banners()->max('position') + 1;
        $banner->forceFill($images->store($request->file('image'), $shop->id));
        $banner->save();

        return redirect()->route('seller.banners.index')->with('success', 'Baner został dodany.');
    }

    public function edit(Request $request, ShopBanner $banner): Renderable
    {
        $shop = $this->shop($request);
        $this->authorizeBanner($shop, $banner);

        return view('seller.banners.form', ['shop' => $shop, 'banner' => $banner]);
    }

    public function update(BannerRequest $request, ShopBanner $banner, BannerImageService $images): RedirectResponse
    {
        $shop = $this->shop($request);
        $this->authorizeBanner($shop, $banner);

        $banner->fill($request->safe()->except('image'));

        if ($request->hasFile('image')) {
            // Najpierw nowe pliki, potem kasacja starych — błąd przy zapisie
            // nie zostawi banera bez obrazka.
            $old = $banner->paths();
            $banner->forceFill($images->store($request->file('image'), $shop->id));
            $banner->save();
            Storage::disk('public')->delete($old);
        } else {
            $banner->save();
        }

        return redirect()->route('seller.banners.index')->with('success', 'Zmiany zapisane.');
    }

    public function destroy(Request $request, ShopBanner $banner): RedirectResponse
    {
        $shop = $this->shop($request);
        $this->authorizeBanner($shop, $banner);

        $banner->delete();

        return redirect()->route('seller.banners.index')->with('success', 'Baner został usunięty.');
    }

    /** Kolejność przeciąganiem — ten sam mechanizm co strony w „Informacjach". */
    public function reorder(Request $request): JsonResponse
    {
        $shop = $this->shop($request);

        $ids = collect($request->input('order', []))
            ->map(fn ($id): int => (int) $id)
            ->filter();

        // Tylko banery tego sklepu — zamyka manipulację obcymi ID.
        $owned = $shop->banners()->whereIn('id', $ids)->pluck('id')->all();

        DB::transaction(function () use ($ids, $owned): void {
            $position = 0;
            foreach ($ids as $id) {
                if (in_array($id, $owned, true)) {
                    ShopBanner::query()->whereKey($id)->update(['position' => $position++]);
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    public function interval(BannerIntervalRequest $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $shop->update(['banner_interval' => (int) $request->validated('banner_interval')]);

        return redirect()->route('seller.banners.index')->with('success', 'Czas wyświetlania zapisany.');
    }

    private function shop(Request $request): Shop
    {
        $shop = $request->user()->currentShop();
        abort_if($shop === null, 403);

        return $shop;
    }

    private function limit(Shop $shop): int
    {
        return max(0, (int) $shop->entitlement('max_banners'));
    }

    private function refuseOverLimit(Shop $shop): ?RedirectResponse
    {
        $limit = $this->limit($shop);

        if ($shop->banners()->count() < $limit) {
            return null;
        }

        return redirect()
            ->route('seller.banners.index')
            ->with('error', $limit === 1
                ? 'Twój pakiet pozwala na jeden baner. Podmień obrazek w istniejącym albo usuń go, żeby dodać nowy.'
                : 'Twój pakiet pozwala na '.$limit.' banerów. Usuń któryś, żeby dodać nowy.');
    }

    private function authorizeBanner(Shop $shop, ShopBanner $banner): void
    {
        abort_unless($banner->shop_id === $shop->id, 404);
    }
}
