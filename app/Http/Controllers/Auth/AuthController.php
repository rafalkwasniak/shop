<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\LoginChallenges;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Formularz logowania (wspólny dla admina i sprzedawcy).
     */
    public function create(): Renderable|RedirectResponse
    {
        if ($user = Auth::user()) {
            return redirect()->route($user->role->homeRoute());
        }

        return view('auth.login');
    }

    /**
     * Uwierzytelnienie i przekierowanie na pulpit zależny od roli.
     */
    public function store(LoginRequest $request, LoginChallenges $challenges): RedirectResponse
    {
        $user = $request->authenticatedUser();
        $remember = $request->boolean('remember');

        if (! LoginChallenges::enabled()) {
            return LoginCodeController::logIn($request, $user, $remember);
        }

        // Hasło się zgadza, ale konto jeszcze nie jest zalogowane: sesja wie
        // tylko, na którą próbę czeka ekran kodu.
        $challenge = $challenges->start($user);

        $request->session()->put(LoginCodeController::SESSION_KEY, [
            'challenge' => $challenge->id,
            'remember' => $remember,
        ]);

        return redirect()->route('login.code');
    }

    /**
     * Wylogowanie i unieważnienie sesji.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
