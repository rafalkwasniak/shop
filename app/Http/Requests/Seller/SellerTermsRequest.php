<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Kreator regulaminu sklepu — dane, które trafiają WYŁĄCZNIE do dokumentu.
 *
 * Świadomie NIE waliduje NIP-u sumą kontrolną ani telefonu tak jak ustawienia
 * sklepu: to nie są dane profilu, tylko treść, którą sprzedawca wpisuje do
 * własnego regulaminu i za którą sam odpowiada (§10 ust. 1 Regulaminu Kramio).
 * Sprawdzamy to, bez czego dokument byłby wadliwy — obecność i długość — a nie
 * zgodność ze stanem faktycznym. Kreator tworzy dokument, nie audytuje rzeczywistości.
 */
class SellerTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentShop() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'seller_name' => Str::squish((string) $this->input('seller_name')),
            'nip' => preg_replace('/\D+/', '', (string) $this->input('nip')) ?: null,
            'address' => Str::squish((string) $this->input('address')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'phone' => Str::squish((string) $this->input('phone')) ?: null,
            'return_address' => Str::squish((string) $this->input('return_address')) ?: null,
            'withdrawal_exclusions' => Str::squish((string) $this->input('withdrawal_exclusions')) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'seller_name' => ['required', 'string', 'max:150'],
            // Bez sumy kontrolnej: działalność nierejestrowana NIE MA NIP-u,
            // a pustka jest tu poprawną odpowiedzią, nie brakiem.
            'nip' => ['nullable', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:200'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'return_address' => ['nullable', 'string', 'max:200'],
            'shipping_days' => ['required', 'integer', 'min:1', 'max:60'],
            'withdrawal_exclusions' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'seller_name.required' => 'Podaj, kto prowadzi sklep — nazwę firmy albo imię i nazwisko.',
            'address.required' => 'Podaj adres. Klient musi wiedzieć, gdzie odesłać towar przy zwrocie.',
            'email.required' => 'Podaj adres e-mail — to jedyny kanał reklamacji i odstąpienia od umowy.',
            'email.email' => 'Ten adres e-mail wygląda na nieprawidłowy.',
            'shipping_days.required' => 'Podaj, w ile dni roboczych wysyłasz zamówienia.',
            'shipping_days.integer' => 'Podaj liczbę dni roboczych.',
            'shipping_days.max' => 'To bardzo długo — jeśli naprawdę tyle, opisz to raczej w treści regulaminu.',
        ];
    }
}
