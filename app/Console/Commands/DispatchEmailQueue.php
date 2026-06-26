<?php

namespace App\Console\Commands;

use App\Mail\OutboxMailable;
use App\Models\EmailMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Opróżnia outbox maili. Przeznaczona do uruchamiania co minutę przez cron:
 * krótki proces jest bezpieczny na CloudLinux LVE, w odróżnieniu od długo
 * żyjącego demona kolejki. Każdy bieg wysyła do `mail_outbox.batch_size`
 * wiadomości gotowych do wysłania (throttle), najwyższy priorytet pierwszy,
 * ponawiając błędy aż do `max_attempts`.
 */
class DispatchEmailQueue extends Command
{
    protected $signature = 'email:dispatch';

    protected $description = 'Wyślij paczkę zaległych maili z kolejki outbox';

    public function handle(): int
    {
        $batchSize = (int) config('mail_outbox.batch_size');

        $messages = EmailMessage::query()
            ->dueForSending()
            ->limit($batchSize)
            ->get();

        if ($messages->isEmpty()) {
            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        foreach ($messages as $message) {
            try {
                Mail::to($message->to_email, $message->to_name)
                    ->send(new OutboxMailable($message));

                $message->markSent();
                $sent++;
            } catch (Throwable $e) {
                $message->markFailed($e->getMessage());
                $failed++;
            }
        }

        $this->info("Outbox: {$sent} wysłanych, {$failed} nieudanych.");

        return self::SUCCESS;
    }
}
