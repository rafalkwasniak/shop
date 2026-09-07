<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmployeeInvitationRequest;
use App\Models\ShopEmployee;
use App\Models\User;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Przyjęcie zaproszenia do pracy w sklepie: ustawienie własnego hasła z linku
 * mailowego. Token brokera `invitation` (7 dni) uwierzytelnia po adresie, na
 * który zaproszenie wystawiono.
 *
 * Osobno od `ActivationController`, mimo podobnego mechanizmu: tamten formularz
 * zbiera dane firmowe i zgody prawne sprzedawcy, których pracownik nie ma prawa
 * składać — stroną umowy z Kramio jest jego pracodawca.
 */
class EmployeeInvitationController extends Controller
{
    public function create(Request $request, string $token): Renderable
    {
        $email = (string) $request->query('email', '');
        $user = $email !== '' ? User::where('email', $email)->first() : null;

        return view('auth.employee-invitation', [
            'token' => $token,
            'tokenEmail' => $email,
            'user' => $user,
            'employment' => $user !== null ? $this->pendingEmployment($user) : null,
        ]);
    }

    public function store(EmployeeInvitationRequest $request): RedirectResponse
    {
        $user = User::where('email', $request->string('token_email'))->first();
        $employment = $user !== null ? $this->pendingEmployment($user) : null;

        // Zaproszenie wycofane, zanim ktoś zdążył kliknąć. Link zostaje ważny po
        // stronie brokera (token żyje 7 dni niezależnie od nas), więc bez tego
        // sprawdzenia odebranie dostępu dałoby się obejść, klikając stary mail.
        if ($employment === null) {
            throw ValidationException::withMessages([
                'token' => 'To zaproszenie nie jest już aktualne. Poproś swojego pracodawcę o nowe.',
            ]);
        }

        $status = Password::broker('invitation')->reset(
            [
                'email' => $request->input('token_email'),
                'password' => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $request->input('token'),
            ],
            function (User $user) use ($request): void {
                $user->forceFill([
                    'password' => Hash::make($request->string('password')),
                    // Znacznik „konto aktywowane" — ten sam co u sprzedawcy.
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'token' => 'Link wygasł lub jest nieprawidłowy. Poproś swojego pracodawcę o ponowne zaproszenie.',
            ]);
        }

        // DOPIERO TERAZ zatrudnienie staje się czynne. Do tej chwili wiersz
        // istniał, ale `isActive()` był fałszem, więc konto nie otwierało niczego
        // — konto bez ustawionego hasła jest tylko wieszakiem na link.
        $employment->update(['accepted_at' => now()]);

        Auth::login($employment->user->fresh());
        $request->session()->regenerate();

        return redirect()->route('seller.dashboard')
            ->with('status', 'Witamy w panelu sklepu '.$employment->shop->name.'.');
    }

    /**
     * Zaproszenie czekające na przyjęcie: nieprzyjęte i niewycofane.
     *
     * Przyjęte świadomie NIE wraca — inaczej ten sam link działałby jako
     * „ustaw nowe hasło" dla każdego, kto ma dostęp do starej skrzynki. Do
     * zmiany hasła służy odzyskiwanie hasła, z własnym, krótszym tokenem.
     */
    private function pendingEmployment(User $user): ?ShopEmployee
    {
        return $user->employments()
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->latest('id')
            ->first();
    }
}
