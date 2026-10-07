<?php

namespace App\Console\Commands;

use App\Services\LoyaltyLedger;
use Illuminate\Console\Command;

/**
 * Codzienne wygaszanie przeterminowanych punktów za zakupy. Logika siedzi w
 * `LoyaltyLedger::expire()` — komenda jest tylko wejściem z crona, żeby dała
 * się też odpalić z ręki. Idempotentna: wygaszona porcja ma `remaining` 0.
 */
class ExpireLoyaltyPoints extends Command
{
    protected $signature = 'loyalty:expire';

    protected $description = 'Wygasza przeterminowane punkty za zakupy';

    public function handle(LoyaltyLedger $ledger): int
    {
        $this->info('Wygaszone punkty: '.$ledger->expire().'.');

        return self::SUCCESS;
    }
}
