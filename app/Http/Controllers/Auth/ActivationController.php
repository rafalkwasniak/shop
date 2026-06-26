<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ActivationRequest;
use App\Models\LegalDocument;
use App\Models\User;
use App\Services\ConsentRecorder;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Aktywacja konta: dokończenie rejestracji z linku mailowego. Formularz jest
 * wstępnie wypełniony danymi z rejestracji (edytowalnymi); użytkownik ustawia
 * hasło, potwierdza dane i zgody, po czym zostaje zalogowany. Token brokera
 * 'activation' (24 h) uwierzytelnia po pierwotnym adresie (token_email).
 */
class ActivationController extends Controller
{
    public function create(Request $request, string $token): Renderable
    {
        $email = (string) $request->query('email', '');
        $user = $email !== '' ? User::where('email', $email)->first() : null;

        return view('auth.activation', [
            'token' => $token,
            'tokenEmail' => $email,
            'user' => $user,
        ]);
    }

    public function store(ActivationRequest $request, ConsentRecorder $consents): RedirectResponse
    {
        $status = Password::broker('activation')->reset(
            [
                'email' => $request->input('token_email'),
                'password' => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $request->input('token'),
            ],
            function (User $user) use ($request) {
                $user->forceFill([
                    'name' => $request->string('name'),
                    'surname' => $request->string('surname'),
                    'email' => $request->string('email'),
                    'phone' => $request->input('phone'),
                    'password' => Hash::make($request->string('password')),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => 'Link aktywacyjny wygasł lub jest nieprawidłowy. Wyślij go ponownie z poziomu rejestracji.',
            ]);
        }

        /** @var User $user */
        $user = User::where('email', $request->string('email'))->firstOrFail();

        // Domknięcie zgód na aktualne wersje (idempotentne — istniejące zostają).
        $documents = collect(config('legal.required_types'))
            ->map(fn ($type) => LegalDocument::current($type))
            ->filter();
        $consents->record($user, $documents, $request->ip());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($user->role->homeRoute());
    }
}
