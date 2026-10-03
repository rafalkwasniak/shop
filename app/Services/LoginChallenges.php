<?php

namespace App\Services;

use App\Models\LoginChallenge;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Drugi krok logowania do centrali: kod z maila (config `security.two_factor`).
 *
 * Kod istnieje w jawnej postaci tylko w chwili wystawienia — do maila albo na
 * ekran komendy awaryjnej. W bazie leży jego hash.
 */
class LoginChallenges
{
    public function __construct(private LoginCodeMailer $mailer) {}

    public static function enabled(): bool
    {
        return (bool) config('security.two_factor.enabled');
    }

    /**
     * Nowa próba logowania po poprawnym haśle. Wcześniejsze niedokończone
     * próby tego konta przepadają — ważny jest zawsze tylko ostatni kod.
     */
    public function start(User $user): LoginChallenge
    {
        LoginChallenge::where('user_id', $user->id)->delete();

        $challenge = new LoginChallenge(['user_id' => $user->id]);
        $this->mailer->send($user, $this->issue($challenge));

        return $challenge;
    }

    /**
     * „Wyślij nowy kod": stary przestaje działać, licznik prób od zera.
     */
    public function resend(LoginChallenge $challenge): void
    {
        $this->mailer->send($challenge->user, $this->issue($challenge));
    }

    /**
     * Sprawdza kod. Nieudana próba się liczy — po wyczerpaniu limitu kod jest
     * martwy nawet, gdy następna próba byłaby trafiona.
     */
    public function verify(LoginChallenge $challenge, string $code): bool
    {
        if ($challenge->isExpired() || $challenge->attemptsExhausted()) {
            return false;
        }

        if (Hash::check($code, $challenge->code_hash)) {
            return true;
        }

        $challenge->increment('attempts');

        return false;
    }

    /**
     * Kod dla komendy awaryjnej: ostatnia oczekująca próba konta dostaje nowy
     * kod, który zamiast do maila idzie na ekran. Null = nikt na to konto
     * akurat się nie loguje (najpierw trzeba podać hasło na stronie).
     */
    public function issueForConsole(User $user): ?string
    {
        $challenge = LoginChallenge::where('user_id', $user->id)->latest('id')->first();

        return $challenge ? $this->issue($challenge) : null;
    }

    public function complete(LoginChallenge $challenge): void
    {
        $challenge->delete();
    }

    private function issue(LoginChallenge $challenge): string
    {
        $length = (int) config('security.two_factor.code_length');
        $code = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);

        $challenge->fill([
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'sent_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addMinutes((int) config('security.two_factor.ttl_minutes')),
        ])->save();

        return $code;
    }
}
