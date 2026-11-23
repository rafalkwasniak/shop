@php use App\Enums\OrderStatus; @endphp
<x-layouts.storefront :shop="$shop" title="Płatność za zamówienie" :noindex="true">
    <main class="mx-auto max-w-2xl px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Płatność za zamówienie #'.$order->number],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">Płatność za zamówienie #{{ $order->number }}</h1>

        {{-- Podsumowanie kwotowe (pozycje + razem) — bez danych osobowych, bo link
             po tokenie może krążyć; kupujący i tak rozpozna zamówienie po numerze. --}}
        <div class="st-card st-border mt-8 rounded-3xl border p-6">
            <h2 class="st-brand st-box-title">Podsumowanie</h2>
            <ul class="mt-4 space-y-3">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-3 text-sm">
                        <span class="opacity-80">{{ $item->sale_unit->formatQuantity((float) $item->quantity) }} × {{ $item->name }}</span>
                        <span class="shrink-0 tabular-nums">{{ \App\Support\Money::pln($item->line_total_gross) }}</span>
                    </li>
                @endforeach
            </ul>
            {{-- Bez linii „netto": kupujący na stronie płatności ma widzieć kwotę
                 do zapłaty, nie rozbicie podatkowe. --}}
            <x-storefront.order-totals :order="$order" :show-net="false" />
        </div>

        {{-- Akcja: dopóki zamówienie czeka na wpłatę — przycisk do bramki (jeden,
             gdy działa bramka z kasy; kilka, gdy sprzedawca ją wyłączył, a ma
             inne); gdy opłacone (webhook potwierdził) — ptaszek; anulowane —
             informacja. --}}
        <div class="mt-6">
            @if ($order->isAwaitingOnlinePayment())
                <div class="st-card st-border rounded-3xl border p-6 text-center">
                    @if (session('error'))
                        <p class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</p>
                    @endif
                    <p class="font-semibold">To zamówienie czeka na opłacenie</p>
                    @if ($providers === [])
                        <p class="mt-1 text-sm opacity-70">Płatność online jest w tym sklepie chwilowo niedostępna. Skontaktuj się ze sklepem, aby ustalić sposób zapłaty.</p>
                    @else
                        <p class="mt-1 text-sm opacity-70">Zapłać online (BLIK, karta lub szybki przelew), aby sklep mógł rozpocząć realizację.</p>
                        <div class="mt-5 flex flex-wrap justify-center gap-3">
                            @foreach ($providers as $provider)
                                <form method="POST" action="/platnosc/{{ $order->paymentToken() }}">
                                    @csrf
                                    <input type="hidden" name="provider" value="{{ $provider->value }}">
                                    <button type="submit" class="st-btn inline-flex items-center gap-2 rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">
                                        Zapłać przez {{ $provider->label() }} — {{ \App\Support\Money::pln($order->total_gross) }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @endif
                    <p class="mt-3 text-xs opacity-60">Jeśli właśnie zapłacono, potwierdzenie może chwilę potrwać — odśwież stronę za moment.</p>
                </div>
            @elseif ($order->status === OrderStatus::Cancelled)
                <div class="st-card st-border rounded-3xl border p-6 text-center">
                    <p class="font-semibold">Zamówienie zostało anulowane</p>
                    <p class="mt-1 text-sm opacity-70">Tego zamówienia nie można już opłacić.</p>
                </div>
            @else
                <div class="st-card st-border rounded-3xl border p-6 text-center">
                    <p class="font-semibold text-emerald-600">✓ Płatność potwierdzona</p>
                    <p class="mt-1 text-sm opacity-70">Dziękujemy! Zamówienie #{{ $order->number }} jest opłacone i trafiło do realizacji.</p>
                    <a href="/" wire:navigate class="st-btn mt-5 inline-block rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">Wróć do sklepu</a>
                </div>
            @endif
        </div>
    </main>
</x-layouts.storefront>
