<?php

namespace App\Models;

use App\Enums\MailPriority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Pojedynczy zakolejkowany mail wychodzący (wiersz outboxu). Wysyłka nigdy nie
 * dzieje się w locie — wołający wstawia wiersz, a komenda crona `email:dispatch`
 * go dostarcza. Wzorzec z kociaczek.com.pl + `shop_id`/`heading`/`preheader`.
 */
#[Fillable([
    'priority', 'shop_id', 'to_email', 'to_name', 'subject', 'preheader',
    'heading', 'greeting', 'intro_lines', 'action_text', 'action_url',
    'outro_lines', 'scheduled_at',
])]
class EmailMessage extends Model
{
    protected function casts(): array
    {
        return [
            'priority' => MailPriority::class,
            'intro_lines' => 'array',
            'outro_lines' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmailMessage $message): void {
            $message->priority ??= MailPriority::Mid;
            $message->scheduled_at ??= Carbon::now();
        });
    }

    /**
     * Maks. liczba prób przed trwałym niepowodzeniem. W configu, by było jedno
     * żywe źródło prawdy (nie zamrożone per wiersz).
     */
    public static function maxAttempts(): int
    {
        return (int) config('mail_outbox.max_attempts');
    }

    /**
     * Wiadomości gotowe do wysłania teraz — najwyższy priorytet i najstarsze pierwsze.
     *
     * @param  Builder<EmailMessage>  $query
     * @return Builder<EmailMessage>
     */
    public function scopeDueForSending(Builder $query): Builder
    {
        return $query
            ->whereNull('sent_at')
            ->whereNull('failed_at')
            ->where('scheduled_at', '<=', Carbon::now())
            ->where('attempts', '<', self::maxAttempts())
            ->orderByDesc('priority')
            ->orderBy('scheduled_at')
            ->orderBy('id');
    }

    /**
     * Odnotuj udane dostarczenie.
     */
    public function markSent(): void
    {
        $this->forceFill([
            'sent_at' => Carbon::now(),
            'last_error' => null,
        ])->save();
    }

    /**
     * Odnotuj nieudaną próbę. Po wyczerpaniu prób — trwałe niepowodzenie;
     * w przeciwnym razie backoff (przesuń następny termin w przyszłość), żeby
     * niestabilny SMTP nie był katowany przy każdym tiku crona.
     */
    public function markFailed(string $error): void
    {
        $this->attempts++;
        $this->last_error = $error;

        if ($this->attempts >= self::maxAttempts()) {
            $this->failed_at = Carbon::now();
        } else {
            $this->scheduled_at = Carbon::now()->addMinutes(
                (int) config('mail_outbox.retry_delay_minutes')
            );
        }

        $this->save();
    }
}
