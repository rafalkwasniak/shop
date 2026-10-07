<?php

namespace App\Services;

use App\Exceptions\OrderReturnException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Support\DiscountAllocation;
use Illuminate\Support\Facades\DB;

/**
 * Rejestracja zwrotu konsumenckiego (odstąpienie od umowy, 14 dni). Jedno
 * wejście dla formularza na tokenie: sprawdza, ile wolno oddać, wylicza kwotę
 * do zwrotu, dopisuje zgłoszenie i pomniejsza zamówienie — atomowo.
 *
 * DWIE ŻELAZNE ZASADY:
 *
 * 1. **Stanu magazynowego NIE RUSZAMY.** To nie jest anulowanie zamówienia:
 *    towar fizycznie dopiero jedzie do sprzedawcy i może wrócić niezdatny do
 *    sprzedaży. O tym, czy wraca na półkę, decyduje sprzedawca ręcznie.
 * 2. **Pieniądze idą z ręki.** v1 rejestruje zwrot i liczy kwotę; przelew,
 *    zwrot w Paynow i faktura korygująca zostają po stronie sprzedawcy.
 *
 * Terminu 14 dni ten serwis świadomie NIE pilnuje — bramka siedzi na wejściu
 * (publiczna strona zwrotu), bo sprzedawca może przyjąć zwrot po terminie z
 * dobrej woli, a serwis ma wtedy umieć go zapisać.
 */
class OrderReturnService
{
    public function __construct(private OrderTotals $totals) {}

    /**
     * Zapisuje zgłoszenie zwrotu i pomniejsza zamówienie.
     *
     * @param  array<int, float>  $quantities  mapa: id pozycji zamówienia → zwracana ilość
     * @param  array<string, string|null>  $declaration  dane z oświadczenia
     *                                                   (customer_name, customer_address, bank_account, note)
     *
     * @throws OrderReturnException
     */
    public function register(Order $order, array $quantities, array $declaration): OrderReturn
    {
        return DB::transaction(function () use ($order, $quantities, $declaration): OrderReturn {
            // Blokada wiersza na czas liczenia: dwa równoległe zgłoszenia z tego
            // samego linku nie mogą oddać tej samej sztuki dwa razy.
            $order = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $order->load('items.product');

            if ($order->status->isTerminal()) {
                throw new OrderReturnException('To zamówienie zostało anulowane — nie ma od czego odstąpić.');
            }

            $lines = $this->validatedLines($order, $quantities);

            if ($lines === []) {
                throw new OrderReturnException('Zaznacz, co i w jakiej ilości chcesz zwrócić.');
            }

            $refunds = $this->refundAmounts($order, $lines);
            $pointsShare = $this->pointsShare($order, $lines);

            $return = $order->returns()->create([
                'customer_name' => $declaration['customer_name'] ?? '',
                'customer_address' => $declaration['customer_address'] ?? '',
                'bank_account' => $declaration['bank_account'] ?? null,
                'note' => $declaration['note'] ?? null,
                'refund_gross' => round(array_sum($refunds), 2),
            ]);

            foreach ($lines as $itemId => $quantity) {
                $return->items()->create([
                    'order_item_id' => $itemId,
                    'quantity' => $quantity,
                    'refund_gross' => $refunds[$itemId],
                ]);

                $item = $order->items->firstWhere('id', $itemId);
                $item->returned_quantity = round((float) $item->returned_quantity + $quantity, 2);
                $item->save();
            }

            // Część zapłacona punktami za zwracane sztuki schodzi z zamówienia —
            // `OrderObserver` odda te punkty klientowi na konto (decyzja Rafała
            // 07.10). Pieniądze oddajemy wyłącznie za część zapłaconą pieniędzmi.
            if ($pointsShare > 0) {
                $order->points_discount = round((float) $order->points_discount - $pointsShare, 2);
            }

            // Sumy liczą się z ilości efektywnych, więc zamówienie kurczy się
            // samo — tą samą formułą, co przy edycji w panelu sprzedawcy.
            $this->totals->recalculate($order->load('items'));

            return $return->load('items');
        });
    }

    /**
     * Przepuszcza tylko linie, które wolno zwrócić, i normalizuje ilości do kroku
     * jednostki sprzedaży (0,5 kg / 1 szt.). Zera i pozycje spoza zamówienia
     * odpadają po cichu — to nie jest błąd, tylko niezaznaczona pozycja.
     *
     * @param  array<int, float>  $quantities
     * @return array<int, float>
     *
     * @throws OrderReturnException
     */
    private function validatedLines(Order $order, array $quantities): array
    {
        $lines = [];

        foreach ($quantities as $itemId => $quantity) {
            $item = $order->items->firstWhere('id', (int) $itemId);

            if ($item === null) {
                continue;
            }

            $quantity = $item->sale_unit->normalizeQuantity((float) $quantity);

            if ($quantity <= 0) {
                continue;
            }

            if (! $item->isWithdrawable()) {
                throw new OrderReturnException('„'.$item->name.'" nie podlega zwrotowi.');
            }

            if ($quantity > $item->returnableQuantity()) {
                throw new OrderReturnException(
                    'Z pozycji „'.$item->name.'" można zwrócić najwyżej '.$item->sale_unit->formatQuantity($item->returnableQuantity()).'.',
                );
            }

            $lines[(int) $item->id] = $quantity;
        }

        return $lines;
    }

    /**
     * Kwota do oddania za każdą zwracaną linię — liczona od ceny PO RABACIE, bo
     * tyle klient faktycznie zapłacił. Rabat (kod + punkty) rozbijamy tym samym
     * podziałem, co OrderTotals i faktura, więc trzy miejsca dają ten sam grosz.
     *
     * @param  array<int, float>  $lines
     * @return array<int, float>
     */
    private function refundAmounts(Order $order, array $lines): array
    {
        $lineGrossValues = $order->items
            ->mapWithKeys(fn (OrderItem $item): array => [$item->id => (float) $item->line_total_gross])
            ->all();

        $shares = DiscountAllocation::spread(round((float) $order->discount_amount + (float) $order->points_discount, 2), $lineGrossValues);

        $refunds = [];

        foreach ($lines as $itemId => $quantity) {
            $item = $order->items->firstWhere('id', $itemId);
            $effective = $item->effectiveQuantity();

            // Zwrot całej pozostałej ilości oddaje dokładnie wartość linii po
            // rabacie — bez dzielenia, żeby nie zgubić grosza na zaokrągleniu.
            $discountedLine = round($lineGrossValues[$itemId] - ($shares[$itemId] ?? 0.0), 2);

            $refunds[$itemId] = $quantity >= $effective
                ? $discountedLine
                : round($discountedLine / $effective * $quantity, 2);
        }

        return $refunds;
    }

    /**
     * Ile z zapłaconego punktami przypada na zwracane sztuki — ten sam podział
     * na linie co w OrderTotals, proporcjonalnie do zwracanej ilości.
     *
     * @param  array<int, float>  $lines
     */
    private function pointsShare(Order $order, array $lines): float
    {
        if ((float) $order->points_discount <= 0) {
            return 0.0;
        }

        $lineGrossValues = $order->items
            ->mapWithKeys(fn (OrderItem $item): array => [$item->id => (float) $item->line_total_gross])
            ->all();

        $shares = DiscountAllocation::spread((float) $order->points_discount, $lineGrossValues);
        $total = 0.0;

        foreach ($lines as $itemId => $quantity) {
            $item = $order->items->firstWhere('id', $itemId);
            $effective = $item->effectiveQuantity();
            $share = $shares[$itemId] ?? 0.0;

            $total += $quantity >= $effective ? $share : round($share / $effective * $quantity, 2);
        }

        return round($total, 2);
    }
}
