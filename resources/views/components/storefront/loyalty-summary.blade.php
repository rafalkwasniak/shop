@props(['balance', 'pending', 'nextExpiring' => null])

{{-- Trzy kafelki punktów klienta: do wykorzystania, oczekujące, najbliżej wygasa.
     Wspólne dla strony „Moje konto" i zakładki „Punkty", żeby oba widoki nie
     rozjechały się przy zmianie. SAME PUNKTY, bez wartości w zł (decyzja
     Rafała 07.10) — przelicznik jest w Zasadach, kwota rabatu dopiero w koszyku. --}}
<div {{ $attributes->class('grid gap-4 sm:grid-cols-3') }}>
    <div class="st-card st-border rounded-2xl border p-5">
        <p class="text-xs uppercase tracking-wide opacity-50">Do wykorzystania</p>
        <p class="mt-1 text-3xl font-bold tabular-nums {{ $balance < 0 ? 'text-rose-700' : '' }}">{{ $balance }} pkt</p>
        <p class="mt-0.5 text-sm opacity-60">do wydania w koszyku</p>
    </div>
    <div class="st-card st-border rounded-2xl border p-5">
        <p class="text-xs uppercase tracking-wide opacity-50">Oczekujące</p>
        <p class="mt-1 text-3xl font-bold tabular-nums">{{ $pending }} pkt</p>
        <p class="mt-0.5 text-sm opacity-60">wpadną po czasie na zwrot</p>
    </div>
    <div class="st-card st-border rounded-2xl border p-5">
        <p class="text-xs uppercase tracking-wide opacity-50">Najbliżej wygasa</p>
        @if ($nextExpiring)
            <p class="mt-1 text-3xl font-bold tabular-nums">{{ $nextExpiring->remaining }} pkt</p>
            <p class="mt-0.5 text-sm opacity-60">{{ $nextExpiring->expires_at->format('d.m.Y') }}</p>
        @else
            <p class="mt-1 text-3xl font-bold">—</p>
            <p class="mt-0.5 text-sm opacity-60">nic nie wygasa</p>
        @endif
    </div>
</div>
