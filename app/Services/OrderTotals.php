<?php

namespace App\Services;

use App\Enums\VatRate;
use App\Models\Order;
use App\Support\DiscountAllocation;

/**
 * Jedno źródło przeliczania kwot zamówienia. Formuła jest „od brutto": netto i
 * VAT wyliczamy z ceny brutto i ułamka stawki, zaokrąglając per linia — dokładnie
 * tak, jak przy składaniu zamówienia. Dzięki temu zamówienie utworzone (z koszyka)
 * i zamówienie edytowane (panel sprzedawcy) liczą się identycznie.
 */
class OrderTotals
{
    /**
     * Przelicza pozycje i sumy zamówienia z bieżącej ceny/ilości pozycji i zapisuje.
     * Odświeża `line_total_gross` każdej pozycji (gdy drgnął) oraz `items_total`,
     * `total_net`, `total_vat`, `total_gross` zamówienia (brutto = produkty + dostawa).
     *
     * Liczymy z ilości EFEKTYWNEJ (kupiona minus zwrócona), więc zgłoszony zwrot
     * pomniejsza zamówienie tą samą drogą, co edycja pozycji — jedną formułą, bez
     * osobnego trybu. `quantity` zostaje nietknięte jako migawka zakupu.
     */
    public function recalculate(Order $order): void
    {
        $itemsTotal = 0.0;
        $lineGrossValues = [];

        foreach ($order->items as $index => $item) {
            [$lineGross] = $this->lineAmounts(
                (float) $item->unit_price_gross,
                $item->effectiveQuantity(),
                $item->vat_rate,
            );

            if ((float) $item->line_total_gross !== $lineGross) {
                $item->line_total_gross = $lineGross;
                $item->save();
            }

            $itemsTotal += $lineGross;
            $lineGrossValues[$index] = $lineGross;
        }

        // Rabat nigdy nie przekracza wartości produktów — inaczej edycja
        // zamówienia (usunięcie pozycji) zrobiłaby ujemne zamówienie albo
        // zjadła koszt wysyłki, którego rabat na produkty tykać nie może.
        $discount = min(round((float) $order->discount_amount, 2), round($itemsTotal, 2));

        // Punkty za zakupy płacą to, co zostało PO kodzie — i też nigdy więcej niż
        // produkty. Gdy edycja albo zwrot zmniejszą zamówienie poniżej kwoty
        // zapłaconej punktami, przycinamy ją, a `OrderObserver` oddaje różnicę
        // klientowi na konto (LoyaltyLedger::syncSpent).
        $points = min(round((float) $order->points_discount, 2), round($itemsTotal - $discount, 2));

        // Kod i punkty rozbijamy JEDNYM podziałem — dwa osobne zaokrąglenia
        // mogłyby rozjechać się o grosz z fakturą.
        $shares = DiscountAllocation::spread(round($discount + $points, 2), $lineGrossValues);

        $totalNet = 0.0;
        $totalVat = 0.0;

        foreach ($order->items as $index => $item) {
            // Rabat rozbity na pozycje PROPORCJONALNIE, żeby netto i VAT liczyły
            // się od kwoty faktycznie zapłaconej — przy dwóch stawkach w koszyku
            // odjęcie rabatu dopiero od sumy dałoby złą fakturę.
            $discounted = $lineGrossValues[$index] - ($shares[$index] ?? 0.0);
            $lineNet = round($discounted / (1 + $item->vat_rate->fraction()), 2);

            $totalNet += $lineNet;
            $totalVat += round($discounted - $lineNet, 2);
        }

        $order->update([
            // `items_total` to wartość produktów PRZED rabatem — zgadza się z sumą
            // pozycji na liście; rabat mieszka osobno w `discount_amount`.
            'items_total' => round($itemsTotal, 2),
            'discount_amount' => $discount,
            'points_discount' => $points,
            'total_net' => round($totalNet, 2),
            'total_vat' => round($totalVat, 2),
            'total_gross' => round($itemsTotal - $discount - $points + (float) $order->delivery_cost, 2),
        ]);
    }

    /**
     * Wartości pojedynczej linii z ceny brutto i ilości: [brutto, netto, VAT].
     * Zaokrąglenie per linia, brutto pierwsze (netto = brutto / (1 + stawka)).
     *
     * @return array{float, float, float}
     */
    public function lineAmounts(float $unitGross, float $quantity, VatRate $vat): array
    {
        $lineGross = round($unitGross * $quantity, 2);
        $lineNet = round($lineGross / (1 + $vat->fraction()), 2);
        $lineVat = round($lineGross - $lineNet, 2);

        return [$lineGross, $lineNet, $lineVat];
    }
}
