<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Baner strony głównej: obrazek (wymagany przy dodawaniu), tekst
 * alternatywny, opcjonalny link i opcjonalne „pokazuj od–do".
 *
 * Link przyjmujemy w dwóch postaciach: adres w sklepie („/produkty") albo
 * pełny adres. Wpisany bez `https://` („aracaffee.pl/kawa") dostaje go
 * w normalizacji — sprzedawca kopiuje adresy z paska różnie.
 */
class BannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentShop() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->route('banner') === null;

        return [
            'image' => [
                $creating ? 'required' : 'nullable',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:'.config('shop.banners.max_upload_kb'),
                Rule::dimensions()->minWidth((int) config('shop.banners.min_width')),
            ],
            'alt' => ['required', 'string', 'max:200'],
            'link_url' => [
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $relative = str_starts_with((string) $value, '/') && ! str_starts_with((string) $value, '//');
                    if (! $relative && filter_var($value, FILTER_VALIDATE_URL) === false) {
                        $fail('Podaj adres strony, np. /produkty albo https://example.com.');
                    }
                },
            ],
            'is_active' => ['boolean'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Wybierz obrazek banera.',
            'image.image' => 'Plik musi być obrazkiem (JPG, PNG lub WebP).',
            'image.mimes' => 'Obsługujemy obrazki JPG, PNG i WebP.',
            'image.max' => 'Obrazek jest za duży (maks. 20 MB).',
            'image.dimensions' => 'Obrazek jest za wąski — baner idzie na całą szerokość ekranu, potrzeba co najmniej :min_width px szerokości.',
            'alt.required' => 'Opisz krótko, co jest na banerze — przeczytają to wyszukiwarki i czytniki ekranu.',
            'ends_on.after_or_equal' => 'Data końca nie może być wcześniejsza niż data początku.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'image' => 'obrazek',
            'alt' => 'opis banera',
            'link_url' => 'link',
            'starts_on' => 'pokazuj od',
            'ends_on' => 'pokazuj do',
        ];
    }

    protected function prepareForValidation(): void
    {
        $link = trim((string) $this->input('link_url'));

        if ($link !== '' && ! str_starts_with($link, '/') && ! preg_match('~^https?://~i', $link)) {
            $link = 'https://'.$link;
        }

        $this->merge([
            'alt' => trim((string) $this->input('alt')),
            'link_url' => $link !== '' ? $link : null,
            'is_active' => $this->boolean('is_active'),
            'starts_on' => $this->input('starts_on') ?: null,
            'ends_on' => $this->input('ends_on') ?: null,
        ]);
    }
}
