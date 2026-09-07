<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\BulkMailingRequest;
use App\Models\BulkMailing;
use App\Models\Shop;
use App\Services\BulkMailService;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Wiadomości do klientów (korespondencja seryjna). Funkcja płatna
 * (uprawnienie `bulk_mail`, pakiet Pawilon) — stronę widzą wszyscy, ale bez
 * uprawnienia dostają zachętę zamiast narzędzia, jak przy kodach rabatowych.
 *
 * Kontroler odpowiada wyłącznie za SZKIC: utworzenie, edycję i skasowanie.
 * Samą wysyłkę — próbkę do siebie i wysyłkę do klientów — prowadzi komponent
 * Livewire, bo wymaga potwierdzenia w miejscu i odświeżania licznika bez
 * przeładowania strony.
 */
class BulkMailingController extends Controller
{
    public function index(Request $request, BulkMailService $mail): Renderable
    {
        $shop = $request->user()->currentShop();
        $allowed = (bool) $shop?->entitlement('bulk_mail');

        return view('seller.mailings.index', [
            'shop' => $shop,
            'allowed' => $allowed,
            // Postęp wysyłki liczymy podzapytaniami (`withCount`), a nie
            // zapytaniem na wiersz — lista ma 10 pozycji, a każda mogłaby
            // odpytywać outbox o setki maili.
            'mailings' => $allowed
                ? $shop->bulkMailings()
                    ->withCount([
                        'messages',
                        'messages as delivered_count' => fn ($query) => $query->whereNotNull('sent_at'),
                        'messages as failed_count' => fn ($query) => $query->whereNotNull('failed_at'),
                    ])
                    ->paginate(10)
                : null,
            // Liczba zgód liczona TAKŻE przy zablokowanym pakiecie: „12 klientów już
            // się zgodziło na Twoje wiadomości" to najmocniejsza zachęta, jaką mamy
            // na tym ekranie — mocniejsza od opisu funkcji.
            'recipients' => $shop !== null ? $mail->recipientsCount($shop) : 0,
            'blockedUntil' => $allowed ? $mail->nextAllowedAt($shop) : null,
        ]);
    }

    public function create(Request $request): Renderable
    {
        $shop = $this->allowedShop($request);

        return view('seller.mailings.form', [
            'mailing' => null,
            'products' => $this->promotableProducts($shop),
        ]);
    }

    public function store(BulkMailingRequest $request): RedirectResponse
    {
        $shop = $this->allowedShop($request);

        $mailing = $shop->bulkMailings()->create($request->validated());

        // Prosto do edycji: dopiero tam jest wysyłka próbki, a zapisany szkic
        // jest jej warunkiem.
        return redirect()
            ->route('seller.mailings.edit', $mailing)
            ->with('success', 'Szkic zapisany. Wyślij próbkę do siebie i sprawdź, jak wygląda.');
    }

    public function edit(Request $request, BulkMailing $bulkMailing): Renderable
    {
        $shop = $this->allowedShop($request);
        $this->authorizeMailing($shop, $bulkMailing);

        return view('seller.mailings.form', [
            'mailing' => $bulkMailing,
            'products' => $this->promotableProducts($shop),
        ]);
    }

    /**
     * Produkty do wypromowania: tylko AKTYWNE, bo mail prowadzi klienta wprost
     * na kartę produktu — wysłanie go do wyłączonego byłoby zaproszeniem
     * donikąd. Najnowsze pierwsze: mailing zwykle dotyczy nowości.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Product>
     */
    private function promotableProducts(Shop $shop): \Illuminate\Support\Collection
    {
        return $shop->products()
            ->where('is_active', true)
            ->latest('id')
            ->get(['id', 'name', 'price_gross']);
    }

    public function update(BulkMailingRequest $request, BulkMailing $bulkMailing): RedirectResponse
    {
        $shop = $this->allowedShop($request);
        $this->authorizeMailing($shop, $bulkMailing);
        $this->guardDraft($bulkMailing);

        $bulkMailing->update($request->validated());

        return redirect()
            ->route('seller.mailings.edit', $bulkMailing)
            ->with('success', 'Zmiany zapisane.');
    }

    public function destroy(Request $request, BulkMailing $bulkMailing): RedirectResponse
    {
        $shop = $this->allowedShop($request);
        $this->authorizeMailing($shop, $bulkMailing);
        $this->guardDraft($bulkMailing);

        $bulkMailing->delete();

        return redirect()
            ->route('seller.mailings.index')
            ->with('success', 'Szkic usunięty.');
    }

    /**
     * Sklep sprzedawcy z uprawnieniem — inaczej 403. Widok listy jest jedynym
     * miejscem, które pokazuje zachętę; reszta akcji jest twardo zamknięta.
     */
    private function allowedShop(Request $request): Shop
    {
        $shop = $request->user()->currentShop();

        abort_unless($shop?->entitlement('bulk_mail') === true, 403);

        return $shop;
    }

    private function authorizeMailing(Shop $shop, BulkMailing $mailing): void
    {
        abort_unless($mailing->shop_id === $shop->id, 404);
    }

    /**
     * Wysłanej wiadomości nie wolno już zmieniać ani kasować — klienci mają ją
     * w skrzynkach, więc zapis musi zostać zgodny z tym, co dostali.
     */
    private function guardDraft(BulkMailing $mailing): void
    {
        abort_if($mailing->isSent(), 403);
    }
}
