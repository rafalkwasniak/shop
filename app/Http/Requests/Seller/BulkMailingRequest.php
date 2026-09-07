<?php

namespace App\Http\Requests\Seller;

use App\Services\HtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Treść wiadomości do klientów. Temat trafia w linię tematu maila i zarazem
 * w nagłówek treści, więc trzymamy go krótko — długi ucina się w skrzynce.
 *
 * Treść pisze się w tym samym edytorze co opisy produktów i stron sklepu, więc
 * przechodzi przez ten sam sanitizer HTML (biała lista znaczników). Do skrzynki
 * trafia jako HTML ułożony przez `Prose` — dzięki temu pogrubienia, listy i
 * odnośniki wyglądają tak, jak sprzedawca je widział podczas pisania.
 */
class BulkMailingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:'.config('bulk_mail.body_max')],
            // Promowany produkt musi należeć do TEGO sklepu — inaczej sprzedawca
            // wypromowałby cudzy towar (i wysłał klientów do konkurencji).
            'product_id' => [
                'nullable',
                Rule::exists('products', 'id')->where('shop_id', $this->user()->currentShop()?->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'Podaj temat wiadomości.',
            'subject.max' => 'Temat jest za długi — zmieść się w 150 znakach, inaczej skrzynki go utną.',
            'body.required' => 'Napisz treść wiadomości.',
            'body.max' => 'Treść jest za długa — skróć ją nieco.',
            'product_id.exists' => 'Wybierz produkt ze swojego sklepu.',
        ];
    }

    /**
     * Sanitizacja HTML przed walidacją (konwencja projektu: sanitizer na
     * zapisie, `Prose` na wyjściu). Pusty edytor potrafi przysłać sam znacznik
     * bez tekstu — po oczyszczeniu zostaje pustka i zadziała reguła `required`.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('body')) {
            $clean = app(HtmlSanitizer::class)->clean((string) $this->input('body'));

            $this->merge([
                'body' => trim(strip_tags($clean)) === '' ? '' : $clean,
            ]);
        }
    }
}
