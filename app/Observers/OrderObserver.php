<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\LoyaltyLedger;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Punkty za zakupy idą za zamówieniem. Obserwator zamiast wpinania się w każdą
 * ścieżkę z osobna: status zmienia wyłącznie `Order::changeStatus()`, a kwoty
 * (zwrot, edycja pozycji) wyłącznie `OrderTotals::recalculate()` — obie drogi
 * kończą się zapisem zamówienia, więc jedno miejsce łapie też ścieżki przyszłe.
 *
 *  - „Zrealizowane" → naliczenie porcji (tylko sklep z włączonymi punktami);
 *  - „Anulowane" → oddanie wydanych punktów i odebranie przyznanych;
 *  - spadek kwoty produktów (zwrot, edycja) → proporcjonalne odebranie.
 *
 * Oddawanie i odbieranie działa także w sklepie, który punkty WYŁĄCZYŁ: punkty
 * już przyznane klientom muszą dalej reagować na anulowanie i zwrot.
 *
 * Co się zmieniło, odczytujemy OD RAZU (przy następnym zapisie `wasChanged`
 * pokazywałby już co innego), a samą księgę ruszamy dopiero po commicie —
 * wycofana zmiana statusu nie może zostawić naliczonych punktów. Błąd punktów
 * NIGDY nie blokuje zamówienia: trafia do logu i na Discorda, a status czy
 * zwrot zostają zapisane.
 */
class OrderObserver
{
    public function __construct(private LoyaltyLedger $ledger) {}

    public function updated(Order $order): void
    {
        $status = $order->wasChanged('status') ? $order->status : null;
        $amountsChanged = $order->wasChanged(['items_total', 'discount_amount']);

        if ($status === OrderStatus::Completed) {
            $this->afterCommit(fn () => $this->ledger->award($order));
        } elseif ($status === OrderStatus::Cancelled) {
            $this->afterCommit(function () use ($order): void {
                $this->ledger->restore($order);
                $this->ledger->reconcile($order);
            });
        } elseif ($amountsChanged) {
            $this->afterCommit(fn () => $this->ledger->reconcile($order));
        }
    }

    private function afterCommit(callable $callback): void
    {
        DB::afterCommit(function () use ($callback): void {
            try {
                $callback();
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
