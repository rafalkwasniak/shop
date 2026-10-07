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
 *  - `cart` — koszyk za mały: po punktach musi zostać coś do zapłaty.
 */
final class LoyaltyRedemption
{
    public function __construct(
        public readonly int $balance,
        public readonly int $points,
        public readonly float $amount,
        public readonly ?string $reason = null,
        public readonly ?int $minimum = null,
    ) {}

    public function usable(): bool
    {
        return $this->points > 0;
    }
}
