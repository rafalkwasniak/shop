@props(['shop', 'active' => 'overview', 'heading' => 'Moje konto', 'crumbs' => [], 'back' => null])

{{-- Wspólna skorupa panelu klienta „Moje konto". Pełną szerokością renderuje
     nagłówek strony (breadcrumbs → H1 → linia), a pod linią dwie kolumny: lewe
     submenu (sticky) i prawą treść z `$slot`. Konwencja „tytuł = końcówka
     breadcrumbs" — `$heading` powinno równać się etykiecie ostatniego okruszka.
     `$active`: 'overview' | 'orders' | 'points' | 'data'. Na mobile menu składa się nad
     treść. Flash `session('status')` renderujemy raz, tutaj. --}}
@php
    $menu = [
        ['key' => 'overview', 'label' => 'Moje konto', 'url' => '/moje-konto'],
        ['key' => 'orders', 'label' => 'Zamówienia', 'url' => '/moje-konto/zamowienia'],
        // Punkty tylko, gdy sklep je nalicza albo klient ma historię (po wyłączeniu
        // zebrane punkty dalej da się wydać, więc saldo musi być widoczne).
        ...(($customer = auth('customer')->user()) && app(\App\Services\LoyaltyLedger::class)->visibleFor($shop, $customer->email)
            ? [['key' => 'points', 'label' => 'Punkty', 'url' => '/moje-konto/punkty']]
            : []),
        ['key' => 'data', 'label' => 'Edycja danych', 'url' => '/moje-konto/dane'],
    ];
    // Offset przyklejenia lewego menu = wysokość nagłówka-winiety (sticky top-0) +
    // ODSTĘP, żeby menu zatrzymywało się z powietrzem pod belką, a nie stykało
    // się z nią krawędziami. Jedyna zmienna wysokości winiety to obecność logo
    // (h-28). MUSI być zgodne z components/storefront/information-shell — obie
    // skorupy stoją obok siebie w nawigacji i różnica byłaby widoczna. Inline
    // `top` działa tylko przy lg:sticky (na mobile bez echa).
    $stickyTop = filled($shop->logo_path) ? '14.5rem' : '10rem';
@endphp

<x-layouts.storefront :shop="$shop" :title="$heading" :noindex="true">
    <main class="mx-auto max-w-6xl px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="$crumbs" :back="$back" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">{{ $heading }}</h1>

        <div class="st-border mt-8 grid gap-8 border-t pt-8 lg:grid-cols-[220px_minmax(0,1fr)]">
            {{-- Lewe submenu --}}
            <aside class="lg:sticky lg:self-start" style="top: {{ $stickyTop }};">
                <nav class="flex flex-col gap-1 text-sm font-medium">
                    @foreach ($menu as $item)
                        <a href="{{ $item['url'] }}" wire:navigate
                            @class([
                                'rounded-xl px-4 py-2.5 transition',
                                'st-btn' => $active === $item['key'],
                                'st-border border hover:brightness-95' => $active !== $item['key'],
                            ])>{{ $item['label'] }}</a>
                    @endforeach
                    <form method="POST" action="/wyloguj" class="mt-1">
                        @csrf
                        <button type="submit"
                            class="st-brand w-full rounded-xl px-4 py-2.5 text-left font-medium transition hover:brightness-95">Wyloguj mnie</button>
                    </form>
                </nav>
            </aside>

            {{-- Prawa kolumna --}}
            <div class="min-w-0">
                @if (session('status'))
                    <div class="st-card st-border mb-6 rounded-xl border p-4 text-sm">{{ session('status') }}</div>
                @endif

                {{ $slot }}
            </div>
        </div>
    </main>
</x-layouts.storefront>
