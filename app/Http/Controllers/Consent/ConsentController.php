<?php

namespace App\Http\Controllers\Consent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consent\ConsentRequest;
use App\Models\User;
use App\Services\ConsentRecorder;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Ponowna akceptacja dokumentów prawnych po zmianie ich wersji. Dostępna dla
 * zalogowanego użytkownika; lista bierze się z User::outstandingConsents().
 */
class ConsentController extends Controller
{
    public function show(Request $request): Renderable|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $outstanding = $user->outstandingConsents();

        if ($outstanding->isEmpty()) {
            return redirect()->route($user->role->homeRoute());
        }

        return view('consents.show', ['documents' => $outstanding]);
    }

    public function store(ConsentRequest $request, ConsentRecorder $consents): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $consents->record($user, $user->outstandingConsents(), $request->ip());

        return redirect()->intended(route($user->role->homeRoute()));
    }
}
