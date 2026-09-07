<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Zakup pakietu: jedno pole, ale bez niego nie wolno ruszyć płatności.
 *
 * `immediate_start` to wyraźne żądanie rozpoczęcia świadczenia przed upływem
 * 14 dni na odstąpienie (art. 15 ust. 3 u.p.k.). Musi być odrębną, świadomą
 * czynnością — domyślnie odznaczone, nigdy zaznaczone z góry i nigdy dorozumiane
 * z samego kliknięcia przycisku. Bez niego przy odstąpieniu oddajemy 100%,
 * mimo że §9 ust. 2 Regulaminu mówi o rozliczeniu proporcjonalnym.
 */
class PackagePurchaseRequest extends FormRequest
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
            'immediate_start' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'immediate_start.accepted' => 'Zaznacz zgodę na natychmiastowe uruchomienie pakietu — bez niej nie możemy włączyć go przed upływem 14 dni na odstąpienie.',
        ];
    }
}
