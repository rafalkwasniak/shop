<?php

namespace App\Http\Requests\Seller;

use App\Enums\PanelSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Zmiana działów istniejącego pracownika. Osobno od zaproszenia, bo tożsamość
 * (imię, adres) już się nie zmienia — edytujemy wyłącznie zakres dostępu.
 */
class EmployeeSectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSeller() === true
            && $this->user()->currentShop() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'permissions' => array_values(array_filter((array) $this->input('permissions', []))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
            'permissions.required' => 'Zaznacz przynajmniej jeden dział. Żeby odebrać dostęp całkiem, użyj „Odbierz dostęp".',
        ];
    }
}
