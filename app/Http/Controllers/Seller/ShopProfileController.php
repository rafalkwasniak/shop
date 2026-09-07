<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ShopProfileRequest;
use App\Support\MetaDescription;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Edycja profilu sklepu w centrali (nazwa, opis, adres). Sprzedawca zarządza
 * własnym sklepem tutaj — nie na subdomenie. Slug/subdomena pozostają stałe.
 */
class ShopProfileController extends Controller
{
    public function edit(Request $request): Renderable|RedirectResponse
    {
        $shop = $request->user()->currentShop();

        if ($shop === null) {
            return redirect()->route('seller.dashboard');
        }

        return view('seller.shop.edit', ['shop' => $shop]);
    }

    public function update(ShopProfileRequest $request): RedirectResponse
    {
        $shop = $request->user()->currentShop();

        $shop->fill($request->validated());

        // Opis SEO wpisany ręcznie należy do sprzedawcy — znacznik pilnuje, żeby
        // automat go nie nadpisał. Wyczyszczenie pola oddaje kontrolę automatowi.
        $shop->fill(MetaDescription::fields($request->input('meta_description')));

        $shop->save();

        return redirect()
            ->route('seller.shop.edit')
            ->with('success', 'Zapisano dane sklepu.');
    }
}
