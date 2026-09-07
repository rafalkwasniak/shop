<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Potwierdzenie usunięcia własnego sklepu: hasło ORAZ przepisana nazwa sklepu.
 *
 * Dwa bezpieczniki, bo każdy łapie co innego. Hasło zatrzymuje kogoś, kto siadł
 * przy niezablokowanym komputerze; nazwa sklepu zmusza właściciela do chwili
 * zastanowienia, której samo „czy na pewno?" nie wymusza.
 *
 * Porównanie nazwy jest odporne na wielkość liter i podwójne spacje, ale nie na
 * skróty — całą nazwę trzeba wpisać.
 */
class ShopDeletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentShop() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'confirm_name' => Str::squish((string) $this->input('confirm_name')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $shop = $this->user()->currentShop();

        return [
            'confirm_name' => [
                'required', 'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($shop): void {
                    if (mb_strtolower((string) $value) !== mb_strtolower(Str::squish($shop->name))) {
                        $fail('Wpisana nazwa nie zgadza się z nazwą Twojego sklepu.');
                    }
                },
            ],
            'current_password' => ['required', 'current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm_name.required' => 'Wpisz nazwę sklepu, żeby potwierdzić usunięcie.',
            'current_password.required' => 'Podaj swoje hasło, żeby potwierdzić usunięcie.',
            'current_password.current_password' => 'Podane hasło jest nieprawidłowe.',
        ];
    }
}
