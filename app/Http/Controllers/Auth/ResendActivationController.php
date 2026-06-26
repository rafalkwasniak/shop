<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendActivationRequest;
use App\Models\User;
use App\Services\ActivationMailer;
use Illuminate\Http\RedirectResponse;

/**
 * Ponowne wysłanie maila aktywacyjnego z ekranu „Sprawdź skrzynkę”. Adres bierze
 * z przesłanego pola (odporne na utratę sesji). Wysyła tylko dla kont
 * czekających na aktywację (email_verified_at = null). Komunikat neutralny —
 * nie zdradza, czy konto istnieje. Trasa jest throttlowana.
 */
class ResendActivationController extends Controller
{
    public function store(ResendActivationRequest $request, ActivationMailer $activation): RedirectResponse
    {
        $user = User::query()
            ->where('email', $request->string('email'))
            ->whereNull('email_verified_at')
            ->first();

        if ($user !== null) {
            $activation->send($user);
        }

        return back()->with('status', 'Jeśli konto czeka na aktywację, wysłaliśmy link ponownie.');
    }
}
