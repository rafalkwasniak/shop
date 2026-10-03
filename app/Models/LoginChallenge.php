<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Rozpoczęta próba logowania do centrali, która czeka na kod z maila.
 * Logika (wystawienie, sprawdzenie, ponowna wysyłka): App\Services\LoginChallenges.
 */
#[Fillable(['user_id', 'code_hash', 'attempts', 'sent_at', 'expires_at'])]
class LoginChallenge extends Model
{
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function attemptsExhausted(): bool
    {
        return $this->attempts >= (int) config('security.two_factor.max_attempts');
    }

    /**
     * Sekundy do chwili, gdy wolno poprosić o nowy kod (0 = już wolno).
     */
    public function resendAvailableIn(): int
    {
        $at = $this->sent_at->copy()->addSeconds((int) config('security.two_factor.resend_cooldown_seconds'));

        return max(0, (int) ceil(Carbon::now()->diffInSeconds($at, false)));
    }
}
