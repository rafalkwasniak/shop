<x-storefront.account-shell :shop="$shop" active="orders" heading="Zamówienia" :crumbs="[
    ['label' => $shop->name, 'url' => '/'],
    ['label' => 'Moje konto', 'url' => '/moje-konto'],
    ['label' => 'Zamówienia'],
]">
    <x-storefront.order-stats :count="$ordersCount" :total="$totalSpent" />

    {{-- Nagłówek „Historia" jak w zakładce Punkty: kafelki → nagłówek → lista. --}}
    <h2 class="st-brand st-box-title mt-8">Historia</h2>

    @if ($orders->isEmpty())
        <div class="st-card st-border mt-3 rounded-3xl border p-10 text-center">
            <p class="opacity-70">Nie masz jeszcze zamówień.</p>
            <a href="/produkty" wire:navigate class="st-brand mt-2 inline-block text-sm underline underline-offset-2">Przejdź do produktów</a>
        </div>
    @else
        <ul class="mt-3 space-y-3">
            @foreach ($orders as $order)
                <li>
                    <a href="/moje-konto/zamowienia/{{ $order->id }}?powrot={{ urlencode(request()->getRequestUri()) }}" wire:navigate
                        class="st-card st-border flex flex-wrap items-center justify-between gap-4 rounded-2xl border p-4 transition hover:brightness-[0.98]">
                        <div>
                            <span class="font-semibold">Zamówienie #{{ $order->number }}</span>
                            <span class="block text-xs opacity-60">{{ $order->created_at->format('d.m.Y') }} · {{ $order->items_count }} poz.</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-1.5">
                                @if ($order->hasInvoice())
                                    {{-- Plakietka „FV" (marker) — to zamówienie ma wystawioną fakturę. --}}
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800" title="Faktura VAT wystawiona">FV</span>
                                @endif
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $order->status->badgeClasses() }}">{{ $order->status->label() }}</span>
                            </div>
                            <span class="font-bold tabular-nums">{{ \App\Support\Money::pln($order->total_gross) }}</span>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($orders->hasPages())
            <div class="mt-8">
                {{ $orders->onEachSide(1)->links('storefront.pagination') }}
            </div>
        @endif
    @endif
</x-storefront.account-shell>
