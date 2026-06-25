<x-layouts.panel title="Pulpit administratora">
    <x-slot:actions>
        <button type="button" class="rounded-full bg-white/70 px-4 py-1.5 text-sm font-medium text-stone-600 backdrop-blur hover:bg-white">Ostatnie 30 dni</button>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Sklepy', '0', 'Brak aktywnych sklepów', '🛍️'],
            ['Sprzedawcy', (string) $sellersCount, 'Konta z rolą sprzedawcy', '👥'],
            ['Zamówienia (30 dni)', '0', 'Statystyka ruszy z pierwszym sklepem', '📦'],
            ['Przychód (30 dni)', '0 zł', 'Statystyka ruszy z pierwszą sprzedażą', '💰'],
        ] as [$label, $value, $hint, $icon])
            <div class="rounded-3xl border border-white/60 bg-white/70 p-5 backdrop-blur">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-stone-500">{{ $label }}</p>
                    <span class="text-lg">{{ $icon }}</span>
                </div>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-stone-900">{{ $value }}</p>
                <p class="mt-1 text-xs text-stone-400">{{ $hint }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur lg:col-span-2">
            <h2 class="font-semibold text-stone-900">Najnowsze sklepy</h2>
            <div class="mt-6 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 bg-white/40 px-6 py-12 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-stone-100 text-2xl">🛍️</span>
                <p class="mt-4 font-medium text-stone-700">Nie ma jeszcze żadnych sklepów</p>
                <p class="mt-1 max-w-sm text-sm text-stone-500">
                    Gdy sprzedawcy założą i opublikują swoje sklepy, pojawią się tutaj wraz ze statusem i pakietem.
                </p>
            </div>
        </div>

        <div class="rounded-3xl bg-gradient-to-br from-amber-500 to-rose-500 p-6 text-white shadow-lg shadow-rose-500/20">
            <h2 class="text-lg font-semibold">Pierwsze kroki</h2>
            <p class="mt-2 text-sm text-amber-50">Platforma jest gotowa. Zaproś pierwszych sprzedawców i obserwuj, jak rośnie.</p>
            <ul class="mt-6 space-y-3 text-sm">
                <li class="flex items-center gap-3 rounded-2xl bg-white/15 px-4 py-2.5 backdrop-blur">Skonfiguruj pakiety</li>
                <li class="flex items-center gap-3 rounded-2xl bg-white/15 px-4 py-2.5 backdrop-blur">Zaproś sprzedawców</li>
                <li class="flex items-center gap-3 rounded-2xl bg-white/15 px-4 py-2.5 backdrop-blur">Ustaw treści platformy</li>
            </ul>
        </div>
    </div>
</x-layouts.panel>
