<?php

namespace App\Http\Requests\Seller;

use App\Enums\SaleUnit;
use App\Enums\SendingMethod;
use App\Enums\VatRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Walidacja ustawień sklepu — domyślna stawka VAT i domyślna jednostka
 * sprzedaży (podpowiadane przy nowym produkcie), fiszki metod i włączniki.
 * Sprzedawca edytuje wyłącznie własny sklep.
 */
class ShopSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentShop() !== null;
    }

    /**
     * Włączniki usług to checkboxy — nieobecne w POST, gdy odznaczone.
     * Normalizujemy do jawnego boola, żeby zapis nie gubił wyłączenia metody.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'bank_transfer_enabled' => $this->boolean('bank_transfer_enabled'),
            'pickup_enabled' => $this->boolean('pickup_enabled'),
            'pay_on_pickup_enabled' => $this->boolean('pay_on_pickup_enabled'),
            'courier_enabled' => $this->boolean('courier_enabled'),
            // Kwoty kuriera: przecinek → kropka, spacje out (jak cena produktu).
            // Puste = null: koszt dopiero wymusimy regułą, gdy kurier włączony, a
            // próg pusty to świadomy „brak darmowej dostawy".
            'courier_cost' => $this->normalizeAmount($this->input('courier_cost')),
            'courier_free_from' => $this->normalizeAmount($this->input('courier_free_from')),
            'parcel_locker_enabled' => $this->boolean('parcel_locker_enabled'),
            'parcel_locker_cost' => $this->normalizeAmount($this->input('parcel_locker_cost')),
            'parcel_locker_free_from' => $this->normalizeAmount($this->input('parcel_locker_free_from')),
            'courier_cod_enabled' => $this->boolean('courier_cod_enabled'),
            'courier_cod_cost' => $this->normalizeAmount($this->input('courier_cod_cost')),
            'courier_cod_free_from' => $this->normalizeAmount($this->input('courier_cod_free_from')),
            'parcel_locker_cod_enabled' => $this->boolean('parcel_locker_cod_enabled'),
            'parcel_locker_cod_cost' => $this->normalizeAmount($this->input('parcel_locker_cod_cost')),
            'parcel_locker_cod_free_from' => $this->normalizeAmount($this->input('parcel_locker_cod_free_from')),
            'google_analytics_enabled' => $this->boolean('google_analytics_enabled'),
            'fakturownia_enabled' => $this->boolean('fakturownia_enabled'),
            'paynow_enabled' => $this->boolean('paynow_enabled'),
            // Auto-FV po opłaceniu online — decyzja systemowa (nie dane połączenia),
            // więc mieszka tu, wcięta pod włącznikiem Paynow. Flaga siedzi w configu
            // integracji płatności; zapis merge'uje ją w kontrolerze.
            'paynow_auto_invoice' => $this->boolean('paynow_auto_invoice'),
            'shipx_enabled' => $this->boolean('shipx_enabled'),
            // Domyślna paczka kurierska: wymiary w cm, waga w kg. Ta sama
            // normalizacja co przy kwotach — „2,5" i „2.5" mają znaczyć to samo.
            'courier_parcel_length_cm' => $this->normalizeAmount($this->input('courier_parcel_length_cm')),
            'courier_parcel_width_cm' => $this->normalizeAmount($this->input('courier_parcel_width_cm')),
            'courier_parcel_height_cm' => $this->normalizeAmount($this->input('courier_parcel_height_cm')),
            'courier_parcel_weight_kg' => $this->normalizeAmount($this->input('courier_parcel_weight_kg')),
            // Nowe pole — gdy formularz go nie przyśle (starszy submit), zostawiamy
            // bieżącą jednostkę sklepu, żeby częściowy zapis jej nie wyzerował.
            'default_sale_unit' => $this->input('default_sale_unit', $this->user()?->currentShop()?->default_sale_unit?->value ?? 'piece'),
        ]);
    }

    /**
     * Kwota z pola tekstowego → format akceptowany przez `numeric` (kropka
     * dziesiętna, bez spacji). Puste pole → null (kolumna nullable). Wzorzec z
     * ProductRequest, żeby „19,90" i „19.90" znaczyły to samo.
     */
    private function normalizeAmount(mixed $value): ?string
    {
        $normalized = str_replace([' ', "\u{a0}", ','], ['', '', '.'], trim((string) $value));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'default_vat_rate' => ['required', Rule::enum(VatRate::class)],
            'default_sale_unit' => ['required', Rule::enum(SaleUnit::class)],
            'bank_transfer_enabled' => ['boolean'],
            'pickup_enabled' => ['boolean'],
            'pay_on_pickup_enabled' => ['boolean'],
            'courier_enabled' => ['boolean'],
            // Koszt wymagany dopiero, gdy kurier włączony (0 jest OK = gratis).
            'courier_cost' => [Rule::requiredIf($this->boolean('courier_enabled')), 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            // Próg opcjonalny (null = brak darmowej dostawy).
            'courier_free_from' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'parcel_locker_enabled' => ['boolean'],
            'parcel_locker_cost' => [Rule::requiredIf($this->boolean('parcel_locker_enabled')), 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            'parcel_locker_free_from' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'courier_cod_enabled' => ['boolean'],
            'courier_cod_cost' => [Rule::requiredIf($this->boolean('courier_cod_enabled')), 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            'courier_cod_free_from' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'parcel_locker_cod_enabled' => ['boolean'],
            'parcel_locker_cod_cost' => [Rule::requiredIf($this->boolean('parcel_locker_cod_enabled')), 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            'parcel_locker_cod_free_from' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'google_analytics_enabled' => ['boolean'],
            'fakturownia_enabled' => ['boolean'],
            'paynow_enabled' => ['boolean'],
            'paynow_auto_invoice' => ['boolean'],
            'shipx_enabled' => ['boolean'],
            // Sposób nadania: wybór jest WIĄŻĄCY przy każdej nadanej przesyłce
            // (InPost nie pozwala go potem zmienić), więc nie przyjmujemy tu
            // niczego spoza enumu. Brak pola = zostaje dotychczasowy.
            'shipment_sending_method' => ['nullable', Rule::enum(SendingMethod::class)],
            // Limity są InPostu, nie nasze — te same, co przy nadawaniu, żeby
            // sprzedawca nie zapisał domyślnej paczki, której potem nie da się
            // wysłać. Bok powyżej 120 cm = przesyłka niestandardowa.
            'courier_parcel_length_cm' => ['nullable', 'integer', 'min:1', 'max:120'],
            'courier_parcel_width_cm' => ['nullable', 'integer', 'min:1', 'max:120'],
            'courier_parcel_height_cm' => ['nullable', 'integer', 'min:1', 'max:120'],
            'courier_parcel_weight_kg' => ['nullable', 'numeric', 'min:0.1', 'max:25'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'default_vat_rate' => 'domyślna stawka VAT',
            'default_sale_unit' => 'domyślna jednostka sprzedaży',
            'bank_transfer_enabled' => 'przelew na konto',
            'pickup_enabled' => 'odbiór osobisty',
            'pay_on_pickup_enabled' => 'płatność przy odbiorze',
            'courier_enabled' => 'dostawa kurierem',
            'courier_cost' => 'koszt dostawy kurierem',
            'courier_free_from' => 'próg darmowej dostawy',
            'parcel_locker_enabled' => 'dostawa do paczkomatu',
            'parcel_locker_cost' => 'koszt dostawy do paczkomatu',
            'parcel_locker_free_from' => 'próg darmowej dostawy do paczkomatu',
            'courier_cod_enabled' => 'kurier za pobraniem',
            'courier_cod_cost' => 'koszt dostawy kurierem za pobraniem',
            'courier_cod_free_from' => 'próg darmowej dostawy kurierem za pobraniem',
            'parcel_locker_cod_enabled' => 'paczkomat za pobraniem',
            'parcel_locker_cod_cost' => 'koszt dostawy do paczkomatu za pobraniem',
            'parcel_locker_cod_free_from' => 'próg darmowej dostawy do paczkomatu za pobraniem',
            'google_analytics_enabled' => 'Google Analytics',
            'fakturownia_enabled' => 'Fakturownia',
            'paynow_enabled' => 'płatności online (Paynow)',
            'paynow_auto_invoice' => 'automatyczna faktura po opłaceniu',
            'shipx_enabled' => 'nadawanie przesyłek InPost',
            'shipment_sending_method' => 'sposób oddawania paczek',
            'courier_parcel_length_cm' => 'długość paczki',
            'courier_parcel_width_cm' => 'szerokość paczki',
            'courier_parcel_height_cm' => 'wysokość paczki',
            'courier_parcel_weight_kg' => 'waga paczki',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'courier_cost.required' => 'Podaj koszt dostawy kurierem (może być 0).',
            'courier_cost.numeric' => 'Podaj koszt liczbą, np. 15,99.',
            'courier_free_from.numeric' => 'Podaj kwotę progu liczbą, np. 200.',
            'parcel_locker_cost.required' => 'Podaj koszt dostawy do paczkomatu (może być 0).',
            'parcel_locker_cost.numeric' => 'Podaj koszt liczbą, np. 12,99.',
            'parcel_locker_free_from.numeric' => 'Podaj kwotę progu liczbą, np. 150.',
            'courier_cod_cost.required' => 'Podaj koszt dostawy kurierem za pobraniem (może być 0).',
            'courier_cod_cost.numeric' => 'Podaj koszt liczbą, np. 19,99.',
            'courier_cod_free_from.numeric' => 'Podaj kwotę progu liczbą, np. 250.',
            'parcel_locker_cod_cost.required' => 'Podaj koszt dostawy do paczkomatu za pobraniem (może być 0).',
            'parcel_locker_cod_cost.numeric' => 'Podaj koszt liczbą, np. 16,99.',
            'parcel_locker_cod_free_from.numeric' => 'Podaj kwotę progu liczbą, np. 200.',
            'courier_parcel_length_cm.max' => 'Bok powyżej 120 cm to przesyłka niestandardowa — takiej nie nadasz z panelu.',
            'courier_parcel_width_cm.max' => 'Bok powyżej 120 cm to przesyłka niestandardowa — takiej nie nadasz z panelu.',
            'courier_parcel_height_cm.max' => 'Bok powyżej 120 cm to przesyłka niestandardowa — takiej nie nadasz z panelu.',
            'courier_parcel_weight_kg.max' => 'InPost przyjmuje paczki do 25 kg.',
            'courier_parcel_length_cm.integer' => 'Podaj długość w pełnych centymetrach, np. 30.',
            'courier_parcel_width_cm.integer' => 'Podaj szerokość w pełnych centymetrach, np. 20.',
            'courier_parcel_height_cm.integer' => 'Podaj wysokość w pełnych centymetrach, np. 10.',
            'courier_parcel_weight_kg.numeric' => 'Podaj wagę liczbą, np. 2,5.',
        ];
    }
}
