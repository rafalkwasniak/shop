<?php

namespace App\Livewire\Seller;

use App\Enums\PanelSection;
use App\Exceptions\BulkMailException;
use App\Models\BulkMailing;
use App\Services\BulkMailService;
use Livewire\Component;

/**
 * Wysyłka wiadomości do klientów — cały cykl w jednym miejscu pod treścią:
 * próbka na własny adres (bez limitu), potwierdzenie w miejscu, a na końcu
 * jedna nieodwracalna wysyłka.
 *
 * Kolejność jest celowa: przycisk „Wyślij do klientów" stoi ZA próbką i
 * wymaga potwierdzenia, bo tej operacji nie da się cofnąć — maile wychodzą do
 * ludzi. Potwierdzenie mówi wprost, ilu klientów je dostanie i kiedy będzie
 * można wysłać kolejną wiadomość.
 */
class BulkMailingSender extends Component
{
    public BulkMailing $mailing;

    /** Czy pokazujemy potwierdzenie wysyłki do klientów. */
    public bool $confirming = false;

    public ?string $error = null;

    public ?string $sentMessage = null;

    public function mount(BulkMailing $mailing): void
    {
        $this->mailing = $mailing;
    }

    /**
     * Próbka na adres zalogowanego sprzedawcy. Bez limitu i bez wpływu na
     * karencję — po to jest, żeby sprawdzić treść tyle razy, ile trzeba.
     */
    public function sendTest(BulkMailService $mail): void
    {
        $this->authorizeOwnership();
        $this->reset(['error', 'sentMessage']);

        $user = auth()->user();

        try {
            $mail->sendTest($this->mailing, $user->email, $user->name);
        } catch (BulkMailException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->mailing->refresh();
        $this->sentMessage = 'Próbkę wysłaliśmy na '.$user->email.'. Powinna dotrzeć w ciągu minuty.';
    }

    public function askSend(): void
    {
        $this->authorizeOwnership();
        $this->reset(['error', 'sentMessage']);

        $this->confirming = true;
    }

    public function dismiss(): void
    {
        $this->confirming = false;
    }

    /**
     * Wysyłka do wszystkich klientów ze zgodą. Wszystkie reguły (uprawnienie,
     * karencja, jednorazowość, pusta lista) pilnuje serwis — tutaj tylko
     * pokazujemy jego komunikat, bo jest pisany do sprzedawcy.
     */
    public function send(BulkMailService $mail): void
    {
        $this->authorizeOwnership();
        $this->confirming = false;
        $this->reset(['error', 'sentMessage']);

        try {
            $count = $mail->send($this->mailing);
        } catch (BulkMailException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->mailing->refresh();
        $this->sentMessage = 'Wiadomość poszła do '.$count.' '.$this->declineRecipients($count).'.';
    }

    private function authorizeOwnership(): void
    {
        $user = auth()->user();

        // Sklep MUSI sie zgadzac, ale sam sklep nie wystarczy: zadanie do
        // `livewire/update` nie przechodzi przez `section:` z trasy, na ktorej
        // ten komponent sie renderowal. Bez drugiego warunku pracownik bez
        // tego dzialu obchodzilby brame, wolajac komponent wprost.
        abort_unless(
            $this->mailing->shop_id === $user?->currentShop()?->id
                && $user->canAccess(PanelSection::Marketing),
            403,
        );
    }

    /**
     * „1 klienta / 2 klientów" — odmiana rzeczownika po liczbie.
     */
    private function declineRecipients(int $count): string
    {
        return $count === 1 ? 'klienta' : 'klientów';
    }

    public function render()
    {
        $mail = app(BulkMailService::class);
        $shop = $this->mailing->shop;

        return view('livewire.seller.bulk-mailing-sender', [
            'recipients' => $mail->recipientsCount($shop),
            'blockedUntil' => $mail->nextAllowedAt($shop),
            // Postęp wysyłki — liczony z outboxu, bo kolejkę opróżnia cron
            // paczkami, a `sent_at` kampanii mówi tylko, kiedy ją zlecono.
            'total' => $this->mailing->messagesTotal(),
            'delivered' => $this->mailing->deliveredCount(),
            'failed' => $this->mailing->failedCount(),
            'delivering' => $this->mailing->isDelivering(),
        ]);
    }
}
