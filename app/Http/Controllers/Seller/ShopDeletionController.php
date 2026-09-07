<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ShopDeletionRequest;
use App\Services\ShopEraser;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Samoobsługowe usunięcie własnego sklepu (RODO + sprzątanie po sobie).
 *
 * Osobny ekran, nie sekcja na dole długiej strony: jest gdzie pokazać, co
 * dokładnie zniknie, a sama zmiana adresu mówi „to nie jest zwykły przycisk".
 *
 * Zlecenie NIE kasuje niczego od razu — ustawia termin (`ShopEraser::schedule`)
 * i gasi storefront. Przez karencję sprzedawca może się rozmyślić; dopiero
 * `shops:purge` po terminie usuwa sklep i konto.
 */
class ShopDeletionController extends Controller
{
    public function show(Request $request): Renderable|RedirectResponse
    {
        $shop = $request->user()->currentShop();

        if ($shop === null) {
            return redirect()->route('seller.dashboard');
        }

        return view('seller.shop.delete', [
            'shop' => $shop->loadCount(['products', 'orders', 'customers', 'pages']),
        ]);
    }

    public function store(ShopDeletionRequest $request, ShopEraser $eraser): RedirectResponse
    {
        $due = $eraser->schedule($request->user()->currentShop());

        return redirect()
            ->route('seller.deletion.show')
            ->with('success', 'Usunięcie zaplanowane na '.$due->format('d.m.Y').'. Do tego dnia możesz je cofnąć.');
    }

    public function cancel(Request $request, ShopEraser $eraser): RedirectResponse
    {
        $eraser->cancel($request->user()->currentShop());

        return redirect()
            ->route('seller.shop.edit')
            ->with('success', 'Usunięcie zatrzymane — Twój sklep znów jest widoczny.');
    }
}
