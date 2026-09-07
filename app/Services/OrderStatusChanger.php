<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Jedyna droga zmiany statusu zamówienia z panelu. Skupia w jednym miejscu to,
 * co przy zmianie musi się wydarzyć razem: sprawdzenie reguł ścieżki, zapis na
 * oś czasu, zwrot towaru na stan przy anulowaniu i mail do kupującego. Dzięki
 * temu nie da się przestawić statusu „po cichu" — a mail o statusie jest właśnie
 * tym, po co ten system istnieje (sprzedawca nie siedzi w panelu, klient ma
 * wiedzieć, co się dzieje).
 *
 * Reguły (ustalenia 2026-07-14) są egzekwowane TUTAJ, nie w UI:
 *  - wolno wyłącznie po ścieżce zamówienia (`OrderFlow`) — statusy spoza niej
 *    dla tego zamówienia nie istnieją;
 *  - anulowanie jest jedynym wyjściem poza ścieżkę i jest nieodwracalne;
 *  - z anulowanego nie ma powrotu — jest zamrożone (patrz też `OrderEditor`,
 *    który z tego samego powodu nie pozwala edytować pozycji).
 */
class OrderStatusChanger
{
    public function __construct(private OrderMailer $mailer) {}

    /**
     * Czy TEN status wolno ustawić TEMU zamówieniu. Publiczne, bo tym samym
     * pytaniem UI decyduje, co w ogóle pokazać.
     */
    public function allows(Order $order, OrderStatus $to): bool
    {
        if ($order->status->isTerminal()) {
            return false;
        }

        return $to === OrderStatus::Cancelled || $order->flow()->includes($to);
    }

    /**
     * Zmienia status i kolejkuje maila. Zwraca false, gdy zmiana jest niedozwolona
     * albo gdy status się nie zmienia (no-op — nie zaśmiecamy historii ani skrzynki
     * kupującego pustym przejściem).
     *
     * Anulowanie oddaje towar na stan; zapis statusu i zwrot dzieją się w jednej
     * transakcji, żeby nie dało się zostawić zamówienia anulowanego bez zwrotu
     * (albo odwrotnie). Mail wychodzi dopiero po commicie — outbox nie może
     * obiecać czegoś, co za chwilę zostanie wycofane.
     */
    public function change(Order $order, OrderStatus $to, ?string $note = null, ?User $actor = null): bool
    {
        if (! $this->allows($order, $to)) {
            return false;
        }

        $event = DB::transaction(function () use ($order, $to, $note, $actor) {
            $event = $order->changeStatus($to, $note, $actor);

            if ($event !== null && $to === OrderStatus::Cancelled) {
                $this->returnStock($order);
            }

            return $event;
        });

        if ($event === null) {
            return false;
        }

        if ($to === OrderStatus::Cancelled) {
            $this->mailer->cancelled($order, $event);
        } else {
            $this->mailer->statusChanged($order, $event);
        }

        return true;
    }

    /**
     * Oddaje na stan wszystko, co zamówienie zdjęło przy składaniu — tylko dla
     * produktów pod kontrolą stanu. Wiersze produktów pod blokadą, bo równolegle
     * mógłby je ruszać koszyk albo inne zamówienie.
     *
     * Pozycje osierocone (produkt usunięty ze sklepu) pomijamy — nie ma czego
     * zwracać. Anulowanie jest terminalne, więc nie grozi to podwójnym zwrotem:
     * z „Anulowane" nie da się wyjść i wejść ponownie, a edycja pozycji jest na
     * anulowanym zablokowana.
     */
    private function returnStock(Order $order): void
    {
        $order->loadMissing('items');

        $productIds = $order->items->pluck('product_id')->filter()->all();

        if ($productIds === []) {
            return;
        }

        $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

        foreach ($order->items as $item) {
            $product = $products->get($item->product_id);

            if ($product === null || ! $product->tracksStock()) {
                continue;
            }

            $product->increment('stock', (float) $item->quantity);
        }
    }
}
