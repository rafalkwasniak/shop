@props(['count', 'total'])

{{-- Dwa kafelki zamówień klienta: liczba i łączna wartość. Wspólne dla strony
     „Moje konto" i listy zamówień (spójność z kafelkami punktów — Rafał 07.10).
     Liczby bez anulowanych — te nie są zakupem (scope `countedAsSale`). --}}
<div {{ $attributes->class('grid gap-4 sm:grid-cols-2') }}>
    <div class="st-card st-border rounded-2xl border p-5">
        <p class="text-xs uppercase tracking-wide opacity-50">Złożone zamówienia</p>
        <p class="mt-1 text-3xl font-bold tabular-nums">{{ $count }}</p>
    </div>
    <div class="st-card st-border rounded-2xl border p-5">
        <p class="text-xs uppercase tracking-wide opacity-50">Łączna wartość zamówień</p>
        <p class="mt-1 text-3xl font-bold tabular-nums">{{ \App\Support\Money::pln($total) }}</p>
    </div>
</div>
