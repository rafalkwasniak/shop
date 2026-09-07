<?php

namespace App\Http\Requests\Seller;

use App\Enums\PanelSection;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Zaproszenie pracownika do sklepu.
 *
 * Uprawnienia przychodzą jako lista kluczy działów — walidowana wobec enuma, bo
 * `permissions` trafia do bazy jako JSON i nikt jej później nie sprawdza.
 */
class EmployeeInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Zapraszać może WYŁĄCZNIE właściciel. Trasa i tak stoi za `role:seller`,
        // ale zarządzanie ludźmi to ostatnie miejsce, w którym chcemy polegać na
        // jednej warstwie.
        return $this->user()?->isSeller() === true
            && $this->user()->currentShop() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'name' => trim((string) $this->input('name')),
            'surname' => trim((string) $this->input('surname')),
            'permissions' => array_values(array_filter((array) $this->input('permissions', []))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'surname' => ['required', 'string', 'max:60'],
            // Adres musi być wolny w `users`. Doklejanie roli pracownika do
            // istniejącego konta sprzedawcy zrobiłoby z jednej osoby właściciela
            // i pracownika naraz — a wtedy `currentShop()` nie ma jednej
            // poprawnej odpowiedzi. Klienci sklepów siedzą w osobnej tabeli
            // `customers`, więc z nimi kolizji nie ma.
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(PanelSection::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ten adres jest już zajęty przez inne konto w Kramio.',
            'permissions.required' => 'Zaznacz przynajmniej jeden dział — pracownik bez działu nie zobaczy w panelu niczego.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'imię',
            'surname' => 'nazwisko',
            'email' => 'adres e-mail',
            'permissions' => 'działy',
        ];
    }
}
