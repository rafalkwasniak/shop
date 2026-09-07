<?php

namespace App\Http\Requests\Seller;

use App\Enums\IntegrationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Walidacja konfiguracji integracji: identyfikator Google Analytics (G-…/GTM-…)
 * oraz dane Fakturowni (adres konta + token API). Wszystkie pola opcjonalne —
 * puste znaczy „usuń/nie zmieniaj" (szczegóły w kontrolerze). Regex GA pełni
 * podwójną rolę: kształt ID + bezpieczeństwo (wartość trafia do <script>
 * storefrontu, więc dopuszczamy tylko [A-Z0-9-]).
 */
class IntegrationRequest extends FormRequest
{
    /** GA4: „G-" + znaki; GTM: „GTM-" + znaki. Wielkość liter ujednolicona niżej. */
    public const GA_PATTERN = '/^(G-[A-Z0-9]{4,15}|GTM-[A-Z0-9]{4,12})$/';

    /**
     * Kod weryfikacyjny Search Console — Google wystawia ciąg base64url (litery,
     * cyfry, `-` i `_`). Wzorzec pełni tę samą podwójną rolę co przy GA: kształt
     * plus bezpieczeństwo, bo wartość ląduje w atrybucie `content` meta tagu.
     */
    public const SITE_VERIFICATION_PATTERN = '/^[A-Za-z0-9_-]{20,100}$/';

    public function authorize(): bool
    {
        return $this->user()?->currentShop() !== null;
    }

    /**
     * Normalizacja wejścia. GA: trim + wielkie litery (G-/GTM- są case-insensitive
     * u Google, trzymamy kanonicznie wielkimi). Fakturownia: adres dostaje schemat
     * https:// gdy go brak i traci końcowy ukośnik (kanoniczna baza do API i PDF);
     * token tylko trim. Puste stringi → null, by `nullable` puszczało czyszczenie.
     */
    protected function prepareForValidation(): void
    {
        $id = trim((string) $this->input('google_analytics_id'));
        $url = trim((string) $this->input('fakturownia_url'));
        $token = trim((string) $this->input('fakturownia_token'));
        $paynowApiKey = trim((string) $this->input('paynow_api_key'));
        $paynowSignatureKey = trim((string) $this->input('paynow_signature_key'));
        $shipxToken = trim((string) $this->input('shipx_token'));
        $shipxOrganizationId = trim((string) $this->input('shipx_organization_id'));
        // Sprzedawca zwykle kopiuje CAŁY meta tag z Google, nie sam kod —
        // wyłuskujemy `content`, zamiast odbijać się błędem walidacji od czegoś,
        // co jest poprawną odpowiedzią na źle postawione pytanie.
        $verification = trim((string) $this->input('google_site_verification'));

        if (preg_match('/content=["\']([^"\']+)["\']/i', $verification, $matches) === 1) {
            $verification = trim($matches[1]);
        }

        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $this->merge([
            'google_analytics_id' => $id === '' ? null : strtoupper($id),
            'fakturownia_url' => $url === '' ? null : rtrim($url, '/'),
            'fakturownia_token' => $token === '' ? null : $token,
            'paynow_api_key' => $paynowApiKey === '' ? null : $paynowApiKey,
            'paynow_signature_key' => $paynowSignatureKey === '' ? null : $paynowSignatureKey,
            'google_site_verification' => $verification === '' ? null : $verification,
            // Środowisko wybieramy checkboxem „testowe (sandbox)": zaznaczony =
            // sandbox, odznaczony = produkcja. Odznaczony domyślnie znaczy produkcję,
            // więc UI musi renderować stan bieżący, żeby zapis go nie zresetował.
            'paynow_environment' => $this->boolean('paynow_sandbox') ? 'sandbox' : 'production',
            'shipx_token' => $shipxToken === '' ? null : $shipxToken,
            'shipx_organization_id' => $shipxOrganizationId === '' ? null : $shipxOrganizationId,
            // Jak przy Paynow: checkbox „testowe (sandbox)". Przy ShipX pomyłka
            // w stronę produkcji nadaje PRAWDZIWE paczki za prawdziwe pieniądze,
            // więc UI musi renderować stan bieżący, a nie zakładać domyślny.
            'shipx_environment' => $this->boolean('shipx_sandbox') ? 'sandbox' : 'production',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'google_analytics_id' => ['nullable', 'string', 'regex:'.self::GA_PATTERN],
            'fakturownia_url' => ['nullable', 'string', 'url', 'max:255'],
            'fakturownia_token' => ['nullable', 'string', 'max:255'],
            'paynow_api_key' => ['nullable', 'string', 'max:255'],
            'paynow_signature_key' => ['nullable', 'string', 'max:255'],
            'paynow_environment' => ['required', 'in:sandbox,production'],
            // Token ShipX to JWT — bywa długi (nasz ma ~950 znaków), więc żadnego
            // `max:255` jak przy kluczach Paynow; kolumna jest szyfrowanym JSON-em.
            'shipx_token' => ['nullable', 'string', 'max:4000'],
            'shipx_organization_id' => ['nullable', 'string', 'regex:/^[0-9]{1,12}$/'],
            'shipx_environment' => ['required', 'in:sandbox,production'],
            'google_site_verification' => ['nullable', 'string', 'regex:'.self::SITE_VERIFICATION_PATTERN],
        ];
    }

    /**
     * Reguła między-polowa: adres Fakturowni bez tokenu jest dopuszczalny TYLKO
     * wtedy, gdy token jest już zapisany (puste pole = „zostaw token bez zmian").
     * Gdy sklep tokenu nie ma, sam adres nie wystarczy — nie da się wystawić FV
     * bez tokenu, więc żądamy go od razu.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $url = $this->input('fakturownia_url');
            $token = $this->input('fakturownia_token');
            $storedToken = $this->user()?->currentShop()?->integration(IntegrationType::Invoicing)?->config['api_token'] ?? null;

            if (filled($url) && blank($token) && blank($storedToken)) {
                $validator->errors()->add('fakturownia_token', 'Podaj token API Fakturowni, aby połączyć konto.');
            }

            // Bliźniacza reguła dla Paynow: klucz API bez klucza podpisu jest OK
            // tylko, gdy podpis jest już zapisany (puste pole = „zostaw bez zmian").
            // Przy pierwszej konfiguracji obu kluczy nie da się rozdzielić.
            $paynowApiKey = $this->input('paynow_api_key');
            $paynowSignatureKey = $this->input('paynow_signature_key');
            $storedSignature = $this->user()?->currentShop()?->integration(IntegrationType::Payments)?->config['signature_key'] ?? null;

            if (filled($paynowApiKey) && blank($paynowSignatureKey) && blank($storedSignature)) {
                $validator->errors()->add('paynow_signature_key', 'Podaj klucz obliczania podpisu Paynow, aby połączyć konto.');
            }

            // ShipX: obie wartości pochodzą z tego samego ekranu w panelu InPostu
            // i bez kompletu nie da się nadać przesyłki. Reguła działa w OBIE
            // strony (inaczej niż para Fakturowni/Paynow, gdzie tylko token bywa
            // „zostaw bez zmian"), bo Organization ID nie jest sekretem — pole
            // pokazuje go wprost, więc puste znaczy naprawdę puste.
            $shipxToken = $this->input('shipx_token');
            $shipxOrganizationId = $this->input('shipx_organization_id');
            $storedShipxToken = $this->user()?->currentShop()?->integration(IntegrationType::Shipping)?->config['token'] ?? null;

            if (filled($shipxToken) && blank($shipxOrganizationId)) {
                $validator->errors()->add('shipx_organization_id', 'Podaj Organization ID z panelu InPost.');
            }

            if (filled($shipxOrganizationId) && blank($shipxToken) && blank($storedShipxToken)) {
                $validator->errors()->add('shipx_token', 'Podaj token ShipX z panelu InPost.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'google_analytics_id.regex' => 'Podaj poprawny identyfikator w formacie G-XXXXXXXXXX (GA4) lub GTM-XXXXXXX (Tag Manager).',
            'fakturownia_url.url' => 'Podaj poprawny adres konta Fakturowni, np. https://twojadomena.fakturownia.pl.',
            'google_site_verification.regex' => 'Wklej kod weryfikacyjny z Google Search Console (możesz wkleić cały meta tag — wyciągniemy z niego kod).',
            'shipx_organization_id.regex' => 'Organization ID to sam numer z panelu InPost, np. 203242.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'google_analytics_id' => 'identyfikator Google Analytics',
            'fakturownia_url' => 'adres konta Fakturowni',
            'fakturownia_token' => 'token API Fakturowni',
            'paynow_api_key' => 'klucz dostępu do API Paynow',
            'paynow_signature_key' => 'klucz obliczania podpisu Paynow',
            'paynow_environment' => 'środowisko Paynow',
            'shipx_token' => 'token ShipX',
            'shipx_organization_id' => 'Organization ID InPost',
            'shipx_environment' => 'środowisko InPost',
        ];
    }
}
