<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;

/**
 * Ekran „Odbiór kuriera". Cienki: całą robotę wykonuje komponent Livewire
 * {@see \App\Livewire\Seller\CourierPickup}, tu zostaje sama bramka dostępu.
 */
class ShipmentPickupController extends Controller
{
    public function index(): Renderable|RedirectResponse
    {
        $shop = auth()->user()?->currentShop();

        // Ekran jest bez sensu bez nadawania przez InPost — bez integracji nie
        // ma czego odbierać, a bramka pakietu jest ta sama co dla etykiet.
        if ($shop === null || ! $shop->shipxEnabled()) {
            return redirect()->route('seller.orders.index');
        }

        return view('seller.shipments.pickup');
    }
}
