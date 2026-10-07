<?php

namespace App\Services;

use App\Enums\LoyaltyEntryType;
use App\Enums\OrderStatus;
use App\Exceptions\LoyaltyException;
use App\Models\LoyaltyEntry;
use App\Models\LoyaltyEntryUsage;
use App\Models\Order;
use App\Models\Shop;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Księga punktów za zakupy — jedyne miejsce, które nalicza, wydaje, oddaje,
 * odbiera i wygasza punkty. Kluczem jest (sklep, e-mail), nie konto klienta.
 *
 * Dwie kolumny, dwa znaczenia: `points` to linia historii (to widzi klient),
 * `remaining` to stan porcji. Saldo liczymy WYŁĄCZNIE z `remaining`, więc
 * każda operacja musi przesunąć `remaining` w porcjach i dopisać linię historii
 * w jednej transakcji. Ujemne `remaining` to niepokryty dług po zwrocie towaru.
 *
 * Kolejność wydawania: najpierw porcje, które najwcześniej wygasną (bezterminowe
 * na końcu), przy równym terminie starsze — klient nie traci punktów przez to,
 * że system zjadł mu świeże zamiast starych.
 *
 * Kwoty liczymy w groszach na liczbach całkowitych — punkty to liczby całkowite
 * i zaokrąglenie zmiennoprzecinkowe nie może nikomu zabrać punktu.
 */
class LoyaltyLedger
{
    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    /**
     * Kwota, od której naliczamy punkty: wartość produktów faktycznie zapłacona
     * (po rabacie), bez dostawy. `items_total` liczy się z ilości efektywnej,
     * więc po zwrocie ta kwota sama maleje.
     */
    public function baseAmount(Order $order): float
    {
        return max(0.0, round((float) $order->items_total - (float) $order->discount_amount, 2));
    }

    /** Ile punktów należy się za zamówienie przy bieżących ustawieniach sklepu. */
    public function pointsFor(Order $order): int
    {
        return $this->pointsFromBase($order->shop, $this->baseAmount($order));
    }

    /** Ile punktów da produkt (lub dowolna kwota) — do informacji na karcie produktu. */
    public function pointsFromBase(Shop $shop, float $base): int
    {
        // Procent w setnych częściach (3,50% → 350), wartość punktu w groszach.
        $percent = (int) round((float) $shop->loyalty_earn_percent * 100);
        $value = (int) round($shop->loyaltyPointValue() * 100);

        if ($percent <= 0 || $value <= 0 || $base <= 0) {
            return 0;
        }

        return intdiv((int) round($base * 100) * $percent, 10000 * $value);
    }

    /**
     * Nalicza porcję za zrealizowane zamówienie. Idempotentne: drugie wywołanie
     * zwraca istniejącą porcję. Punkty stają się dostępne po karencji sklepu,
     * a ważność liczy się od dostępności — karencja nie zjada okresu ważności.
     */
    public function award(Order $order, ?CarbonInterface $at = null): ?LoyaltyEntry
    {
        $shop = $order->shop;

        if (! $shop->loyaltyActive() || $order->status === OrderStatus::Cancelled) {
            return null;
        }

        return DB::transaction(function () use ($order, $shop, $at) {
            $existing = $this->orderEntries($order, LoyaltyEntryType::Earned)->lockForUpdate()->first();

            if ($existing !== null) {
                return $existing;
            }

            $base = $this->baseAmount($order);
            $points = $this->pointsFromBase($shop, $base);

            if ($points <= 0) {
                return null;
            }

            $availableAt = ($at ?? now())->copy()->addDays($shop->loyaltyDelayDays());

            return LoyaltyEntry::create([
                'shop_id' => $shop->id,
                'email' => self::normalizeEmail($order->buyer_email),
                'order_id' => $order->id,
                'type' => LoyaltyEntryType::Earned,
                'points' => $points,
                'remaining' => $points,
                'point_value' => $shop->loyaltyPointValue(),
                'base_amount' => $base,
                'available_at' => $availableAt,
                'expires_at' => $this->expiryFrom($shop, $availableAt),
            ]);
        });
    }

    /**
     * Dopasowuje punkty za zamówienie do jego bieżącego stanu — po zwrocie
     * (zmalała kwota) albo anulowaniu (zero). Odbiera proporcjonalnie do
     * spadku kwoty naliczenia, nigdy nie dodaje. Najpierw z porcji tego
     * zamówienia (także jeszcze oczekującej), potem z pozostałych dostępnych;
     * czego nie ma z czego zdjąć, zostaje długiem. Zwraca liczbę odebranych.
     */
    public function reconcile(Order $order): int
    {
        return DB::transaction(function () use ($order) {
            $earned = $this->orderEntries($order, LoyaltyEntryType::Earned)->lockForUpdate()->first();

            if ($earned === null) {
                return 0;
            }

            // Liczymy w groszach, nie w punktach: między naliczeniem a zwrotem
            // sprzedawca mógł zmienić wartość punktu i przeliczyć salda, a
            // historia trzyma liczby w jednostkach ze swojego dnia.
            $earnedValue = $earned->points * $this->grosze($earned->point_value);
            $targetValue = 0;
            $baseThen = (int) round((float) $earned->base_amount * 100);

            if ($order->status !== OrderStatus::Cancelled && $baseThen > 0) {
                $baseNow = min($baseThen, (int) round($this->baseAmount($order) * 100));
                $targetValue = intdiv($earnedValue * $baseNow, $baseThen);
            }

            $takenValue = $this->orderEntries($order, LoyaltyEntryType::Clawback)->get()
                ->sum(fn (LoyaltyEntry $entry) => -$entry->points * $this->grosze($entry->point_value));

            $currentValue = $this->grosze($order->shop->loyaltyPointValue());
            // W dół: ułamek punktu zostaje u klienta.
            $due = intdiv(max(0, $earnedValue - $takenValue - $targetValue), $currentValue);

            if ($due <= 0) {
                return 0;
            }

            $uncovered = $this->consume($earned->shop_id, $earned->email, $due, preferLot: $earned);

            LoyaltyEntry::create([
                'shop_id' => $earned->shop_id,
                'email' => $earned->email,
                'order_id' => $order->id,
                'type' => LoyaltyEntryType::Clawback,
                'points' => -$due,
                'remaining' => -$uncovered,
                'point_value' => $order->shop->loyaltyPointValue(),
            ]);

            return $due;
        });
    }

    /**
     * Wydaje punkty na zamówienie. Najpierw spłaca ewentualny dług z dostępnych
     * porcji, potem sprawdza saldo — klient z długiem nie wyda punktów, które
     * w rzeczywistości są już „zajęte".
     */
    public function spend(Order $order, string $email, int $points): LoyaltyEntry
    {
        if ($points <= 0) {
            throw new InvalidArgumentException('Liczba punktów do wydania musi być dodatnia.');
        }

        $email = self::normalizeEmail($email);

        return DB::transaction(function () use ($order, $email, $points) {
            $this->settleDebts($order->shop_id, $email);

            if ($this->balance($order->shop_id, $email) < $points) {
                throw new LoyaltyException('Masz za mało punktów, żeby z nich teraz skorzystać.');
            }

            $entry = LoyaltyEntry::create([
                'shop_id' => $order->shop_id,
                'email' => $email,
                'order_id' => $order->id,
                'type' => LoyaltyEntryType::Spent,
                'points' => -$points,
                'remaining' => 0,
                'point_value' => $order->shop->loyaltyPointValue(),
            ]);

            $this->consume($order->shop_id, $email, $points, spend: $entry);

            return $entry;
        });
    }

    /**
     * Oddaje punkty wydane na anulowane zamówienie — do tych samych porcji, z ich
     * terminem ważności. Porcja, która w międzyczasie wygasła, nie wraca (punkty
     * i tak by przepadły). Idempotentne: zapis wykorzystań kasujemy po oddaniu.
     */
    public function restore(Order $order): int
    {
        return DB::transaction(function () use ($order) {
            $spent = $this->orderEntries($order, LoyaltyEntryType::Spent)->with('usages')->lockForUpdate()->get();
            $restored = 0;

            foreach ($spent as $entry) {
                foreach ($entry->usages as $usage) {
                    $lot = LoyaltyEntry::query()->lockForUpdate()->find($usage->lot_id);

                    if ($lot !== null && ($lot->expires_at === null || $lot->expires_at->isFuture())) {
                        $lot->increment('remaining', $usage->points);
                        $restored += $usage->points;
                    }

                    $usage->delete();
                }
            }

            if ($restored > 0) {
                LoyaltyEntry::create([
                    'shop_id' => $order->shop_id,
                    'email' => $spent->first()->email,
                    'order_id' => $order->id,
                    'type' => LoyaltyEntryType::Restored,
                    'points' => $restored,
                    'remaining' => 0,
                    'point_value' => $order->shop->loyaltyPointValue(),
                ]);
            }

            return $restored;
        });
    }

    /**
     * Ręczna korekta sprzedawcy. Dodatnia tworzy porcję dostępną od razu
     * (z ważnością sklepu), ujemna odbiera jak zwrot — w razie braku punktów
     * zostaje dług.
     */
    public function adjust(Shop $shop, string $email, int $points, ?string $note = null): LoyaltyEntry
    {
        if ($points === 0) {
            throw new InvalidArgumentException('Korekta musi zmieniać saldo.');
        }

        $email = self::normalizeEmail($email);

        return DB::transaction(function () use ($shop, $email, $points, $note) {
            if ($points > 0) {
                $now = now();

                return LoyaltyEntry::create([
                    'shop_id' => $shop->id,
                    'email' => $email,
                    'type' => LoyaltyEntryType::Adjustment,
                    'points' => $points,
                    'remaining' => $points,
                    'point_value' => $shop->loyaltyPointValue(),
                    'available_at' => $now,
                    'expires_at' => $this->expiryFrom($shop, $now),
                    'note' => $note,
                ]);
            }

            $uncovered = $this->consume($shop->id, $email, -$points);

            return LoyaltyEntry::create([
                'shop_id' => $shop->id,
                'email' => $email,
                'type' => LoyaltyEntryType::Adjustment,
                'points' => $points,
                'remaining' => -$uncovered,
                'point_value' => $shop->loyaltyPointValue(),
                'note' => $note,
            ]);
        });
    }

    /**
     * Punkty do wydania teraz: dostępne, niewygasłe porcje minus niepokryty dług.
     * Może być ujemne.
     */
    public function balance(Shop|int $shop, string $email): int
    {
        $available = (int) $this->spendable($shop, $email)->sum('remaining');
        $debt = (int) $this->entries($shop, $email)->where('remaining', '<', 0)->sum('remaining');

        return $available + $debt;
    }

    /** Czy którykolwiek klient sklepu ma punkty albo dług — wtedy zmiana wartości punktu przelicza salda. */
    public function hasOutstanding(Shop $shop): bool
    {
        return LoyaltyEntry::query()->where('shop_id', $shop->id)->where('remaining', '!=', 0)->exists();
    }

    /** Punkty naliczone, ale jeszcze w karencji. */
    public function pending(Shop|int $shop, string $email): int
    {
        return (int) $this->entries($shop, $email)
            ->where('remaining', '>', 0)
            ->where('available_at', '>', now())
            ->sum('remaining');
    }

    /**
     * Wygasza przeterminowane porcje (do wywołania z crona). Zanim punkty
     * przepadną, spłacają dług tego klienta — lepiej, żeby pokryły zwrot,
     * niż zniknęły. Zwraca liczbę wygaszonych punktów.
     */
    public function expire(): int
    {
        $owners = LoyaltyEntry::query()
            ->where('remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->select('shop_id', 'email')
            ->distinct()
            ->get();

        $total = 0;

        foreach ($owners as $owner) {
            $shop = Shop::findOrFail($owner->shop_id);

            $total += DB::transaction(function () use ($owner, $shop) {
                $lots = $this->entries($owner->shop_id, $owner->email)
                    ->where('remaining', '>', 0)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now())
                    ->orderBy('expires_at')->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $this->payDebts($owner->shop_id, $owner->email, $lots);
                $expired = 0;

                foreach ($lots as $lot) {
                    if ($lot->remaining <= 0) {
                        continue;
                    }

                    LoyaltyEntry::create([
                        'shop_id' => $lot->shop_id,
                        'email' => $lot->email,
                        'order_id' => $lot->order_id,
                        'type' => LoyaltyEntryType::Expired,
                        'points' => -$lot->remaining,
                        'remaining' => 0,
                        'point_value' => $shop->loyaltyPointValue(),
                    ]);

                    $expired += $lot->remaining;
                    $lot->update(['remaining' => 0]);
                }

                return $expired;
            });
        }

        return $total;
    }

    /**
     * Zmienia wartość punktu sklepu i przelicza salda klientów z zachowaniem
     * wartości w złotych: 300 pkt × 1 gr → 30 pkt × 10 gr. Przeliczamy każdą
     * porcję z resztą (także oczekującą), dług i zapis wykorzystań (żeby
     * anulowanie oddało właściwą liczbę). Reszty zaokrąglamy na korzyść
     * klienta: porcje w górę, dług w stronę zera. Każdy klient, któremu
     * zmieniło się saldo, dostaje w historii wpis „Przeliczenie".
     *
     * Zwraca liczbę przeliczonych klientów.
     */
    public function revalue(Shop $shop, float $newValue): int
    {
        $new = $this->grosze($newValue);

        if ($new < $this->grosze(config('loyalty.point_value_min')) || $new > $this->grosze(config('loyalty.point_value_max'))) {
            throw new InvalidArgumentException('Niedozwolona wartość punktu.');
        }

        $old = $this->grosze($shop->loyaltyPointValue());

        if ($new === $old) {
            return 0;
        }

        return DB::transaction(function () use ($shop, $old, $new, $newValue) {
            $entries = LoyaltyEntry::query()
                ->where('shop_id', $shop->id)
                ->where('remaining', '!=', 0)
                ->lockForUpdate()
                ->get();

            $before = [];
            $after = [];

            foreach ($entries as $entry) {
                $converted = $entry->remaining > 0
                    ? intdiv($entry->remaining * $old + $new - 1, $new)
                    : -intdiv(-$entry->remaining * $old, $new);

                $before[$entry->email] = ($before[$entry->email] ?? 0) + $entry->remaining;
                $after[$entry->email] = ($after[$entry->email] ?? 0) + $converted;

                $entry->update(['remaining' => $converted]);
            }

            LoyaltyEntryUsage::query()
                ->whereIn('entry_id', LoyaltyEntry::query()->where('shop_id', $shop->id)->select('id'))
                ->lockForUpdate()
                ->get()
                ->each(fn (LoyaltyEntryUsage $usage) => $usage->update([
                    'points' => intdiv($usage->points * $old + $new - 1, $new),
                ]));

            $note = sprintf(
                'Nowa wartość punktu: %s zł zamiast %s zł',
                number_format($new / 100, 2, ',', ''),
                number_format($old / 100, 2, ',', ''),
            );
            $changed = 0;

            foreach ($before as $email => $total) {
                if ($after[$email] === $total) {
                    continue;
                }

                LoyaltyEntry::create([
                    'shop_id' => $shop->id,
                    'email' => $email,
                    'type' => LoyaltyEntryType::Revaluation,
                    'points' => $after[$email] - $total,
                    'remaining' => 0,
                    'point_value' => $newValue,
                    'note' => $note,
                ]);

                $changed++;
            }

            $shop->update(['loyalty_point_value' => $newValue]);

            return $changed;
        });
    }

    /** Spłaca dług klienta z dostępnych porcji. */
    private function settleDebts(int $shopId, string $email): void
    {
        $this->payDebts($shopId, $email, $this->spendable($shopId, $email)->lockForUpdate()->get());
    }

    /**
     * Pokrywa dług (wpisy z ujemnym `remaining`, od najstarszego) z podanych
     * porcji, w ich kolejności.
     *
     * @param  Collection<int, LoyaltyEntry>  $lots
     */
    private function payDebts(int $shopId, string $email, Collection $lots): void
    {
        $debts = $this->entries($shopId, $email)
            ->where('remaining', '<', 0)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($debts as $debt) {
            foreach ($lots as $lot) {
                if ($debt->remaining >= 0) {
                    break;
                }

                $pay = min($lot->remaining, -$debt->remaining);

                if ($pay <= 0) {
                    continue;
                }

                $lot->update(['remaining' => $lot->remaining - $pay]);
                $debt->update(['remaining' => $debt->remaining + $pay]);
            }
        }
    }

    /**
     * Zdejmuje punkty z porcji: najpierw z `preferLot` (porcja odbieranego
     * zamówienia, nawet oczekująca), potem z dostępnych w kolejności wygasania.
     * Przy wydaniu zapisuje, z której porcji ile zeszło. Zwraca, czego nie
     * udało się pokryć.
     */
    private function consume(int $shopId, string $email, int $points, ?LoyaltyEntry $spend = null, ?LoyaltyEntry $preferLot = null): int
    {
        $lots = $this->spendable($shopId, $email)->lockForUpdate()->get();

        if ($preferLot !== null) {
            $lots = $lots->reject(fn (LoyaltyEntry $lot) => $lot->id === $preferLot->id)
                ->prepend($preferLot->refresh());
        }

        $left = $points;

        foreach ($lots as $lot) {
            if ($left <= 0) {
                break;
            }

            $take = min($lot->remaining, $left);

            if ($take <= 0) {
                continue;
            }

            $lot->update(['remaining' => $lot->remaining - $take]);
            $left -= $take;

            $spend?->usages()->create(['lot_id' => $lot->id, 'points' => $take]);
        }

        return $left;
    }

    /** @return Builder<LoyaltyEntry> */
    private function entries(Shop|int $shop, string $email): Builder
    {
        return LoyaltyEntry::query()
            ->where('shop_id', $shop instanceof Shop ? $shop->id : $shop)
            ->where('email', self::normalizeEmail($email));
    }

    /**
     * Porcje do wydania teraz, w kolejności wydawania.
     *
     * @return Builder<LoyaltyEntry>
     */
    private function spendable(Shop|int $shop, string $email): Builder
    {
        return $this->entries($shop, $email)
            ->where('remaining', '>', 0)
            ->where('available_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at')
            ->orderBy('id');
    }

    /** @return Builder<LoyaltyEntry> */
    private function orderEntries(Order $order, LoyaltyEntryType $type): Builder
    {
        return LoyaltyEntry::query()->where('order_id', $order->id)->where('type', $type);
    }

    /** Wartość punktu w groszach; wpis sprzed zapisywania wartości liczy się po 1 gr. */
    private function grosze(mixed $value): int
    {
        return (int) round((float) ($value ?? 0.01) * 100);
    }

    private function expiryFrom(Shop $shop, CarbonInterface $from): ?CarbonInterface
    {
        $months = $shop->loyalty_validity_months;

        return $months ? $from->copy()->addMonthsNoOverflow($months)->endOfDay() : null;
    }
}
