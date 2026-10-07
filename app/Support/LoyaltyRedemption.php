<?php

namespace App\Support;

/**
 * Ile punktów klient może wydać na DANY koszyk i ile to złotych — wynik
 * `LoyaltyLedger::redeemable()`. Ten sam obiekt widzą koszyk, kasa i składanie
 * zamówienia, więc wszystkie trzy pokażą i zapiszą tę samą kwotę.
 *
 * `reason` mówi, czemu punktów nie da się użyć (`points` = 0):
 *  - `empty` — brak punktów do wykorzystania (albo saldo ujemne),
 *  - `minimum` — mniej niż minimum ustawione przez sklep,
 *  - `cart` — koszyk za mały: po punktach musi zostać coś do zapłaty,
 *  - `below_minimum` — klient wpisał mniej, niż wynosi minimum sklepu.
 *
 * `maximum` = ile WOLNO wykorzystać w tym koszyku; `points` = ile pójdzie
 * naprawdę (przy własnej liczbie klienta mniejsza z obu). `requested` = co
 * klient wpisał (null = „wszystkie").
 */
final class LoyaltyRedemption
{
    public function __construct(
        public readonly int $balance,
        public readonly int $points,
        public readonly float $amount,
        public readonly ?string $reason = null,
        public readonly ?int $minimum = null,
        public readonly int $maximum = 0,
        public readonly ?int $requested = null,
    ) {}

    public function usable(): bool
    {
        return $this->points > 0;
    }

    /** Czy w tym koszyku w ogóle da się zapłacić punktami (choćby inną liczbą). */
    public function canRedeem(): bool
    {
        return $this->maximum > 0;
    }

    /** Czy liczbę wpisaną przez klienta przycięliśmy do maksimum. */
    public function capped(): bool
    {
        return $this->requested !== null && $this->requested > $this->maximum;
    }
}
