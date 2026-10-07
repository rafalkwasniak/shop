<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ręczna korekta punktów klienta przez sprzedawcę (kartoteka klientów).
 * Liczba ze znakiem: dodatnia dopisuje, ujemna odejmuje. Opis jest
 * obowiązkowy — klient zobaczy go w swojej historii jako „Korekta sklepu".
 */
class LoyaltyAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentShop() !== null;
    }

    protected function prepareForValidation(): void
    {
        // „+500", „1 000" i „−200" (minus typograficzny) mają znaczyć to, co widać.
        $points = str_replace([' ', "\u{a0}", '+', '−'], ['', '', '', '-'], trim((string) $this->input('points')));

        $this->merge([
            'points' => $points,
            'note' => trim((string) $this->input('note')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'points' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'],
            'note' => ['required', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'points.required' => 'Podaj liczbę punktów, np. 500 albo -200.',
            'points.integer' => 'Podaj całą liczbę punktów, np. 500 albo -200.',
            'points.not_in' => 'Korekta o 0 punktów niczego nie zmienia.',
            'note.required' => 'Napisz krótko, za co ta korekta — klient zobaczy to w swojej historii.',
            'note.max' => 'Opis może mieć najwyżej 120 znaków.',
        ];
    }
}
