<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
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
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route($request->user()->role->homeRoute()));
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
