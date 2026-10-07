<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Rzucany przez `LoyaltyLedger`, gdy nie da się wydać punktów (za mało na
 * saldzie). Komunikat jest przeznaczony dla KLIENTA sklepu, więc pisany jego
 * językiem — bez żargonu.
 */
class LoyaltyException extends RuntimeException {}
