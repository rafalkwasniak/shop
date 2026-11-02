<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

/** Czas między slajdami slidera banerów (sekundy, granice z configu). */
class BannerIntervalRequest extends FormRequest
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
            'banner_interval' => [
                'required',
                'integer',
                'min:'.config('shop.banners.interval_min'),
                'max:'.config('shop.banners.interval_max'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['banner_interval' => 'czas wyświetlania'];
    }
}
