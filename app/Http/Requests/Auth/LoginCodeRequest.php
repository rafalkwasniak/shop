<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Kod przepisany z maila bywa podzielony („123 456") albo wklejony ze
     * spacją na końcu — to wciąż ten sam kod.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => preg_replace('/[\s-]+/u', '', (string) $this->input('code')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'digits:'.(int) config('security.two_factor.code_length')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'kod',
        ];
    }
}
