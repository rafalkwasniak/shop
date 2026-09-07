<?php

namespace App\Livewire\Seller;

use App\Enums\ParcelSize;
use App\Enums\SendingMethod;
use App\Models\Order;
use App\Services\Shipping\ParcelSpec;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Nadanie przesyłki InPost przy danych dostawy na szczególe zamówienia.
 * Bliźniak {@see OrderInvoice}: cały cykl w jednym miejscu — opis paczki →
 * „Nadaj przesyłkę" → „Nadawanie…" → numer przesyłki i „Pobierz etykietę",
 * a przy błędzie czytelny powód i ponowienie.
 *
 * Paczkę opisuje się DWOJAKO, zależnie od tego, co wybrał klient w kasie:
 *  - paczkomat — gabaryt skrytki (A/B/C), bo tam paczka musi się zmieścić;
 *  - kurier — wymiary i waga realnej paczki, podpowiedziane z Ustawień sklepu,
 *    żeby sprzedawca nie wpisywał tego samego przy każdym zamówieniu.
 *
 * Sama robota dzieje się w tle ({@see \App\Jobs\CreateInpostShipment}), a stan
 * „Nadawanie…" odświeża `wire:poll`, aż komenda `shipments:refresh` zobaczy, że
 * InPost opłacił przesyłkę.
 */
class OrderShipment extends Component
{
    public Order $order;

    /** Wybrany gabaryt skrytki (wartość szablonu ShipX) — tylko paczkomat. */
    public string $size = 'small';

    /**
     * Sposób oddania paczki InPostowi. Domyślnie z Ustawień sklepu, ale
     * ZMIENIALNY tutaj: deklaracja jest po stronie InPostu nieodwracalna, a
     * jedna paczka na dziesięć bywa inna (za ciężka, żeby ją zanieść). Bez tego
     * pola sprzedawca musiałby przestawiać domyślną w Ustawieniach tam i z
     * powrotem wokół każdego wyjątku.
     */
    public string $sendingMethod = '';

    /** Wymiary paczki kurierskiej w centymetrach i waga w kilogramach. */
    public ?string $length = null;

    public ?string $width = null;

    public ?string $height = null;

    public ?string $weight = null;

    /** Czy pokazujemy potwierdzenie w miejscu (zamiast opisu paczki). */
    public bool $confirming = false;

    public function mount(Order $order): void
    {
        $this->order = $order;
        // Podpowiadamy gabaryt z poprzedniego nadania tego zamówienia, jeśli był.
        $this->size = $order->shipment_size?->value ?? 'small';
        // Najpierw poprzednia próba na tym zamówieniu (ponowienie po błędzie ma
        // wracać do tego, co sprzedawca wybrał), potem domyślna z Ustawień.
        $this->sendingMethod = ($order->shipment_sending_method
            ?? $order->shop?->sendingMethod()
            ?? SendingMethod::default())->value;

        $this->fillCourierParcel();
    }

    /**
     * Wymiary paczki kurierskiej: najpierw z poprzedniej próby na tym
     * zamówieniu (ponowienie po błędzie ma zaczynać od tego, co sprzedawca już
     * wpisał), potem z domyślnych ustawień sklepu.
     */
    private function fillCourierParcel(): void
    {
        $defaults = $this->order->shop?->courierParcelDefaults();

        $this->length = $this->asInput($this->order->shipment_length_cm ?? $defaults['length'] ?? null);
        $this->width = $this->asInput($this->order->shipment_width_cm ?? $defaults['width'] ?? null);
        $this->height = $this->asInput($this->order->shipment_height_cm ?? $defaults['height'] ?? null);
        $this->weight = $this->asInput($this->order->shipment_weight_kg ?? $defaults['weight'] ?? null);
    }

    private function asInput(int|float|string|null $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        // Przecinek zamiast kropki — tak wpisuje wagę polski sprzedawca i tak
        // pokazujemy ją z powrotem (spójnie z cenami w Ustawieniach). Końcowe
        // zera obcinamy TYLKO po przecinku: inaczej „30" zrobiłoby się „3".
        $text = str_replace('.', ',', (string) (float) $value);

        return str_contains($text, ',') ? rtrim(rtrim($text, '0'), ',') : $text;
    }

    /**
     * Czy to zamówienie opisuje się wymiarami (kurier), czy gabarytem (paczkomat).
     */
    public function needsDimensions(): bool
    {
        return $this->order->delivery_method?->requiresParcelLocker() !== true;
    }

    /**
     * Otwiera potwierdzenie. Nadanie kosztuje sprzedawcę realne pieniądze i jest
     * nieodwracalne z poziomu panelu, więc — jak przy zmianie statusu — pytamy,
     * pokazując opis paczki i cel dostawy.
     */
    public function ask(): void
    {
        $this->authorizeOwnership();
        $this->validate($this->parcelRules(), $this->parcelMessages());

        $this->confirming = true;
    }

    public function dismiss(): void
    {
        $this->confirming = false;
    }

    public function create(): void
    {
        $this->authorizeOwnership();
        $this->validate($this->parcelRules(), $this->parcelMessages());

        $this->confirming = false;

        $this->order->requestShipment($this->parcel(), $this->selectedSendingMethod());
    }

    /**
     * Wybrany sposób nadania. Fallback nigdy nie wskazuje opcji PŁATNEJ.
     */
    public function selectedSendingMethod(): SendingMethod
    {
        return SendingMethod::tryFrom($this->sendingMethod) ?? SendingMethod::default();
    }

    /**
     * Opis paczki gotowy do nadania.
     */
    public function parcel(): ParcelSpec
    {
        if (! $this->needsDimensions()) {
            return ParcelSpec::locker($this->selectedSize());
        }

        return ParcelSpec::courier(
            (int) $this->decimal($this->length),
            (int) $this->decimal($this->width),
            (int) $this->decimal($this->height),
            (float) $this->decimal($this->weight),
        );
    }

    /**
     * Limity są InPostu, nie nasze: 25 kg na paczkę, a bok powyżej 120 cm
     * czyni przesyłkę niestandardową (inny cennik i osobne zgłoszenie), więc
     * takiej po prostu nie nadajemy z panelu.
     *
     * @return array<string, string>
     */
    private function parcelRules(): array
    {
        // Sposób nadania sprawdzamy ZAWSZE — jedzie wprost do ShipX i jest tam
        // wiążący, więc nie wolno przepuścić niczego spoza enumu.
        $rules = ['sendingMethod' => ['required', Rule::enum(SendingMethod::class)]];

        if (! $this->needsDimensions()) {
            return $rules;
        }

        return $rules + [
            'length' => 'required|numeric|min:1|max:120',
            'width' => 'required|numeric|min:1|max:120',
            'height' => 'required|numeric|min:1|max:120',
            'weight' => 'required|numeric|min:0.1|max:25',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parcelMessages(): array
    {
        return [
            'length.required' => 'Podaj długość paczki.',
            'width.required' => 'Podaj szerokość paczki.',
            'height.required' => 'Podaj wysokość paczki.',
            'weight.required' => 'Podaj wagę paczki.',
            'length.max' => 'Bok powyżej 120 cm to przesyłka niestandardowa — zamów ją w panelu InPostu.',
            'width.max' => 'Bok powyżej 120 cm to przesyłka niestandardowa — zamów ją w panelu InPostu.',
            'height.max' => 'Bok powyżej 120 cm to przesyłka niestandardowa — zamów ją w panelu InPostu.',
            'weight.max' => 'InPost przyjmuje paczki do 25 kg.',
        ];
    }

    /**
     * Walidacja Laravela nie rozumie przecinka dziesiętnego, a sprzedawca pisze
     * „2,5". Normalizujemy przed sprawdzeniem — ten sam chwyt co przy cenach.
     */
    protected function prepareForValidation($attributes)
    {
        foreach (['length', 'width', 'height', 'weight'] as $field) {
            if (isset($attributes[$field])) {
                $attributes[$field] = $this->decimal($attributes[$field]);
            }
        }

        return $attributes;
    }

    private function decimal(?string $value): ?string
    {
        return blank($value) ? null : str_replace([' ', ','], ['', '.'], trim($value));
    }

    /**
     * Gabaryt wybrany w tej chwili — do treści potwierdzenia.
     */
    public function selectedSize(): ParcelSize
    {
        return ParcelSize::tryFrom($this->size) ?? ParcelSize::A;
    }

    private function authorizeOwnership(): void
    {
        abort_unless($this->order->shop_id === auth()->user()?->currentShop()?->id, 403);
    }

    public function render()
    {
        // Widoczne dla każdej WYSYŁKI (paczkomat i kurier), gdy sklep nadaje
        // przez InPost — albo gdy przesyłka już istnieje (żeby etykieta została
        // dostępna nawet po późniejszym wyłączeniu integracji).
        $visible = $this->order->hasShipment()
            || ($this->order->delivery_method?->isShipped() === true
                && $this->order->shop?->shipxEnabled() === true);

        return view('livewire.seller.order-shipment', [
            'visible' => $visible,
            'sizes' => ParcelSize::cases(),
            'sendingMethods' => SendingMethod::cases(),
            // Konto testowe: nadania są próbne, więc śledzenie InPostu ich nie zna.
            'sandbox' => $this->order->shop?->shipxEnvironment() === 'sandbox',
        ]);
    }
}
