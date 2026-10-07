<?php

namespace App\Services;

use App\Enums\VatRate;
use App\Models\Order;
use App\Models\PackagePayment;
use App\Support\DiscountAllocation;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wystawianie faktur VAT przez API Fakturowni. Konfiguracja jest PER-SKLEP
 * (adres konta + token z shop_integrations, zaszyfrowane) — jeden sprzedawca =
 * jedno konto Fakturowni. Serwis buduje ciało faktury z migawki zamówienia
 * (pozycje z VAT per linia, dane nabywcy) i tworzy fakturę jednym POST-em.
 *
 * Świadome ograniczenia zakresu:
 *  - NIE sprawdza uprawnienia pakietu ani idempotencji („FV tylko raz") — to
 *    zadanie wołającego joba (guard `invoice_id`).
 *  - NIE wysyła maila z Fakturowni (`send_by_email`) — mail „Pobierz FV" wysyła
 *    nasz system, dla spójności brandu.
 *
 * Kwoty: pozycje niosą `total_price_gross` = zapisany `line_total_gross` linii,
 * więc suma faktury zgadza się co do grosza z zamówieniem (bez ponownego
 * zaokrąglania po stronie Fakturowni).
 */
class FakturowniaService
{
    /** Domyślna stawka VAT dostawy — nie trzymamy jej na zamówieniu (usługa). */
    private const DELIVERY_TAX = 23;

    /**
     * Tworzy fakturę VAT dla zamówienia. Zwraca ślad faktury
     * (`id`, `number`, `token` do publicznego PDF, `view_url`) albo null, gdy
     * brak konfiguracji lub API zwróciło błąd. Loguje żądanie i odpowiedź do
     * kanału `fakturownia` (bez tokenu — sekretu nigdy nie zapisujemy).
     *
     * @return array{id: int|null, number: string|null, token: string|null, view_url: string|null}|null
     */
    public function createInvoice(Order $order): ?array
    {
        $order->loadMissing(['items', 'shop']);
        $shop = $order->shop;

        $accountUrl = $shop?->fakturowniaAccountUrl();
        $token = $shop?->fakturowniaToken();

        if (blank($accountUrl) || blank($token)) {
            Log::channel('fakturownia')->warning('FV pominięta: brak konfiguracji Fakturowni.', [
                'order_id' => $order->id,
                'shop_id' => $shop?->id,
            ]);

            return null;
        }

        $payload = $this->buildInvoicePayload($order);

        // Audyt PRZED wysyłką: ciało faktury bez api_token (sekret trzymamy z boku).
        Log::channel('fakturownia')->info('FV: wysyłka żądania.', [
            'order_id' => $order->id,
            'shop_id' => $shop->id,
            'invoice' => $payload['invoice'],
        ]);

        try {
            $response = Http::acceptJson()
                ->timeout(20)
                ->post(rtrim($accountUrl, '/').'/invoices.json', ['api_token' => $token] + $payload);
        } catch (\Throwable $e) {
            Log::channel('fakturownia')->error('FV: wyjątek połączenia z Fakturownią.', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::channel('fakturownia')->error('FV: Fakturownia zwróciła błąd.', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();

        Log::channel('fakturownia')->info('FV: faktura utworzona.', [
            'order_id' => $order->id,
            'invoice_id' => $data['id'] ?? null,
            'number' => $data['number'] ?? null,
        ]);

        return [
            'id' => $data['id'] ?? null,
            'number' => $data['number'] ?? null,
            'token' => $data['token'] ?? null,
            'view_url' => $data['view_url'] ?? null,
        ];
    }

    /**
     * Faktura KRAMIO za pakiet (my → sprzedawca). Konto PLATFORMY z `.env`
     * (`services.fakturownia`), nie integracja sklepu: sprzedawca jest tu
     * NABYWCĄ, a nie wystawcą.
     *
     * Nabywcę bierzemy z danych sklepu (`packageInvoiceRecipient()`): z NIP-em
     * wychodzi faktura firmowa, bez niego imienna. Kwota jest BRUTTO z migawki
     * opłaty — ta sama, którą sprzedawca zapłacił.
     *
     * @return array{id: mixed, number: mixed, token: mixed, view_url: mixed}|null
     */
    public function createPackageInvoice(PackagePayment $payment): ?array
    {
        $accountUrl = config('services.fakturownia.url');
        $token = config('services.fakturownia.token');

        if (blank($accountUrl) || blank($token)) {
            Log::channel('fakturownia')->warning('FV pakietu pominięta: brak konfiguracji platformy.', [
                'payment_id' => $payment->id,
            ]);

            return null;
        }

        $payload = $this->buildPackageInvoicePayload($payment);

        Log::channel('fakturownia')->info('FV pakietu: wysyłka żądania.', [
            'payment_id' => $payment->id,
            'invoice' => $payload['invoice'],
        ]);

        try {
            $response = Http::acceptJson()
                ->timeout(20)
                ->post(rtrim($accountUrl, '/').'/invoices.json', ['api_token' => $token] + $payload);
        } catch (\Throwable $e) {
            Log::channel('fakturownia')->error('FV pakietu: wyjątek połączenia.', [
                'payment_id' => $payment->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::channel('fakturownia')->error('FV pakietu: Fakturownia zwróciła błąd.', [
                'payment_id' => $payment->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();

        Log::channel('fakturownia')->info('FV pakietu: faktura utworzona.', [
            'payment_id' => $payment->id,
            'invoice_id' => $data['id'] ?? null,
            'number' => $data['number'] ?? null,
        ]);

        return [
            'id' => $data['id'] ?? null,
            'number' => $data['number'] ?? null,
            'token' => $data['token'] ?? null,
            'view_url' => $data['view_url'] ?? null,
        ];
    }

    /**
     * Ciało faktury za pakiet: jedna pozycja usługowa (roczny abonament) w kwocie
     * brutto z migawki, VAT 23%. `status: paid` — pieniądze już wpłynęły przez
     * bramkę, więc dokument od razu jest rozliczony.
     *
     * @return array{invoice: array<string, mixed>}
     */
    public function buildPackageInvoicePayload(PackagePayment $payment): array
    {
        $today = now()->toDateString();
        $recipient = $payment->shop->packageInvoiceRecipient();
        $packageName = config("shop.packages.{$payment->target_package}.name", $payment->target_package);

        $invoice = [
            'kind' => 'vat',
            'sell_date' => $today,
            'issue_date' => $today,
            'payment_to' => $today,
            'status' => 'paid',
            'payment_type' => 'transfer',
            'buyer_name' => $recipient['name'],
            'buyer_tax_no' => $recipient['nip'],
            'buyer_street' => $recipient['street'],
            'buyer_post_code' => $recipient['postal_code'],
            'buyer_city' => $recipient['city'],
            'buyer_country' => $recipient['country'],
            'buyer_email' => $payment->shop->owner?->email,
            'positions' => [[
                'name' => 'Kramio — pakiet '.$packageName.' (abonament roczny do '.$payment->new_ends_at->format('d.m.Y').')',
                'tax' => 23,
                'quantity' => 1,
                'quantity_unit' => 'szt.',
                'total_price_gross' => (float) $payment->amount,
            ]],
            // Ślad zniżki za niewykorzystany okres — bez niej nie dałoby się
            // odtworzyć, dlaczego kwota jest niższa niż cennikowa.
            'description' => (float) $payment->credit > 0
                ? 'Uwzględniono zniżkę '.Money::pln($payment->credit).' za niewykorzystany okres poprzedniego pakietu.'
                : null,
        ];

        return ['invoice' => array_filter($invoice, fn ($value): bool => $value !== null)];
    }

    /**
     * Buduje ciało faktury (klucz `invoice`, bez `api_token`). Wydzielone od HTTP,
     * by testować samo mapowanie. Daty sprzedaży/wystawienia/płatności = dziś;
     * `status: paid` + `payment_type: transfer` (jak wdrożenie ursalogic —
     * sprzedawca wystawia FV świadomie z karty zamówienia).
     *
     * @return array{invoice: array<string, mixed>}
     */
    public function buildInvoicePayload(Order $order): array
    {
        $today = now()->toDateString();

        $invoice = [
            'kind' => 'vat',
            'sell_date' => $today,
            'issue_date' => $today,
            'payment_to' => $today,
            'status' => 'paid',
            'payment_type' => 'transfer',
            'positions' => $this->positions($order),
            // Ślad kodu rabatowego na dokumencie — kwoty pozycji są już po rabacie,
            // więc bez tej adnotacji nie dałoby się odtworzyć, skąd niższa cena.
            'description' => $this->discountNote($order),
        ] + $this->buyer($order);

        return ['invoice' => array_filter($invoice, fn ($value): bool => $value !== null)];
    }

    /**
     * Adnotacja o rabacie (null, gdy zamówienie go nie miało — wtedy pole odpada
     * w `array_filter`).
     */
    private function discountNote(Order $order): ?string
    {
        $discount = (float) $order->discount_amount;
        $points = (float) $order->points_discount;

        if ($discount <= 0 && $points <= 0) {
            return null;
        }

        // Punkty za zakupy działają jak rabat sklepu (obniżają cenę, nie są
        // zapłatą od kogoś trzeciego), więc idą tą samą drogą: w cenach pozycji.
        $parts = [];

        if ($discount > 0) {
            $parts[] = 'rabat'.(filled($order->discount_code) ? ' (kod '.$order->discount_code.')' : '').': '.Money::pln($discount);
        }

        if ($points > 0) {
            $parts[] = 'rabat za punkty: '.Money::pln($points);
        }

        return 'Uwzględniono '.implode(', ', $parts).'. Ceny pozycji są po rabacie.';
    }

    /**
     * Dane nabywcy z migawki zamówienia. Firma: nazwa firmy + NIP + adres
     * rozliczeniowy. Osoba prywatna: imię i nazwisko + adres dostawy (jeśli był),
     * bez NIP. Puste pola odpadają w `array_filter` wyżej.
     *
     * @return array<string, string|null>
     */
    private function buyer(Order $order): array
    {
        if ($order->is_company) {
            return [
                'buyer_name' => $order->company_name,
                'buyer_tax_no' => $order->company_nip,
                'buyer_email' => $order->buyer_email,
                'buyer_street' => $this->addressLine($order->company_street, $order->company_building_number, $order->company_apartment_number),
                'buyer_post_code' => $order->company_postal_code,
                'buyer_city' => $order->company_city,
            ];
        }

        return [
            'buyer_name' => trim($order->buyer_name.' '.$order->buyer_surname),
            'buyer_email' => $order->buyer_email,
            'buyer_street' => $this->addressLine($order->ship_street, $order->ship_building_number, $order->ship_apartment_number),
            'buyer_post_code' => $order->ship_postal_code,
            'buyer_city' => $order->ship_city,
        ];
    }

    /**
     * Pozycje faktury: każda pozycja zamówienia + (gdy > 0) dostawa jako osobna
     * pozycja usługowa. Kwota linii to zapisany `line_total_gross` — bez
     * przeliczania, żeby suma FV = suma zamówienia. `tax` w formacie Fakturowni:
     * liczba (23/8/5/0) albo „zw" dla zwolnionego.
     *
     * @return list<array<string, mixed>>
     */
    private function positions(Order $order): array
    {
        // Rabat rozbity na pozycje proporcjonalnie — tym samym podziałem co w
        // OrderTotals, więc VAT na fakturze zgadza się z VAT-em zamówienia.
        // ŚWIADOMIE nie dopisujemy rabatu jako ujemnej pozycji: przy dwóch
        // stawkach w koszyku musiałaby dostać jedną stawkę, co zafałszowałoby
        // rozbicie podatku.
        // Pozycje zwrócone w całości na fakturę nie trafiają, a częściowo
        // zwrócone idą w ilości EFEKTYWNEJ — faktura ma opisywać to, za co
        // klient faktycznie płaci, więc zgadza się z sumą zamówienia.
        $items = $order->items->filter(fn ($item): bool => $item->effectiveQuantity() > 0)->values();

        // Kod i punkty jednym podziałem — tak samo jak OrderTotals.
        $shares = DiscountAllocation::spread(
            round((float) $order->discount_amount + (float) $order->points_discount, 2),
            $items->pluck('line_total_gross')->map(fn ($v): float => (float) $v)->all(),
        );

        $positions = $items->map(fn ($item, int $i): array => [
            'name' => $item->name,
            'tax' => $this->tax($item->vat_rate),
            'quantity' => $item->effectiveQuantity(),
            'quantity_unit' => $item->sale_unit->abbreviation(),
            'total_price_gross' => round((float) $item->line_total_gross - ($shares[$i] ?? 0.0), 2),
        ])->values()->all();

        if ((float) $order->delivery_cost > 0) {
            $positions[] = [
                'name' => 'Dostawa: '.$order->delivery_method->label(),
                'tax' => self::DELIVERY_TAX,
                'quantity' => 1,
                'quantity_unit' => 'szt.',
                'total_price_gross' => (float) $order->delivery_cost,
            ];
        }

        return $positions;
    }

    /**
     * Stawka VAT w formacie Fakturowni: zwolniony → „zw", inaczej liczba całkowita
     * (23/8/5/0).
     */
    private function tax(VatRate $vat): int|string
    {
        return $vat === VatRate::Zw ? 'zw' : (int) $vat->value;
    }

    /**
     * Ulica z numerem domu/lokalu w jednej linii („Okrzei 73/5"). null, gdy nie
     * ma nawet ulicy — adres na fakturze jest opcjonalny.
     */
    private function addressLine(?string $street, ?string $building, ?string $apartment): ?string
    {
        if (blank($street)) {
            return null;
        }

        $line = trim($street.' '.$building);

        return filled($apartment) ? $line.'/'.$apartment : $line;
    }
}
