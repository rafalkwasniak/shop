<?php

namespace App\Livewire\Seller;

use App\Enums\PanelSection;
use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Models\DiscountCode;
use App\Models\Shop;
use App\Support\DiscountSummary;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Formularz kodu rabatowego — dodawanie i edycja. Livewire, bo formularz sam
 * się przestawia: darmowa wysyłka nie potrzebuje wartości ani produktu, zakres
 * „produkt" domaga się wskazania towaru, a prawa kolumna po każdej zmianie
 * mówi po polsku, co ten kod właśnie robi (patrz DiscountSummary).
 *
 * Limit użyć wybieramy TRYBEM (bez limitu / jednorazowy / maksymalnie N), a nie
 * gołą liczbą — „jednorazowy" to najczęstszy przypadek, a wpisywanie „1" w pole
 * „limit" jest dla sprzedawcy mniej oczywiste niż zaznaczenie opcji.
 */
class DiscountCodeForm extends Component
{
    public Shop $shop;

    public ?DiscountCode $code = null;

    /**
     * Kontekst listy, z której sprzedawca wszedł w edycję (widok, szukanie,
     * strona). Po zapisie wracamy dokładnie tam — przy 6 stronach kodów odesłanie
     * na pierwszą byłoby karą za każdą poprawkę.
     *
     * @var array<string, string|int>
     */
    public array $listQuery = [];

    public string $codeValue = '';

    public string $type = 'percent';

    public string $value = '';

    public string $scope = 'cart';

    public ?int $product_id = null;

    public string $min_items_total = '';

    public string $starts_at = '';

    public string $ends_at = '';

    /** bez_limitu | jednorazowy | limit */
    public string $uses_mode = 'bez_limitu';

    public string $max_uses = '';

    public ?int $customer_id = null;

    public bool $is_active = true;

    /**
     * @param  array<string, mixed>  $prefill  ustawienia przyniesione z innego
     *                                         miejsca panelu (np. „wystaw kod dla
     *                                         klienta" ze szczegółów zamówienia)
     * @param  array<string, string|int>  $listQuery  kontekst listy do powrotu
     */
    /**
     * Brama działu przy KAŻDYM żądaniu, nie tylko przy pierwszym renderze.
     *
     * `booted()` jest tu jedynym poprawnym miejscem: `mount()` wykonuje się raz,
     * a kolejne akcje przychodzą do `livewire/update` i nie przechodzą przez
     * `section:` z trasy, na której komponent się renderował.
     */
    public function booted(): void
    {
        abort_unless((bool) auth()->user()?->canAccess(PanelSection::Marketing), 403);
    }

    public function mount(Shop $shop, ?DiscountCode $code = null, array $prefill = [], array $listQuery = []): void
    {
        $this->shop = $shop;
        $this->code = $code;
        $this->listQuery = $listQuery;

        if ($code === null) {
            $this->codeValue = DiscountCode::randomCode();
            $this->customer_id = isset($prefill['customer_id']) ? (int) $prefill['customer_id'] : null;
            $this->uses_mode = is_string($prefill['uses_mode'] ?? null) ? $prefill['uses_mode'] : $this->uses_mode;

            return;
        }

        $this->codeValue = $code->code;
        $this->type = $code->type->value;
        $this->value = $code->value !== null ? self::trimZeros((float) $code->value) : '';
        $this->scope = $code->scope->value;
        $this->product_id = $code->product_id;
        $this->min_items_total = $code->min_items_total !== null ? self::trimZeros((float) $code->min_items_total) : '';
        $this->starts_at = $code->starts_at?->format('Y-m-d') ?? '';
        $this->ends_at = $code->ends_at?->format('Y-m-d') ?? '';
        $this->uses_mode = match (true) {
            $code->max_uses === null => 'bez_limitu',
            $code->max_uses === 1 => 'jednorazowy',
            default => 'limit',
        };
        $this->max_uses = $code->max_uses !== null && $code->max_uses > 1 ? (string) $code->max_uses : '';
        $this->customer_id = $code->customer_id;
        $this->is_active = (bool) $code->is_active;
    }

    /**
     * Zmiana typu na „darmowa wysyłka" czyści to, co przestaje mieć sens —
     * inaczej w bazie zostałaby wartość rabatu przy kodzie, który nic nie zdejmuje
     * z produktów.
     */
    public function updatedType(string $value): void
    {
        if ($value === DiscountType::FreeShipping->value) {
            $this->value = '';
            $this->scope = DiscountScope::Cart->value;
            $this->product_id = null;
        }
    }

    public function updatedScope(string $value): void
    {
        if ($value === DiscountScope::Cart->value) {
            $this->product_id = null;
        }
    }

    public function generateCode(): void
    {
        $this->codeValue = DiscountCode::randomCode();
        $this->resetValidation('codeValue');
    }

    /**
     * Kod „na brudno" z bieżącego stanu formularza — wyłącznie do streszczenia
     * w prawej kolumnie. Nigdy nie jest zapisywany.
     */
    public function draft(): DiscountCode
    {
        $draft = new DiscountCode([
            'code' => trim($this->codeValue) !== '' ? $this->codeValue : '',
            'type' => $this->type,
            'value' => $this->value !== '' ? (float) str_replace(',', '.', $this->value) : null,
            'scope' => $this->scope,
            'min_items_total' => $this->min_items_total !== '' ? (float) str_replace(',', '.', $this->min_items_total) : null,
            'starts_at' => $this->starts_at !== '' ? Carbon::parse($this->starts_at) : null,
            'ends_at' => $this->ends_at !== '' ? Carbon::parse($this->ends_at)->endOfDay() : null,
            'max_uses' => $this->resolvedMaxUses(),
            'customer_id' => $this->customer_id,
            'is_active' => $this->is_active,
        ]);

        // Relacje wstrzykujemy ręcznie — model nie jest zapisany, więc nie ma ich skąd dociągnąć.
        $draft->setRelation('product', $this->product_id !== null ? $this->products()->firstWhere('id', $this->product_id) : null);
        $draft->setRelation('customer', $this->customer_id !== null ? $this->customers()->firstWhere('id', $this->customer_id) : null);

        return $draft;
    }

    /**
     * @return list<string>
     */
    public function summary(): array
    {
        return DiscountSummary::lines($this->draft());
    }

    /**
     * Produkty sklepu do wyboru przy zakresie „wybrany produkt".
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Product>
     */
    public function products(): \Illuminate\Support\Collection
    {
        return $this->shop->products()->orderBy('name')->get(['id', 'name', 'price_gross']);
    }

    /**
     * Klienci sklepu do kodu imiennego. Tylko konta aktywowane — do
     * nieaktywowanego i tak nie ma jak wysłać kodu.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Customer>
     */
    public function customers(): \Illuminate\Support\Collection
    {
        return $this->shop->customers()
            ->whereNotNull('email_verified_at')
            ->orderBy('surname')->orderBy('name')
            ->get(['id', 'name', 'surname', 'email']);
    }

    private function resolvedMaxUses(): ?int
    {
        return match ($this->uses_mode) {
            'jednorazowy' => 1,
            'limit' => $this->max_uses !== '' ? (int) $this->max_uses : null,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'codeValue' => [
                'required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('discount_codes', 'code')
                    ->where('shop_id', $this->shop->id)
                    ->ignore($this->code?->id),
            ],
            'type' => ['required', Rule::enum(DiscountType::class)],
            'value' => [
                Rule::requiredIf(fn () => DiscountType::from($this->type)->requiresValue()),
                'nullable', 'numeric', 'min:0.01',
                // Procent nie może przekroczyć 100 — 120% zniżki to zamówienie za ujemną kwotę.
                ...($this->type === DiscountType::Percent->value ? ['max:100'] : ['max:1000000']),
            ],
            'scope' => ['required', Rule::enum(DiscountScope::class)],
            'product_id' => [
                Rule::requiredIf(fn () => DiscountScope::from($this->scope)->requiresProduct()),
                'nullable', 'integer',
                Rule::exists('products', 'id')->where('shop_id', $this->shop->id),
            ],
            'min_items_total' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'uses_mode' => ['required', 'in:bez_limitu,jednorazowy,limit'],
            'max_uses' => [
                Rule::requiredIf(fn () => $this->uses_mode === 'limit'),
                'nullable', 'integer', 'min:1', 'max:100000',
            ],
            'customer_id' => [
                'nullable', 'integer',
                Rule::exists('customers', 'id')->where('shop_id', $this->shop->id),
            ],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'codeValue' => 'kod',
            'value' => 'wartość rabatu',
            'product_id' => 'produkt',
            'min_items_total' => 'minimalna wartość koszyka',
            'starts_at' => 'data początku',
            'ends_at' => 'data końca',
            'max_uses' => 'limit użyć',
            'customer_id' => 'klient',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'codeValue.regex' => 'Kod może zawierać tylko litery, cyfry, myślnik i podkreślnik.',
            'codeValue.unique' => 'Taki kod już istnieje w Twoim sklepie.',
            'product_id.required' => 'Wskaż produkt, którego dotyczy kod.',
            'ends_at.after_or_equal' => 'Data końca nie może być wcześniejsza niż data początku.',
        ];
    }

    /**
     * Kod zawsze wersalikami — zanim ruszy walidacja unikalności, żeby „lato10"
     * i „LATO10" nie przeszły jako dwa różne kody.
     */
    public function updatedCodeValue(): void
    {
        $this->codeValue = mb_strtoupper(trim($this->codeValue));
    }

    /**
     * Normalizacja wpisanego tekstu przed walidacją (odpowiednik
     * `prepareForValidation` z Form Requestów): kod wersalikami, kwoty z
     * przecinkiem po polsku — „19,90" to ta sama liczba co „19.90" i sprzedawca
     * nie ma powodu o tym myśleć.
     */
    private function normalizeInput(): void
    {
        $this->codeValue = mb_strtoupper(trim($this->codeValue));
        $this->value = str_replace(',', '.', trim($this->value));
        $this->min_items_total = str_replace(',', '.', trim($this->min_items_total));
        $this->max_uses = trim($this->max_uses);
    }

    public function save(): void
    {
        $this->normalizeInput();
        $this->validate();

        $attributes = [
            'code' => $this->codeValue,
            'type' => $this->type,
            'value' => DiscountType::from($this->type)->requiresValue()
                ? (float) str_replace(',', '.', $this->value)
                : null,
            'scope' => $this->scope,
            'product_id' => DiscountScope::from($this->scope)->requiresProduct() ? $this->product_id : null,
            'min_items_total' => $this->min_items_total !== '' ? (float) str_replace(',', '.', $this->min_items_total) : null,
            'starts_at' => $this->starts_at !== '' ? Carbon::parse($this->starts_at)->startOfDay() : null,
            // Data końca obejmuje CAŁY wskazany dzień — klient ma prawo użyć kodu
            // do północy, nie do 00:00 rano.
            'ends_at' => $this->ends_at !== '' ? Carbon::parse($this->ends_at)->endOfDay() : null,
            'max_uses' => $this->resolvedMaxUses(),
            'customer_id' => $this->customer_id,
            'is_active' => $this->is_active,
        ];

        if ($this->code === null) {
            $this->shop->discountCodes()->create($attributes);
            session()->flash('success', 'Kod „'.$this->codeValue.'" został dodany.');
        } else {
            $this->code->update($attributes);
            session()->flash('success', 'Kod „'.$this->codeValue.'" został zapisany.');
        }

        $this->redirect(route('seller.discounts.index', $this->listQuery), navigate: false);
    }

    /**
     * „10.00" → „10", ale „10.50" → „10.5" — pole formularza ma pokazywać to, co
     * sprzedawca wpisał, a nie księgowe zera.
     */
    private static function trimZeros(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    public function render()
    {
        return view('livewire.seller.discount-code-form');
    }
}
