<?php

namespace App\Livewire\Seller;

use App\Models\Order;
use App\Models\OrderReturn;
use Livewire\Component;

/**
 * Sekcja „Zwroty" na szczególe zamówienia: co klient oddał, za ile i czy
 * pieniądze już wróciły. Widoczna dopiero, gdy jakiś zwrot wpłynął — pusta
 * karta przy każdym zamówieniu byłaby szumem.
 *
 * Jedyna akcja sprzedawcy to „Pieniądze zwrócone" — świadomie BEZ akceptacji
 * i odrzucania: odstąpienie od umowy działa z mocy prawa, sprzedawca go nie
 * zatwierdza. Zamówienie zostało pomniejszone już przy zgłoszeniu.
 *
 * W v1 przelew, zwrot w Paynow i faktura korygująca dzieją się poza systemem —
 * ten przycisk odnotowuje, że sprzedawca to zrobił.
 */
class OrderReturns extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order;
    }

    /**
     * Odnotowuje zwrot pieniędzy. Idempotentne (`markRefunded` nie przesuwa raz
     * ustawionej daty), więc dubel kliknięcia niczego nie psuje.
     */
    public function markRefunded(int $returnId): void
    {
        abort_unless($this->order->shop_id === auth()->user()?->currentShop()?->id, 403);

        // Zwrot bierzemy PRZEZ relację zamówienia — identyfikator z formularza
        // nie może sięgnąć zgłoszenia z cudzego zamówienia.
        $return = $this->order->returns()->whereKey($returnId)->first();

        $return?->markRefunded();

        $this->order->refresh();
    }

    public function render()
    {
        $returns = $this->order->returns()->with('items.orderItem')->get();
        $pending = $returns->reject(fn (OrderReturn $return): bool => $return->isRefunded());

        return view('livewire.seller.order-returns', [
            'returns' => $returns,
            'pendingTotal' => $pending->sum(fn (OrderReturn $return): float => (float) $return->refund_gross),
            // Przy kilku zgłoszeniach obowiązuje NAJWCZEŚNIEJSZY termin — to on
            // mija pierwszy, więc tylko on jest sprzedawcy do czegoś potrzebny.
            'pendingDeadline' => $pending
                ->map(fn (OrderReturn $return) => $return->refundDeadline())
                ->sort()
                ->first(),
        ]);
    }
}
