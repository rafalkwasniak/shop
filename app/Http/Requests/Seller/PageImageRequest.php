<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

/** Zdjęcie wstawiane w treść strony „Informacje" (przycisk w edytorze). */
class PageImageRequest extends FormRequest
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
        return [
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.config('shop.page_images.max_upload_kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Wybierz zdjęcie.',
            'image.image' => 'Plik musi być obrazkiem (JPG, PNG lub WebP).',
            'image.mimes' => 'Obsługujemy obrazki JPG, PNG i WebP.',
            'image.max' => 'Zdjęcie jest za duże (maks. 20 MB).',
        ];
    }
}
