<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;

/**
 * Reużywalne reguły i komunikaty dla hasła (min. 8 znaków, małe i duże litery,
 * cyfra). Wpinane przez `use` do Form Requestów, w których user ustawia hasło —
 * jedno źródło prawdy. Wzorzec: powtarzalne reguły jako traity w App\Http\Requests.
 */
trait ValidatesPassword
{
    /**
     * @return array<int, mixed>
     */
    protected function passwordRules(): array
    {
        return [
            'required',
            'confirmed',
            Password::min(8)->letters()->mixedCase()->numbers(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function passwordMessages(): array
    {
        return [
            'password.required' => 'Podaj hasło.',
            'password.confirmed' => 'Hasła muszą być takie same.',
            'password.min' => 'Hasło musi mieć co najmniej 8 znaków.',
            'password.letters' => 'Hasło musi zawierać litery.',
            'password.mixed_case' => 'Hasło musi zawierać małą i dużą literę.',
            'password.mixed' => 'Hasło musi zawierać małą i dużą literę.',
            'password.numbers' => 'Hasło musi zawierać cyfrę.',
        ];
    }
}
