<?php

namespace App\Services;

use App\Mail\OutboxMailable;
use App\Models\EmailMessage;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Opróżnianie outboxu maili — JEDNO miejsce dla obu wołających: komendy crona
 * `email:dispatch` i natychmiastowej wysyłki kodu logowania (LoginCodeMailer).
 *
 * Dlaczego usługa, a nie `Artisan::call('email:dispatch')` z żądania WWW:
 * Artisan ładuje całą konsolę razem z routes/console.php, a harmonogram przy
 * budowie szuka binarki PHP przez `exec()`. Sandbox hostingu blokuje `exec()`
 * na stronie (nie w CLI), więc 03.10 każde logowanie kończyło się alertem,
 * a kod czekał na cron. Testy tego nie widzą — w CLI `exec()` działa.
 */
class EmailOutbox
{
    /**
     * Wysyła paczkę zaległych maili (najwyższy priorytet pierwszy).
     *
     * Wspólna blokada dla wszystkich biegów: pobieramy wiersze bez rezerwacji,
     * więc dwa równoległe biegi wysłałyby ten sam mail dwa razy.
     * `withoutOverlapping` crona tu nie pomaga — pilnuje tylko biegów z crona.
     * Kto nie doczeka się blokady, kończy bez szkody: wiersz zabierze trwający
     * albo następny bieg.
     *
     * @return array{sent: int, failed: int}|null null = inny bieg trzymał blokadę
     */
    public function dispatch(): ?array
    {
        $lock = Cache::lock('email-dispatch', 120);

        try {
            $lock->block((int) config('mail_outbox.lock_wait_seconds'));
        } catch (LockTimeoutException) {
            return null;
        }

        try {
            return $this->sendBatch();
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{sent: int, failed: int}
     */
    private function sendBatch(): array
    {
        $messages = EmailMessage::query()
            ->dueForSending()
            ->limit((int) config('mail_outbox.batch_size'))
            ->get();

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

        return ['sent' => $sent, 'failed' => $failed];
    }
}
