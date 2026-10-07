<?php

namespace App\Console\Commands;

use App\Services\LoyaltyExpiryReminder;
use Illuminate\Console\Command;

/**
 * Codzienne przypomnienie o punktach, które wkrótce wygasną. Logika w
 * `LoyaltyExpiryReminder` — komenda jest tylko wejściem z crona, żeby dała
 * się też odpalić z ręki. Idempotentna: przypomniana porcja ma znacznik.
 */
class RemindExpiringLoyaltyPoints extends Command
{
    protected $signature = 'loyalty:remind';

    protected $description = 'Przypomina klientom o punktach, które wkrótce wygasną';

    public function handle(LoyaltyExpiryReminder $reminder): int
    {
        $this->info('Wysłane przypomnienia: '.$reminder->run().'.');

        return self::SUCCESS;
    }
}
