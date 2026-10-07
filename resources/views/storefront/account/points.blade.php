<x-storefront.account-shell :shop="$shop" active="points" heading="Punkty" :crumbs="[
    ['label' => $shop->name, 'url' => '/'],
    ['label' => 'Moje konto', 'url' => '/moje-konto'],
    ['label' => 'Punkty'],
]">
    @php($pointValue = $shop->loyaltyPointValue())

    {{-- Saldo, oczekujące, najbliższe wygaśnięcie --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="st-card st-border rounded-2xl border p-5">
            <p class="text-xs uppercase tracking-wide opacity-50">Do wykorzystania</p>
            <p class="mt-1 text-3xl font-bold tabular-nums {{ $balance < 0 ? 'text-rose-700' : '' }}">{{ $balance }} pkt</p>
            <p class="mt-0.5 text-sm opacity-60">{{ \App\Support\Money::pln(max(0, $balance) * $pointValue) }} rabatu</p>
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

    @if ($balance < 0)
        <div class="st-card st-border mt-4 rounded-xl border p-4 text-sm">
            Saldo jest ujemne, bo zwrócone zostały produkty, za które punkty były już wydane. Wyrówna się punktami z kolejnych zakupów.
        </div>
    @endif

    <p class="mt-4 text-sm opacity-70">
        Punkty wykorzystasz w koszyku po zalogowaniu.
        @if ($rulesPage)
            <a href="{{ $rulesPage->storefrontPath() }}" wire:navigate class="st-brand underline underline-offset-2">Zasady punktów</a>
        @endif
    </p>

    {{-- Historia --}}
    <div class="mt-8">
        <h2 class="st-brand st-box-title">Historia</h2>

        @if ($history->isEmpty())
            <div class="st-card st-border mt-3 rounded-3xl border p-10 text-center">
                <p class="opacity-70">Nie masz jeszcze punktów. Pojawią się po pierwszym zrealizowanym zamówieniu.</p>
            </div>
        @else
            <ul class="mt-3 space-y-3">
                @foreach ($history as $entry)
                    <li class="st-card st-border flex flex-wrap items-center justify-between gap-4 rounded-2xl border p-4">
                        <div class="min-w-0">
                            <span class="font-semibold">{{ $entry->type->label() }}</span>
                            @if ($entry->order)
                                · <a href="/moje-konto/zamowienia/{{ $entry->order->id }}" wire:navigate class="underline underline-offset-2">zamówienie #{{ $entry->order->number }}</a>
                            @endif
                            <span class="block text-xs opacity-60">
                                {{ $entry->created_at->format('d.m.Y') }}
                                @if ($entry->note)
                                    · {{ $entry->note }}
                                @endif
                                @if ($entry->points > 0 && $entry->available_at)
                                    @if ($entry->available_at->isFuture())
                                        · do wykorzystania od {{ $entry->available_at->format('d.m.Y') }}
                                    @endif
                                    @if ($entry->expires_at)
                                        · ważne do {{ $entry->expires_at->format('d.m.Y') }}
                                    @endif
                                @endif
                            </span>
                        </div>
                        <span class="whitespace-nowrap font-bold tabular-nums {{ $entry->points >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $entry->points > 0 ? '+' : '' }}{{ $entry->points }} pkt</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-storefront.account-shell>
