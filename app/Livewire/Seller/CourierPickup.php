<?php

namespace App\Livewire\Seller;

use App\Models\DispatchOrder;
use App\Services\Shipping\CourierPickup as CourierPickupService;
use Livewire\Component;

/**
 * „Zamów kuriera po odbiór" — zaznacz paczki, zamów JEDEN przyjazd.
 *
 * Ekran istnieje, bo dopłata u InPostu jest za PRZYJAZD, a nie za paczkę:
 * sprzedawca z ruchem nadaje przez cały dzień, a wieczorem zamawia kuriera na
 * wszystko naraz. Zamawianie per paczka byłoby po prostu drogie.
 *
 * Trafiają tu wyłącznie przesyłki nadane z wyborem „Odbierze je kurier InPost".
 * Deklaracja zapada przy nadaniu i jest nieodwracalna — paczki zadeklarowanej
 * do wrzucenia w paczkomacie kurier nie odbierze, a InPost odrzuca wtedy CAŁE
 * zlecenie, nie pojedynczą pozycję.
 */
class CourierPickup extends Component
{
    /** @var array<int, int> */
    public array $selected = [];

    public string $comment = '';

    public bool $confirming = false;

    public function mount(): void
    {
        // Domyślnie zaznaczamy wszystko: „zamawiam kuriera po to, co dziś
        // nadałem" jest tu regułą, a nie wyjątkiem.
        $this->selected = $this->awaiting()->pluck('id')->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order>
     */
    public function awaiting()
    {
        $shop = auth()->user()?->currentShop();

        return $shop === null
            ? new \Illuminate\Database\Eloquent\Collection
            : app(CourierPickupService::class)->awaiting($shop);
    }

    public function ask(): void
    {
        if ($this->selected === []) {
            return;
        }

        $this->confirming = true;
    }

    public function dismiss(): void
    {
        $this->confirming = false;
    }

    public function request(CourierPickupService $pickup): void
    {
        $shop = auth()->user()?->currentShop();
        abort_if($shop === null, 403);

        $this->confirming = false;

        $dispatchOrder = $pickup->request($shop, array_map('intval', $this->selected), $this->comment ?: null);

        if ($dispatchOrder === null) {
            session()->flash('pickup_error', 'Nie udało się zamówić kuriera. Sprawdź adres sklepu w Ustawieniach i spróbuj ponownie.');

            return;
        }

        $this->comment = '';
        $this->selected = $this->awaiting()->pluck('id')->all();

        session()->flash('pickup_success', 'Zamówiliśmy kuriera. InPost potwierdzi zlecenie w ciągu chwili.');
    }

    public function render()
    {
        $shop = auth()->user()?->currentShop();
        $awaiting = $this->awaiting();

        return view('livewire.seller.courier-pickup', [
            'awaiting' => $awaiting,
            // Ostatnie zlecenia — sprzedawca musi zobaczyć, czy kurier faktycznie
            // przyjedzie. Odrzucenie przychodzi z opóźnieniem, więc sam fakt
            // kliknięcia niczego nie gwarantuje.
            'recent' => $shop === null
                ? collect()
                : DispatchOrder::where('shop_id', $shop->id)->latest('id')->limit(5)->get(),
            'pickupAddress' => $shop?->pickupAddress(),
        ]);
    }
}
