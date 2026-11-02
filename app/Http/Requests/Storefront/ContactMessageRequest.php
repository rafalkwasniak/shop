<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Wiadomość z formularza kontaktowego storefrontu.
 *
 * Trzy pola i ani jednego więcej: każde dodatkowe obniża liczbę wysłanych
 * wiadomości, a RODO każe zbierać tylko to, co potrzebne do odpowiedzi.
 * Kto chce telefonu, wpisze numer w treści.
 *
 * Ochrona przed spamem NIE jest walidacją i nie mieszka tutaj — robot ma nie
 * dostać komunikatu błędu, tylko „wysłano" (patrz ContactFormGuard).
 */
class ContactMessageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Podaj imię.',
            'email.required' => 'Podaj adres e-mail — na niego przyjdzie odpowiedź.',
            'email.email' => 'To nie wygląda na adres e-mail.',
            'message.required' => 'Napisz, w czym możemy pomóc.',
            'message.min' => 'Wiadomość jest za krótka — opisz sprawę w kilku słowach.',
            'message.max' => 'Wiadomość jest za długa (maks. :max znaków).',
        ];
    }

    /**
     * Normalizacja przed walidacją: przycięte spacje, adres małymi literami,
     * imię w jednej linii (trafia do tematu maila).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) preg_replace('/\s+/u', ' ', (string) $this->input('name'))),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'message' => trim((string) $this->input('message')),
        ]);
    }
}
