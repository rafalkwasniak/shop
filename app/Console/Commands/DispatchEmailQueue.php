<?php

namespace App\Console\Commands;

use App\Services\EmailOutbox;
use Illuminate\Console\Command;

/**
 * Opróżnia outbox maili. Przeznaczona do uruchamiania co minutę przez cron:
 * krótki proces jest bezpieczny na CloudLinux LVE, w odróżnieniu od długo
 * żyjącego demona kolejki. Każdy bieg wysyła do `mail_outbox.batch_size`
 * wiadomości gotowych do wysłania (throttle), najwyższy priorytet pierwszy,
 * ponawiając błędy aż do `max_attempts`. Sama wysyłka: App\Services\EmailOutbox.
 */
class DispatchEmailQueue extends Command
{
    protected $signature = 'email:dispatch';

    protected $description = 'Wyślij paczkę zaległych maili z kolejki outbox';

    public function handle(EmailOutbox $outbox): int
    {
        $result = $outbox->dispatch();

        // Pusty outbox albo zajęta blokada = cisza w dzienniku.
        if ($result === null || $result['sent'] + $result['failed'] === 0) {
            return self::SUCCESS;
        }

        // Ze znacznikiem czasu, bo jedynym odbiorcą tej linii jest plik, do którego
        // scheduler dopisuje wyjście komendy (patrz routes/console.php). Sam plik
        // dat nie nadaje, a linia bez godziny w dzienniku dopisywanym co minutę
        // nie pozwala powiązać awarii z niczym innym.
        $this->info(now()->format('Y-m-d H:i:s')." Outbox: {$result['sent']} wysłanych, {$result['failed']} nieudanych.");

        return self::SUCCESS;
    }
}
