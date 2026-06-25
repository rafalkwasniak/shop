<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    /**
     * Formularz rejestracji sprzedawcy.
     */
    public function create(): Renderable|RedirectResponse
    {
        if ($user = Auth::user()) {
            return redirect()->route($user->role->homeRoute());
        }

        return view('auth.register');
    }

    /**
     * Rejestracja konta sprzedawcy, automatyczne zalogowanie i przejście
     * na pulpit (z onboardingiem). Rola 'seller' ustawiana domyślnie przez
     * bazę — admina zakłada tylko komenda shop:create-admin.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = new User;
        $user->fill($request->safe()->only('name', 'surname', 'email', 'password'));
        $user->role = UserRole::Seller; // role nie jest mass-assignable — ustawiamy jawnie
        $user->save();

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route($user->role->homeRoute());
    }
}
