<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ValidatesPassword;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Przyjęcie zaproszenia: pracownik ustawia WYŁĄCZNIE hasło.
 *
 * Imienia i nazwiska tu nie ma, choć formularz aktywacji sprzedawcy je zbiera.
 * Różnica jest celowa: sprzedawca aktywuje własne konto, a pracownikowi dane
 * wpisał pracodawca i to on odpowiada za to, kogo wpuszcza. Pole do zmiany
 * nazwiska w tym miejscu pozwalałoby podmienić tożsamość między zaproszeniem
 * a wejściem do panelu — a lista pracowników jest dla właściciela jedynym
 * miejscem, gdzie widzi, kto u niego pracuje. Własne dane pracownik zmieni
 * później w „Profilu", już zalogowany i widoczny pod tym samym kontem.
 */
class EmployeeInvitationRequest extends FormRequest
{
    use ValidatesPassword;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'token_email' => ['required', 'string', 'email'],
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['password' => 'hasło'];
    }
}
