@props(['order', 'showNet' => true])

{{-- Rachunek zamówienia na storefroncie: rabat, dostawa, suma. Jeden komponent
     dla potwierdzenia zakupu, konta klienta i strony płatności — te trzy widoki
     miały wcześniej trzy kopie tego bloku, więc rabat trzeba by było dodawać
     trzy razy (i trzy razy o nim zapomnieć).

     Rabat pokazujemy Z NAZWĄ KODU i zawsze nad dostawą: klient ma widzieć, że
     zniżka zeszła z produktów, a nie z wysyłki. Punkty za zakupy — osobnym
     wierszem pod rabatem, tak samo jak w koszyku i kasie. --}}

@php($hasCode = (float) $order->discount_amount > 0)
@php($hasPoints = (float) $order->points_discount > 0)
@php($hasDiscount = $hasCode || $hasPoints)
@php($shipped = $order->delivery_method->isShipped())
@php($freeShipping = $shipped && (float) $order->delivery_cost <= 0)

@if ($hasDiscount)
    <div class="st-border mt-4 space-y-2 border-t pt-4 text-sm">
        <div class="flex items-baseline justify-between">
            <span class="opacity-70">Produkty</span>
            <span class="shrink-0 tabular-nums opacity-70">{{ \App\Support\Money::pln($order->items_total) }}</span>
        </div>
        @if ($hasCode)
            <div class="flex items-baseline justify-between gap-3">
                <span class="min-w-0 opacity-70">
                    Rabat @if (filled($order->discount_code))<span class="font-semibold uppercase tracking-wide break-words">{{ $order->discount_code }}</span>@endif
                </span>
                <span class="shrink-0 font-semibold tabular-nums">−{{ \App\Support\Money::pln($order->discount_amount) }}</span>
            </div>
        @endif
        @if ($hasPoints)
            <div class="flex items-baseline justify-between gap-3">
                <span class="min-w-0 opacity-70">Punkty</span>
                <span class="shrink-0 font-semibold tabular-nums">−{{ \App\Support\Money::pln($order->points_discount) }}</span>
            </div>
        @endif
    </div>
@endif

@if ($shipped)
    <div class="st-border flex items-baseline justify-between text-sm {{ $hasDiscount ? 'mt-2' : 'mt-4 border-t pt-4' }}">
        <span class="opacity-70">Dostawa</span>
        <span class="shrink-0 tabular-nums">{{ $freeShipping ? 'Gratis' : \App\Support\Money::pln($order->delivery_cost) }}</span>
    </div>
@endif

<div class="st-border flex items-baseline justify-between {{ $shipped || $hasDiscount ? 'mt-3' : 'mt-4 border-t pt-4' }}">
    <span class="opacity-70">Razem (brutto)</span>
    <span class="text-xl font-bold tabular-nums">{{ \App\Support\Money::pln($order->total_gross) }}</span>
</div>

@if ($showNet)
    <p class="mt-1 text-right text-xs opacity-60">{{ \App\Support\Money::pln($order->total_net) }} netto</p>
@endif
