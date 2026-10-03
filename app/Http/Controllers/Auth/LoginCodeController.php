<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginCodeRequest;
use App\Models\LoginChallenge;
use App\Models\User;
use App\Services\LoginChallenges;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Drugi krok logowania do centrali: kod z maila. Sesja trzyma tylko numer
 * oczekującej próby (AuthController::store) — konto loguje się dopiero tutaj.
 */
class LoginCodeController extends Controller
{
    public const SESSION_KEY = 'login.pending';

    public function create(Request $request): Renderable|RedirectResponse
    {
        if (! $challenge = $this->pending($request)) {
            return redirect()->route('login');
        }

        return view('auth.login-code', [
            'email' => $challenge->user->email,
            'resendIn' => $challenge->resendAvailableIn(),
        ]);
    }

    public function store(LoginCodeRequest $request, LoginChallenges $challenges): RedirectResponse
    {
        if (! $challenge = $this->pending($request)) {
            return $this->restart($request, 'Sesja logowania wygasła. Zaloguj się ponownie.');
        }

        if ($challenge->isExpired()) {
            return back()->withErrors(['code' => 'Kod wygasł. Wyślij nowy i wpisz go poniżej.']);
        }

        if (! $challenges->verify($challenge, $request->validated('code'))) {
            // Limit wyczerpany: z powrotem do hasła. Kolejne serie prób kosztują
            // atakującego znajomość hasła i trafiają w blokadę logowania.
            if ($challenge->attemptsExhausted()) {
                $challenges->complete($challenge);

                return $this->restart($request, 'Zbyt wiele błędnych kodów. Zaloguj się ponownie — wyślemy nowy kod.');
            }

            return back()->withErrors(['code' => 'Nieprawidłowy kod. Sprawdź go w mailu i spróbuj jeszcze raz.']);
        }

        $remember = (bool) $request->session()->get(self::SESSION_KEY.'.remember');
        $user = $challenge->user;

        $challenges->complete($challenge);
        $request->session()->forget(self::SESSION_KEY);

        return self::logIn($request, $user, $remember);
    }

    public function resend(Request $request, LoginChallenges $challenges): RedirectResponse
    {
        if (! $challenge = $this->pending($request)) {
            return $this->restart($request, 'Sesja logowania wygasła. Zaloguj się ponownie.');
        }

        if ($seconds = $challenge->resendAvailableIn()) {
            return back()->withErrors(['code' => "Nowy kod możesz wysłać za {$seconds} s."]);
        }

        $challenges->resend($challenge);

        return back()->with('status', 'Wysłaliśmy nowy kod. Poprzedni już nie działa.');
    }

    /**
     * Właściwe zalogowanie — wspólne dla ścieżki z kodem i bez (wyłącznik 2FA).
     */
    public static function logIn(Request $request, User $user, bool $remember): RedirectResponse
    {
        Auth::login($user, $remember);

        $request->session()->regenerate();

        return redirect()->intended(route($user->role->homeRoute()));
    }

    private function pending(Request $request): ?LoginChallenge
    {
        $id = $request->session()->get(self::SESSION_KEY.'.challenge');

        return $id ? LoginChallenge::with('user')->find($id) : null;
    }

    private function restart(Request $request, string $message): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
