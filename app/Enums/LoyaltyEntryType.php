<?php

namespace App\Enums;

/**
 * Rodzaj wpisu w księdze punktów. Tylko `Earned` i `Adjustment` (dodatnia
 * korekta) tworzą porcję, z której da się wydawać — reszta to linie historii.
 */
enum LoyaltyEntryType: string
{
    case Earned = 'earned';
    case Spent = 'spent';
    case Restored = 'restored';
    case Clawback = 'clawback';
    case Expired = 'expired';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Earned => 'Za zakup',
            self::Spent => 'Wykorzystane',
            self::Restored => 'Zwrócone po anulowaniu',
            self::Clawback => 'Odebrane po zwrocie',
            self::Expired => 'Wygasłe',
            self::Adjustment => 'Korekta sklepu',
        };
    }
}
