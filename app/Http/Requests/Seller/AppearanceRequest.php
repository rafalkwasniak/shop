<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Walidacja wyglądu sklepu — na razie logo (i flaga jego usunięcia). Docelowo
 * dojdą tu kolory i wybór szablonu. Sprzedawca edytuje wyłącznie własny sklep.
 */
class AppearanceRequest extends FormRequest
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
        $rules = [
            'logo' => [
                'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048',
                Rule::dimensions()->minWidth(100)->minHeight(100)->maxWidth(2000)->maxHeight(2000),
            ],
            'remove_logo' => ['nullable', 'boolean'],
            'template' => ['nullable', 'string', Rule::in(array_keys(config('themes.templates')))],
            // Kolor własny („kolor przewodni"): pusty = brak, inaczej kanoniczny hex.
            'brand_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            // Charakter sklepu — dwie osie niezależne od szablonu (config/themes.php).
            'font' => ['nullable', 'string', Rule::in(array_keys(config('themes.fonts')))],
            'radius' => ['nullable', 'string', Rule::in(array_keys(config('themes.radii')))],
        ];

        // Paleta jest trzymana per szablon (palettes[<slug>]); każda musi należeć
        // do swojego szablonu. „custom" to paleta wirtualna (kolor własny), więc
        // dopuszczamy ją obok gotowców. Zła para szablon/paleta nie przejdzie.
        foreach (array_keys(config('themes.templates')) as $slug) {
            $rules["palettes.{$slug}"] = [
                'nullable', 'string',
                Rule::in([...array_keys(config("themes.templates.{$slug}.palettes")), 'custom']),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'logo' => 'logo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.image' => 'Logo musi być obrazem (PNG, JPG lub WebP).',
            'logo.mimes' => 'Logo musi być w formacie PNG, JPG lub WebP.',
            'logo.max' => 'Logo może mieć maksymalnie 2 MB.',
            'logo.dimensions' => 'Logo powinno mieć od 100 do 2000 px boku.',
            'brand_color.regex' => 'Kolor przewodni musi być zapisany jako #RRGGBB.',
        ];
    }
}
