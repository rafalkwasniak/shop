@php use App\Enums\PaymentMethod; use App\Enums\DeliveryMethod; use App\Enums\OrderStatus; @endphp
<x-layouts.storefront :shop="$shop" title="Dziękujemy za zamówienie" :noindex="true">
    <main class="mx-auto max-w-6xl px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Zamówienie #'.$order->number],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">Dziękujemy za zamówienie</h1>

        <div class="st-border mt-8 border-t pt-8">
            {{-- Potwierdzenie: ptaszek + numer zamówienia --}}
            <div class="st-card st-border rounded-3xl border p-8 text-center">
                <div class="st-btn mx-auto flex h-14 w-14 items-center justify-center rounded-full">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-7 w-7" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                </div>
                <p class="mt-5 text-lg font-semibold">Zamówienie zostało złożone!</p>
                <p class="mt-2 opacity-70">Numer zamówienia: <span class="st-brand font-bold">#{{ $order->number }}</span></p>
                <p class="mt-1 text-sm opacity-70">Potwierdzenie wysłaliśmy na {{ $order->buyer_email }}.</p>
            </div>
        </div>

        {{-- Płatność online: dopóki zamówienie czeka na wpłatę, kupujący kończy ją
             przyciskiem stąd (płatność inicjujemy z podsumowania, nie auto-redirectem
             z kasy). Gdy webhook potwierdzi wpłatę, zamiast przycisku widać ptaszek.
             Powrót z bramki płatności wraca właśnie tutaj. --}}
        @if ($order->payment_method === PaymentMethod::Online)
            <div class="mt-6">
                @if ($order->status === OrderStatus::AwaitingPayment)
                    <div class="st-card st-border rounded-3xl border p-6 text-center">
                        <p class="font-semibold">Twoje zamówienie czeka na opłacenie</p>
                        <p class="mt-1 text-sm opacity-70">Zapłać online (BLIK, karta lub szybki przelew), aby sklep mógł rozpocząć realizację.</p>
                        {{-- Kupujący dopiero co widział podsumowanie (ten ekran), więc idziemy
                             PROSTO do bramki (POST = akcja płatności), bez pośredniej strony.
                             Ta sama trasa niesie tokenowa strona z maila/„Moje konto". --}}
                        <form method="POST" action="/platnosc/{{ $order->paymentToken() }}" class="mt-5">
                            @csrf
                            <button type="submit" class="st-btn inline-flex items-center gap-2 rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">
                                Przejdź do płatności — {{ \App\Support\Money::pln($order->total_gross) }}
                            </button>
                        </form>
                        <p class="mt-3 text-xs opacity-60">Link do płatności wysłaliśmy też w mailu z potwierdzeniem — możesz zapłacić później.</p>
                    </div>
                @elseif ($order->status !== OrderStatus::Cancelled)
                    <div class="st-card st-border rounded-3xl border p-6 text-center">
                        <p class="font-semibold text-emerald-600">✓ Płatność potwierdzona</p>
                        <p class="mt-1 text-sm opacity-70">Dziękujemy! Zamówienie jest opłacone i trafiło do realizacji.</p>
                    </div>
                @endif
            </div>
        @endif

        <div class="mt-6 grid gap-6 md:grid-cols-2">
            {{-- Lewa: pozycje + sumy --}}
            <div class="st-card st-border rounded-3xl border p-6">
                <h2 class="font-semibold">Podsumowanie</h2>
                <ul class="mt-4 space-y-3">
                    @foreach ($order->items as $item)
                        <li class="flex justify-between gap-3 text-sm">
                            <span class="opacity-80">{{ $item->sale_unit->formatQuantity((float) $item->quantity) }} × {{ $item->name }}</span>
                            <span class="shrink-0 tabular-nums">{{ \App\Support\Money::pln($item->line_total_gross) }}</span>
                        </li>
                    @endforeach
                </ul>
                <x-storefront.order-totals :order="$order" />
            </div>

            {{-- Prawa: płatność + dostawa --}}
            <div class="space-y-6">
                <div class="st-card st-border rounded-3xl border p-6">
                    <h2 class="font-semibold">Płatność — {{ $order->paymentMethodLabel() }}</h2>
                    @if ($order->payment_method === PaymentMethod::BankTransfer)
                        <dl class="mt-4 space-y-1.5 text-sm">
                            @if ($shop->bankAccountHolderName())
                                <div class="flex justify-between gap-3"><dt class="opacity-60">Odbiorca</dt><dd class="text-right font-medium">{{ $shop->bankAccountHolderName() }}</dd></div>
                            @endif
                            <div class="flex justify-between gap-3"><dt class="opacity-60">Numer konta</dt><dd class="text-right font-mono">{{ $shop->formattedBankAccountNumber() }}</dd></div>
                            @if (filled($shop->bank_name))
                                <div class="flex justify-between gap-3"><dt class="opacity-60">Bank</dt><dd class="text-right">{{ $shop->bank_name }}</dd></div>
                            @endif
                            <div class="flex justify-between gap-3"><dt class="opacity-60">Tytuł przelewu</dt><dd class="text-right font-medium">Zamówienie #{{ $order->number }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="opacity-60">Kwota</dt><dd class="text-right font-bold">{{ \App\Support\Money::pln($order->total_gross) }}</dd></div>
                        </dl>
                    @elseif ($order->payment_method === PaymentMethod::Online)
                        <p class="mt-2 text-sm opacity-70">
                            {{ $order->status === OrderStatus::AwaitingPayment ? 'Oczekuje na płatność online (BLIK, karta lub szybki przelew).' : 'Opłacone online.' }}
                        </p>
                    @else
                        <p class="mt-2 text-sm opacity-70">Zapłacisz na miejscu przy odbiorze zamówienia.</p>
                    @endif
                </div>

                <div class="st-card st-border rounded-3xl border p-6">
                    <h2 class="font-semibold">Dostawa — {{ $order->delivery_method->label() }}</h2>
                    @if ($order->delivery_method === DeliveryMethod::Pickup)
                        <p class="mt-2 text-sm opacity-70">Odbiór pod adresem sklepu:</p>
                        <p class="mt-1 text-sm font-medium">
                            {{ $shop->street }} {{ $shop->building_number }}{{ $shop->apartment_number ? '/'.$shop->apartment_number : '' }},
                            {{ $shop->postal_code }} {{ $shop->city }}
                        </p>
                        <p class="mt-2 text-xs opacity-60">Poinformujemy Cię, gdy zamówienie będzie gotowe do odbioru.</p>
                    @elseif ($order->delivery_method->requiresShippingAddress())
                        <p class="mt-2 text-sm opacity-70">Wyślemy na adres:</p>
                        <p class="mt-1 text-sm font-medium">
                            {{ $order->ship_street }} {{ $order->ship_building_number }}{{ $order->ship_apartment_number ? '/'.$order->ship_apartment_number : '' }},
                            {{ $order->ship_postal_code }} {{ $order->ship_city }}
                        </p>
                        <p class="mt-2 text-xs opacity-60">Poinformujemy Cię, gdy zamówienie będzie gotowe do wysyłki.</p>
                    @elseif ($order->delivery_method->requiresParcelLocker())
                        <p class="mt-2 text-sm opacity-70">Wyślemy do paczkomatu:</p>
                        <p class="mt-1 text-sm font-medium">{{ $order->parcel_locker_code }}</p>
                        @if (filled($order->parcel_locker_address))
                            <p class="mt-0.5 text-sm opacity-70">{{ $order->parcel_locker_address }}</p>
                        @endif
                        <p class="mt-2 text-xs opacity-60">Poinformujemy Cię, gdy zamówienie będzie gotowe do wysyłki.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="mt-8 text-center">
            <a href="/" wire:navigate class="st-btn inline-block rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">Wróć do sklepu</a>
        </div>
    </main>
</x-layouts.storefront>
