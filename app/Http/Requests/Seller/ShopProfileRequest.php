<?php

namespace App\Http\Requests\Seller;

use App\Services\HtmlSanitizer;
use App\Services\NipService;
use App\Services\PhoneService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Walidacja edycji profilu sklepu: nazwa (edytowalna), opis i dane adresowe.
 * Slug/subdomena NIE są tu edytowalne — zostają zabetonowane z rejestracji,
 * więc świadomie nie przyjmujemy ich do zmiany. Adres w osobnych, walidowanych
 * polach (spec: późniejsze użycie w dokumentach i integracjach).
 */
class ShopProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Sprzedawca edytuje wyłącznie własny sklep (ładowany z relacji usera).
        return $this->user()?->currentShop() !== null;
    }

    /**
     * Normalizacja przed walidacją: kod pocztowy do postaci NN-NNN z samych cyfr.
     */
    protected function prepareForValidation(): void
    {
        $digits = preg_replace('/\D/', '', (string) $this->input('postal_code'));

        if (strlen($digits) === 5) {
            $digits = substr($digits, 0, 2).'-'.substr($digits, 2);
        }

        $merge = ['postal_code' => $digits];

        if ($this->has('nip')) {
            $merge['nip'] = app(NipService::class)->normalize($this->input('nip'));
        }

        // Telefon kontaktowy do postaci kanonicznej (48 + 9 cyfr) przed walidacją.
        if ($this->has('contact_phone')) {
            $merge['contact_phone'] = app(PhoneService::class)->normalize($this->input('contact_phone'));
        }

        // Opis to HTML z edytora Trix — sanityzujemy wąską whitelistą przed walidacją/zapisem.
        if ($this->has('description')) {
            $merge['description'] = app(HtmlSanitizer::class)->clean((string) $this->input('description'));
        }

        // Numer konta do samych cyfr NRB: usuwamy spacje i opcjonalny prefiks „PL"
        // (sprzedawca może wkleić IBAN). Pusty numer zostaje pusty (dane opcjonalne).
        if ($this->has('bank_account_number')) {
            $account = strtoupper((string) $this->input('bank_account_number'));
            $account = preg_replace('/^PL/', '', preg_replace('/\s+/', '', $account));
            $account = preg_replace('/\D/', '', (string) $account);
            $merge['bank_account_number'] = $account === '' ? null : $account;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:'.config('shop.description_max')],
            // Opis SEO: w tagu przycinamy do ~155 znaków, w bazie zostawiamy zapas.
            'meta_description' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', PhoneService::RULE], // 48 + 9 cyfr (po normalizacji)
            'company_name' => ['nullable', 'string', 'max:255'],
            'nip' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if (filled($value) && ! app(NipService::class)->isValid((string) $value)) {
                    $fail('Podaj prawidłowy NIP (10 cyfr).');
                }
            }],
            'country' => ['required', 'string', 'max:255'],
            'province' => ['required', Rule::in(config('shop.provinces'))],
            'city' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'regex:/^\d{2}-\d{3}$/'],
            'street' => ['required', 'string', 'max:255'],
            'building_number' => ['required', 'string', 'max:32'],
            'apartment_number' => ['nullable', 'string', 'max:32'],
            'bank_account_number' => ['nullable', 'digits:26'],
            'bank_account_holder' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nazwa sklepu',
            'description' => 'opis sklepu',
            'contact_email' => 'e-mail kontaktowy',
            'contact_phone' => 'telefon kontaktowy',
            'company_name' => 'nazwa firmy',
            'nip' => 'NIP',
            'country' => 'kraj',
            'province' => 'województwo',
            'city' => 'miejscowość',
            'postal_code' => 'kod pocztowy',
            'street' => 'ulica',
            'building_number' => 'numer budynku',
            'apartment_number' => 'numer lokalu',
            'bank_account_number' => 'numer konta',
            'bank_account_holder' => 'odbiorca przelewu',
            'bank_name' => 'nazwa banku',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'postal_code.regex' => 'Podaj kod pocztowy w formacie NN-NNN.',
            'contact_phone.regex' => PhoneService::RULE_MESSAGE,
            'province.in' => 'Wybierz województwo z listy.',
            'bank_account_number.digits' => 'Numer konta musi mieć 26 cyfr (polski numer NRB).',
        ];
    }
}
