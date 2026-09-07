<?php

namespace App\Livewire\Seller;

use App\Exceptions\OrderEditException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\OrderEditor as OrderEditorService;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Karta „Pozycje" na szczególe zamówienia z trybem edycji (panel sprzedawcy).
 * Domyślnie tylko do odczytu; „Edytuj zamówienie" odsłania zmianę ilości (stepper
 * + wpisanie z palca, sufit = stan + ilość na pozycji), zmianę zamrożonej ceny,
 * usunięcie pozycji i dodanie produktu w bieżącej cenie. Każda akcja zapisuje się
 * od razu (jak koszyk) i przelicza sumy przez OrderTotals. Cała mechanika stanu i
 * kwot siedzi w OrderEditor — tu tylko UI, autoryzacja i komunikaty błędów.
 *
 * Błędy są kierowane „w miejsce": błąd pozycji ląduje pod tą pozycją (podmienia
 * podpowiedź o stanie na czerwoną, bez skoku układu), a błąd dodawania — w ramce
 * „Dodaj produkt". Nie ma globalnego banera, więc strona nie podskakuje.
 */
class OrderEditor extends Component
{
    public Order $order;

    public bool $editing = false;

    /** Wybrany produkt do dodania (id z selecta). */
    public string $addProductId = '';

    /** Ilość dodawanego produktu (PL, przecinek dozwolony). */
    public string $addQuantity = '';

    /** Błędy przypięte do pozycji: [itemId => komunikat] (pokazywane pod pozycją). */
    public array $itemErrors = [];

    /** Błąd formularza „Dodaj produkt". */
    public ?string $addError = null;

    public function mount(Order $order): void
    {
        $this->order = $order;
    }

    public function toggleEditing(): void
    {
        // Anulowanego nie da się edytować (patrz OrderEditor::guardEditable) —
        // nie odsłaniamy kontrolek, które i tak odbiłyby się od serwisu.
        if (! $this->editable()) {
            return;
        }

        $this->editing = ! $this->editing;
        $this->reset('addProductId', 'addQuantity', 'itemErrors', 'addError');
    }

    /**
     * Czy zamówienie w ogóle wolno edytować. Serwis pilnuje tego twardo — tu
     * decydujemy tylko, czy pokazywać kontrolki.
     */
    public function editable(): bool
    {
        // Edycja zamówienia to funkcja pakietu Pawilon (`order_editing`). Bez niej
        // nie wchodzimy w tryb edycji, a i tak zablokuje ją guard w run().
        return $this->order->shop?->entitlement('order_editing') === true
            && ! $this->order->status->isTerminal()
            && ! $this->frozenByCashOnDelivery();
    }

    /**
     * Czy zamówienie zamroziło nadanie przesyłki pobraniowej. Kwota pobrania jest
     * wtedy u InPostu i nie da się jej zmienić, więc kontrolek nie pokazujemy —
     * serwis i tak by je odbił (patrz OrderEditor::guardEditable).
     */
    public function frozenByCashOnDelivery(): bool
    {
        return $this->order->delivery_method?->isCashOnDelivery() === true
            && $this->order->hasShipment();
    }

    /**
     * Status zmienił się w sąsiedniej karcie (osobny komponent). Gdy zamówienie
     * właśnie anulowano, trzeba natychmiast zwinąć tryb edycji — inaczej zostałby
     * odsłonięty formularz, który i tak odbije się od serwisu.
     */
    #[On('order-status-changed')]
    public function syncWithStatus(): void
    {
        $this->order->refresh();

        if (! $this->editable()) {
            $this->editing = false;
            $this->reset('addProductId', 'addQuantity', 'itemErrors', 'addError');
        }
    }

    public function incQuantity(int $itemId): void
    {
        $item = $this->item($itemId);

        $this->run(fn () => $this->service()->changeQuantity($item, (float) $item->quantity + $item->sale_unit->step()), $itemId);
    }

    public function decQuantity(int $itemId): void
    {
        $item = $this->item($itemId);
        $target = (float) $item->quantity - $item->sale_unit->step();

        // Poniżej minimum schodzi tylko „Usuń", nie „−".
        if ($target < $item->sale_unit->minQuantity()) {
            return;
        }

        $this->run(fn () => $this->service()->changeQuantity($item, $target), $itemId);
    }

    public function setQuantity(int $itemId, string $value): void
    {
        $item = $this->item($itemId);
        $qty = $this->parseNumber($value);

        if ($qty === null) {
            $this->itemErrors[$itemId] = 'Podaj poprawną ilość.';

            return;
        }

        $this->run(fn () => $this->service()->changeQuantity($item, $qty), $itemId);
    }

    public function setPrice(int $itemId, string $value): void
    {
        $item = $this->item($itemId);
        $price = $this->parseNumber($value);

        if ($price === null) {
            $this->itemErrors[$itemId] = 'Podaj poprawną cenę.';

            return;
        }

        $this->run(fn () => $this->service()->changePrice($item, $price), $itemId);
    }

    public function removeItem(int $itemId): void
    {
        $item = $this->item($itemId);

        $this->run(fn () => $this->service()->removeItem($item), $itemId);
    }

    public function addProduct(): void
    {
        $this->addError = null;
        $productId = (int) $this->addProductId;

        if ($productId <= 0) {
            $this->addError = 'Wybierz produkt.';

            return;
        }

        $qty = $this->parseNumber($this->addQuantity);

        if ($qty === null) {
            $this->addError = 'Podaj ilość produktu.';

            return;
        }

        if ($this->run(fn () => $this->service()->addProduct($this->order, $productId, $qty))) {
            $this->reset('addProductId', 'addQuantity');
        }
    }

    /**
     * Wykonuje operację edytora: autoryzacja, złapanie błędu biznesowego i
     * odświeżenie zamówienia do ponownego renderu. Błąd kierujemy pod pozycję
     * ($itemId) albo — gdy null — do formularza dodawania. Zwraca true przy sukcesie.
     */
    private function run(callable $action, ?int $itemId = null): bool
    {
        $this->authorizeOwnership();

        // Nie ufamy widokowi: żadna mutacja bez prawa do edycji (uprawnienie
        // pakietu + status niekońcowy). Blokuje też crafted-request z pominięciem
        // przełącznika „Edytuj zamówienie".
        if (! $this->editable()) {
            return false;
        }

        try {
            $action();
        } catch (OrderEditException $e) {
            if ($itemId !== null) {
                $this->itemErrors[$itemId] = $e->getMessage();
            } else {
                $this->addError = $e->getMessage();
            }

            return false;
        }

        if ($itemId !== null) {
            unset($this->itemErrors[$itemId]);
        }

        $this->order->refresh()->load('items');

        return true;
    }

    private function service(): OrderEditorService
    {
        return app(OrderEditorService::class);
    }

    /**
     * Pozycja należąca do TEGO zamówienia (twardo, po autoryzacji własności).
     */
    private function item(int $itemId): OrderItem
    {
        $this->authorizeOwnership();

        return $this->order->items()->findOrFail($itemId);
    }

    private function authorizeOwnership(): void
    {
        abort_unless($this->order->shop_id === auth()->user()?->currentShop()?->id, 403);
    }

    /**
     * Liczba z pola PL: spacje out, przecinek → kropka. Puste/nienumeryczne → null.
     */
    private function parseNumber(string $value): ?float
    {
        $normalized = str_replace([' ', ','], ['', '.'], trim($value));

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    public function render()
    {
        $this->order->loadMissing('items.product');

        // Sufit ilości per pozycja: stan + to, co już na niej (null = bez kontroli stanu).
        $maxQuantities = [];
        foreach ($this->order->items as $item) {
            $product = $item->product;
            $maxQuantities[$item->id] = ($product && $product->track_stock && $product->stock !== null)
                ? (float) $product->stock + (float) $item->quantity
                : null;
        }

        return view('livewire.seller.order-editor', [
            'items' => $this->order->items,
            'maxQuantities' => $maxQuantities,
            'products' => $this->editing ? $this->addableProducts() : new Collection,
        ]);
    }

    /**
     * Aktywne produkty sklepu do dodania (nazwa + cena + stan w opcji selecta).
     *
     * @return Collection<int, Product>
     */
    private function addableProducts(): Collection
    {
        return $this->order->shop->products()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
