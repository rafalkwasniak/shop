<?php

namespace App\Livewire\Administrator;

use App\Models\PackagePayment;
use App\Models\Shop;
use App\Services\PackagePaymentService;
use App\Support\Money;
use App\Support\PackageFeatures;
use App\Support\PackageUpgrade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Rejestracja wpłaty przyjętej POZA bramką — przelew na konto albo gotówka.
 *
 * Po co: dopóki pakiety sprzedaje się z ręki, przychód platformy liczony z
 * samych płatności Paynow pokazuje zero. Ten formularz wpisuje realne pieniądze
 * do TEGO SAMEGO rejestru co bramka, więc zestawienie mówi prawdę bez drugiego,
 * równoległego źródła liczb.
 *
 * Wpłata nie jest samą notatką — od razu USTAWIA pakiet i termin, dokładnie tak
 * jak zrobiłby to webhook po wpłacie online. Inaczej admin musiałby robić dwie
 * rzeczy w dwóch miejscach, a rozjazd między „zapłacił" a „ma pakiet" byłby
 * kwestią czasu.
 *
 * Kwotę i termin PODPOWIADAMY z tej samej wyceny, którą widzi sprzedawca
 * (`PackageUpgrade`), ale obie zostają do nadpisania: deal per klient jest
 * regułą, nie wyjątkiem.
 */
class PackagePaymentRecorder extends Component
{
    public string $shop_id = '';

    public string $target_package = '';

    /** Kwota BRUTTO (zł) — tyle, ile realnie wpłynęło. */
    public string $amount = '';

    /** Data wpłaty (Y-m-d) — data kasowa, po niej dzieli się przychód na lata. */
    public string $paid_at = '';

    public string $method = PackagePayment::METHOD_TRANSFER;

    /** Do kiedy pakiet jest opłacony (Y-m-d). */
    public string $new_ends_at = '';

    /** Numer faktury wystawionej POZA systemem (opcjonalnie). */
    public string $invoice_number = '';

    public string $note = '';

    public bool $notify = true;

    /**
     * Wystawienie faktury w Fakturowni. Domyślnie WYŁĄCZONE — konto nie ma
     * sandboxa, więc kliknięcie tworzy realny dokument, którego nie da się
     * cofnąć z panelu.
     */
    public bool $issue_invoice = false;

    public function mount(): void
    {
        $this->paid_at = now()->format('Y-m-d');
    }

    /**
     * Sklepy do wyboru. Te w drodze do usunięcia odpadają — przyjmowanie od
     * nich opłaty nie ma sensu, a na liście tylko przeszkadzają.
     *
     * @return Collection<int, Shop>
     */
    public function shops(): Collection
    {
        return Shop::query()
            ->whereNull('deletion_scheduled_at')
            ->with('owner')
            ->orderBy('name')
            ->get();
    }

    /**
     * Zmiana sklepu ustawia domyślny pakiet na ten, który sklep ma dziś —
     * najczęstszy przypadek to przedłużenie, nie przesiadka.
     */
    public function updatedShopId(): void
    {
        $shop = $this->selectedShop();

        if ($shop === null) {
            return;
        }

        $this->target_package = $shop->package ?? config('shop.default_package');
        $this->prefill();
    }

    public function updatedTargetPackage(): void
    {
        $this->prefill();
    }

    /**
     * Podpowiedź kwoty i terminu z wyceny sprzedawcy. Gdy wycena nic nie mówi
     * (zejście poza oknem odnowienia, pakiet darmowy), spadamy na cennik i rok
     * od obecnego terminu — pole ma być wypełnione czymś sensownym, a nie puste.
     */
    private function prefill(): void
    {
        $shop = $this->selectedShop();

        if ($shop === null || $this->target_package === '') {
            return;
        }

        $quote = PackageUpgrade::quote($shop, $this->target_package);

        $amount = $quote['amount'] > 0
            ? $quote['amount']
            : (float) config("shop.packages.{$this->target_package}.price_yearly", 0);

        $endsAt = $quote['new_ends_at']
            ?? ($shop->subscription_ends_at?->copy()->addYear() ?? now()->copy()->addYear());

        $this->amount = (string) (int) round($amount);
        $this->new_ends_at = $endsAt->format('Y-m-d');
    }

    private function selectedShop(): ?Shop
    {
        return $this->shop_id !== '' ? Shop::find($this->shop_id) : null;
    }

    /**
     * Podsumowanie „co się zmieni" dla kolumny bocznej — stan sklepu PRZED
     * zapisem zestawiony z tym, co zapis ustawi.
     *
     * Po co osobno, skoro dane są w formularzu: formularz mówi, co wpisuję, a nie
     * co z tego wyniknie. Zejście na tańszy pakiet WYŁĄCZA funkcje droższego, a
     * przedłużenie zostawia cenę indywidualną nietkniętą — obie rzeczy widać
     * dopiero po zestawieniu ze stanem obecnym i obie są nieodwracalne jednym
     * kliknięciem.
     *
     * @return array<string, mixed>|null
     */
    public function changeSummary(): ?array
    {
        $shop = $this->selectedShop();

        if ($shop === null || $this->target_package === '') {
            return null;
        }

        $targetPrice = (float) config("shop.packages.{$this->target_package}.price_yearly", 0);
        $isRenewal = $this->target_package === $shop->package;

        return [
            'shop' => $shop,
            'fromPackage' => $shop->packageName(),
            'toPackage' => config("shop.packages.{$this->target_package}.name", $this->target_package),
            'isRenewal' => $isRenewal,
            'fromEndsAt' => $shop->subscription_ends_at,
            // Data z pola, nie z wyceny — admin mógł ją nadpisać, a podsumowanie
            // ma mówić o tym, co zostanie zapisane.
            'toEndsAt' => rescue(fn () => Carbon::parse($this->new_ends_at), null, report: false),
            // Przy przedłużeniu cena sklepu zostaje — patrz komentarz w `apply()`.
            'priceAfter' => $isRenewal ? (float) $shop->priceYearly() : $targetPrice,
            'isDownsize' => ! $isRenewal && $targetPrice < (float) $shop->priceYearly(),
            'comped' => (bool) $shop->comped,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'shop_id' => ['required', 'exists:shops,id'],
            // `purchasable()`, nie caly cennik: wplate mozna zarejestrowac
            // wylacznie za pakiet, ktory jest w OFERCIE. „Sklep dedykowany" ma
            // cene zero i nie jest sprzedawany przez platforme, wiec wplata za
            // niego nie istnieje — a wybor z listy przypisywalby sklepowi preset
            // z uprawnieniami bez limitow.
            'target_package' => ['required', 'string', 'in:'.implode(',', array_keys(PackageFeatures::purchasable()))],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            // Wpłata z przyszłości to zawsze pomyłka w dacie.
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', 'in:'.implode(',', array_keys(PackagePayment::manualMethods()))],
            // Termin z przeszłości COFNĄŁBY abonament działającego sklepu — a
            // rejestracja wpłaty ma go przedłużać, nie gasić.
            'new_ends_at' => ['required', 'date', 'after:today'],
            'invoice_number' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'shop_id' => 'sklep',
            'target_package' => 'pakiet',
            'amount' => 'kwota',
            'paid_at' => 'data wpłaty',
            'method' => 'sposób wpłaty',
            'new_ends_at' => 'opłacony do',
            'invoice_number' => 'numer faktury',
            'note' => 'notatka',
        ];
    }

    public function save(PackagePaymentService $payments): void
    {
        $this->validate();

        $shop = Shop::findOrFail($this->shop_id);

        $payment = $payments->record($shop, [
            'target_package' => $this->target_package,
            'amount' => (float) $this->amount,
            'method' => $this->method,
            // Godzina wpłaty nie jest nam znana, a północ byłaby myląca na
            // styku dnia — bierzemy koniec dnia, tak jak przy terminach.
            'paid_at' => Carbon::parse($this->paid_at)->endOfDay(),
            'new_ends_at' => Carbon::parse($this->new_ends_at)->endOfDay(),
            'invoice_number' => $this->invoice_number !== '' ? $this->invoice_number : null,
            'note' => $this->note !== '' ? $this->note : null,
            'recorded_by' => auth()->id(),
        ], notify: $this->notify, issueInvoice: $this->issue_invoice);

        session()->flash('success', 'Zapisano wpłatę '.Money::pln($payment->amount)
            .' dla sklepu „'.$shop->name.'". Pakiet jest opłacony do '.$payment->new_ends_at->format('d.m.Y').'.');

        $this->redirect(route('administrator.packages.payments'), navigate: false);
    }

    public function render()
    {
        return view('livewire.administrator.package-payment-recorder', [
            'shops' => $this->shops(),
            'packages' => PackageFeatures::purchasable(),
            'methods' => PackagePayment::manualMethods(),
            'summary' => $this->changeSummary(),
        ]);
    }
}
