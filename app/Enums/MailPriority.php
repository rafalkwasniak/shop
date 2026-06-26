<?php

namespace App\Enums;

/**
 * Priorytet dostarczenia z outboxu. Wyższa wartość wysyłana pierwsza — świeży
 * mail aktywacyjny (High) wyprzedza zalegający newsletter (Low).
 */
enum MailPriority: int
{
    case Low = 1;
    case Mid = 2;
    case High = 3;

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Niski',
            self::Mid => 'Normalny',
            self::High => 'Wysoki',
        };
    }
}
