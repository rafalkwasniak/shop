<x-storefront.account-shell :shop="$shop" active="overview" heading="Moje konto" :crumbs="[
    ['label' => $shop->name, 'url' => '/'],
    ['label' => 'Moje konto'],
]">
    {{-- Dane klienta --}}
    <div class="st-card st-border rounded-3xl border p-6">
        @php
            $greetName = \App\Support\Vocative::of($customer->name);
        @endphp
        <h2 class="st-brand st-box-title">Cześć{{ $greetName ? ', '.$greetName : '' }}!</h2>
        <dl class="mt-4 grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
            <div class="flex justify-between gap-3 sm:block">
                <dt class="opacity-60">Imię i nazwisko</dt>
                <dd class="font-medium sm:mt-0.5">{{ trim($customer->name.' '.$customer->surname) ?: '—' }}</dd>
            </div>
            <div class="flex justify-between gap-3 sm:block">
                <dt class="opacity-60">E-mail</dt>
                <dd class="font-medium sm:mt-0.5">{{ $customer->email }}</dd>
            </div>
            @if ($customer->phone)
                <div class="flex justify-between gap-3 sm:block">
                    <dt class="opacity-60">Telefon</dt>
                    <dd class="font-medium sm:mt-0.5">{{ $customer->phone }}</dd>
                </div>
            @endif
        </dl>
    </div>

    {{-- Statystyki --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <div class="st-card st-border rounded-2xl border p-5">
            <p class="text-xs uppercase tracking-wide opacity-50">Złożone zamówienia</p>
            <p class="mt-1 text-3xl font-bold tabular-nums">{{ $ordersCount }}</p>
        </div>
        <div class="st-card st-border rounded-2xl border p-5">
            <p class="text-xs uppercase tracking-wide opacity-50">Łączna wartość zamówień</p>
            <p class="mt-1 text-3xl font-bold tabular-nums">{{ \App\Support\Money::pln($totalSpent) }}</p>
        </div>
    </div>

    {{-- Punkty za zakupy — te same kafelki co w zakładce „Punkty"; bez odnośnika,
         bo zakładka jest w menu po lewej. --}}
    @if ($loyalty !== null)
        <x-storefront.loyalty-summary class="mt-4" :balance="$loyalty['balance']" :pending="$loyalty['pending']" :next-expiring="$loyalty['nextExpiring']" />
    @endif

    {{-- Ostatnie zamówienie --}}
    @if ($lastOrder)
        <div class="mt-8">
            <h2 class="st-brand st-box-title">Ostatnie zamówienie</h2>
            <a href="/moje-konto/zamowienia/{{ $lastOrder->id }}" wire:navigate
                class="st-card st-border mt-3 flex flex-wrap items-center justify-between gap-4 rounded-2xl border p-4 transition hover:brightness-[0.98]">
                <div>
                    <span class="font-semibold">Zamówienie #{{ $lastOrder->number }}</span>
                    <span class="block text-xs opacity-60">{{ $lastOrder->created_at->format('d.m.Y') }} · {{ $lastOrder->items_count }} poz.</span>
                </div>
                <div class="flex items-center gap-4">
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $lastOrder->status->badgeClasses() }}">{{ $lastOrder->status->label() }}</span>
                    <span class="font-bold tabular-nums">{{ \App\Support\Money::pln($lastOrder->total_gross) }}</span>
                </div>
            </a>
        </div>
    @else
        <div class="st-card st-border mt-8 rounded-3xl border p-10 text-center">
            <p class="opacity-70">Nie masz jeszcze żadnych zamówień.</p>
            <a href="/produkty" wire:navigate class="st-brand mt-2 inline-block text-sm underline underline-offset-2">Przejdź do produktów</a>
        </div>
    @endif

    {{-- Usunięcie konta (RODO) — cichy „danger zone" na dole --}}
    <div class="mt-12 rounded-3xl border border-rose-300 p-6">
        <h2 class="st-box-title text-rose-700">Usuń konto</h2>
        <p class="mt-1 max-w-xl text-sm opacity-70">
            Trwale usuniemy Twoje konto i dane profilu. Złożone zamówienia pozostaną u sprzedawcy
            (na potrzeby rozliczeń), ale nie będą już powiązane z kontem. Tej operacji nie można cofnąć.
        </p>
        <form method="POST" action="/moje-konto/usun" class="mt-4"
            onsubmit="return confirm('Na pewno usunąć konto? Tej operacji nie można cofnąć.');">
            @csrf
            <button type="submit" class="rounded-xl border border-rose-400 px-5 py-2.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">Usuń konto na zawsze</button>
        </form>
    </div>
</x-storefront.account-shell>
