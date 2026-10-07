<?php

namespace App\Http\Requests\Seller;

use App\Services\LoyaltyLedger;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ustawienia punktów za zakupy. Osobny formularz, nie część głównych ustawień:
 * sekcję widzi tylko sklep z uprawnieniem, a jej reguły nie mają prawa
 * blokować zapisu dostawy czy płatności.
 *
 * Włącznika tu NIE MA (decyzja Rafała 07.10) — punkty włącza na razie zespół
 * Kramio. Zmiana wartości punktu przy istniejących saldach wymaga wyraźnego
 * potwierdzenia, bo przelicza punkty wszystkich klientów sklepu.
 */
class LoyaltySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->currentShop()?->entitlement('loyalty_points');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'loyalty_earn_percent' => $this->normalize($this->input('loyalty_earn_percent')),
            'loyalty_point_value' => $this->normalize($this->input('loyalty_point_value')),
            'loyalty_delay_days' => $this->normalize($this->input('loyalty_delay_days')),
            'loyalty_validity_months' => $this->normalize($this->input('loyalty_validity_months')),
            'loyalty_max_redeem_percent' => $this->normalize($this->input('loyalty_max_redeem_percent')),
            'loyalty_min_redeem_points' => $this->normalize($this->input('loyalty_min_redeem_points')),
            'confirm_revaluation' => $this->boolean('confirm_revaluation'),
        ]);
    }

    private function normalize(mixed $value): ?string
    {
        $normalized = str_replace([' ', "\u{a0}", ','], ['', '', '.'], trim((string) $value));

        return $normalized === '' ? null : $normalized;
    }

    /** Czy zapis zmieni wartość punktu sklepu, który ma już klientów z punktami. */
    public function revaluesBalances(): bool
    {
        $shop = $this->user()->currentShop();
        $new = (int) round((float) $this->input('loyalty_point_value') * 100);
        $old = (int) round($shop->loyaltyPointValue() * 100);

        return $new !== $old && app(LoyaltyLedger::class)->hasOutstanding($shop);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'loyalty_earn_percent' => ['required', 'numeric', 'min:0.1', 'max:50'],
            'loyalty_point_value' => ['required', 'numeric', 'decimal:0,2', 'min:'.config('loyalty.point_value_min'), 'max:'.config('loyalty.point_value_max')],
            'loyalty_delay_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'loyalty_validity_months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'loyalty_max_redeem_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'loyalty_min_redeem_points' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'confirm_revaluation' => $this->revaluesBalances() ? ['accepted'] : ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'loyalty_earn_percent' => 'zwrot w punktach',
            'loyalty_point_value' => 'wartość punktu',
            'loyalty_delay_days' => 'karencja',
            'loyalty_validity_months' => 'ważność punktów',
            'loyalty_max_redeem_percent' => 'limit płatności punktami',
            'loyalty_min_redeem_points' => 'minimum punktów',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'loyalty_earn_percent.required' => 'Podaj, ile procent wraca w punktach, np. 5.',
            'loyalty_earn_percent.numeric' => 'Podaj procent liczbą, np. 5 albo 2,5.',
            'loyalty_earn_percent.max' => 'Zwrot w punktach może wynosić najwyżej 50%.',
            'loyalty_point_value.required' => 'Podaj, ile wart jest jeden punkt, np. 0,01.',
            'loyalty_point_value.numeric' => 'Podaj wartość punktu kwotą, np. 0,05.',
            'loyalty_point_value.decimal' => 'Wartość punktu podaj z dokładnością do grosza, np. 0,05.',
            'loyalty_point_value.min' => 'Punkt musi być wart co najmniej 0,01 zł.',
            'loyalty_point_value.max' => 'Punkt może być wart najwyżej 10,00 zł.',
            'loyalty_delay_days.integer' => 'Podaj liczbę dni, np. 20.',
            'loyalty_validity_months.integer' => 'Podaj liczbę miesięcy, np. 12, albo zostaw puste.',
            'loyalty_max_redeem_percent.integer' => 'Podaj procent liczbą całkowitą, np. 30, albo zostaw puste.',
            'loyalty_min_redeem_points.integer' => 'Podaj liczbę punktów, np. 500, albo zostaw puste.',
            'confirm_revaluation.accepted' => 'Twoi klienci mają już punkty. Zaznacz, że rozumiesz, że ich salda zostaną przeliczone.',
        ];
    }
}
