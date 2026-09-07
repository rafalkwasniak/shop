<?php

namespace App\Livewire\Seller;

use App\Enums\PanelSection;
use App\Models\Order;
use App\Services\OrderMailer;
use Livewire\Component;

/**
 * Karta „Napisz do klienta" na szczególe zamówienia: wolny tekst od sprzedawcy,
 * który wychodzi do kupującego mailem w szacie sklepu, razem z pozycjami
 * zamówienia (wiadomość zwykle dotyczy któregoś z produktów).
 *
 * Komponent odpowiada wyłącznie za autoryzację, walidację i widok — treść maila
 * składa `OrderMailer`, a dostarcza cron opróżniający outbox. Wysyłka nigdy nie
 * dzieje się w locie, więc kliknięcie „Wyślij" tylko kolejkuje.
 */
class OrderMessenger extends Component
{
    public Order $order;

    public string $body = '';

    /** Kopia na skrzynkę sprzedawcy — domyślnie nie, żeby nie zaśmiecać poczty. */
    public bool $copyToMe = false;

    /** Potwierdzenie po wysłaniu; znika, gdy sprzedawca zacznie pisać kolejną. */
    public bool $sent = false;

    public function mount(Order $order): void
    {
        $this->order = $order;
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:5', 'max:2000'],
            'copyToMe' => ['boolean'],
        ];
    }

    /**
     * Czytelne nazwy pól w komunikatach walidacji — bez tego Laravel wstawia
     * surową nazwę właściwości („body"). Konwencja: interfejs po polsku.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'body' => 'treść wiadomości',
        ];
    }

    public function updatedBody(): void
    {
        $this->sent = false;
    }

    public function send(OrderMailer $mailer): void
    {
        $this->authorizeOwnership();

        $this->validate();

        $body = trim($this->body);

        $mailer->messageToCustomer($this->order, $body);

        if ($this->copyToMe) {
            $mailer->messageCopyToSeller($this->order, $body);
        }

        $this->body = '';
        $this->sent = true;
    }

    private function authorizeOwnership(): void
    {
        $user = auth()->user();

        // Sklep MUSI sie zgadzac, ale sam sklep nie wystarczy: zadanie do
        // `livewire/update` nie przechodzi przez `section:` z trasy, na ktorej
        // ten komponent sie renderowal. Bez drugiego warunku pracownik bez
        // tego dzialu obchodzilby brame, wolajac komponent wprost.
        abort_unless(
            $this->order->shop_id === $user?->currentShop()?->id
                && $user->canAccess(PanelSection::Orders),
            403,
        );
    }

    public function render()
    {
        return view('livewire.seller.order-messenger');
    }
}
