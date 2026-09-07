<?php

namespace App\Livewire\Seller;

use App\Models\Order;
use Livewire\Component;

/**
 * Faktura VAT przy danych kupującego na szczególe zamówienia. JEDEN komponent na
 * cały cykl w JEDNYM miejscu (obok danych do faktury): przycisk „Stwórz fakturę
 * VAT" z potwierdzeniem w miejscu → „w przygotowaniu" → „Pobierz fakturę VAT",
 * a przy błędzie ponowienie. Bez rozbijania na osobną kartę w kolumnie bocznej —
 * sprzedawca prowadzi całą sprawę tam, gdzie na nią patrzy.
 *
 * Sama robota (wołanie Fakturowni, mail „Pobierz FV") dzieje się w tle w jobie
 * {@see \App\Jobs\GenerateInvoice}; „w przygotowaniu" odświeża się `wire:poll`,
 * aż kolejka (cron) domknie fakturę.
 */
class OrderInvoice extends Component
{
    public Order $order;

    /** Czy pokazujemy potwierdzenie w miejscu (zamiast natywnego dymka). */
    public bool $confirming = false;

    public function mount(Order $order): void
    {
        $this->order = $order;
    }

    public function askCreate(): void
    {
        $this->authorizeOwnership();

        $this->confirming = true;
    }

    public function dismiss(): void
    {
        $this->confirming = false;
    }

    /**
     * Zleca wystawienie FV (pierwsza próba lub ponowienie po błędzie). Guard w
     * `requestInvoice()` chroni przed dublem i zleceniem bez konfiguracji.
     */
    public function create(): void
    {
        $this->authorizeOwnership();

        $this->confirming = false;
        $this->order->requestInvoice();
    }

    private function authorizeOwnership(): void
    {
        abort_unless($this->order->shop_id === auth()->user()?->currentShop()?->id, 403);
    }

    public function render()
    {
        // Widoczne, gdy sklep używa Fakturowni albo faktura już istnieje (żeby
        // link do PDF został nawet po późniejszym wyłączeniu integracji).
        $visible = $this->order->hasInvoice()
            || ($this->order->shop?->entitlement('invoices') === true && $this->order->shop->invoicingEnabled());

        return view('livewire.seller.order-invoice', [
            'visible' => $visible,
        ]);
    }
}
