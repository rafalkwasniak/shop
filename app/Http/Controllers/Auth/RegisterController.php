<?php

namespace App\Http\Controllers\Auth;

use App\Enums\LegalDocumentType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\LegalDocument;
use App\Models\User;
use App\Services\ActivationMailer;
use App\Services\ConsentRecorder;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
     * Rejestracja konta sprzedawcy. Konto powstaje BEZ użytecznego hasła
     * (losowe, niemożliwe do odgadnięcia) — sprzedawca dostaje mailem link do
     * ustawienia hasła. Zaznaczone zgody zapisujemy na aktualne wersje
     * dokumentów. Nie logujemy automatycznie (nie ma jeszcze hasła).
     */
    public function store(RegisterRequest $request, ConsentRecorder $consents, ActivationMailer $activation): RedirectResponse
    {
        $documents = collect(config('legal.required_types'))
            ->map(fn (LegalDocumentType $type) => LegalDocument::current($type))
            ->filter();

        $user = DB::transaction(function () use ($request, $consents, $documents) {
            $user = new User;
            $user->fill($request->safe()->only('name', 'surname', 'email'));
            $user->password = Str::password(32); // placeholder; właściwe hasło ustawi sprzedawca z linku
            $user->role = UserRole::Seller; // role nie jest mass-assignable — ustawiamy jawnie
            $user->save();

            $consents->record($user, $documents, $request->ip());

            return $user;
        });

        // Mail aktywacyjny (link do formularza aktywacji, 24 h) ląduje w kolejce — wyśle go cron.
        $activation->send($user);

        // Zapamiętany adres pozwala wysłać link ponownie z ekranu potwierdzenia (bez logowania).
        $request->session()->put('registered_email', $user->email);

        return redirect()->route('register.confirmation');
    }
}
