<x-layouts.panel title="Pulpit sprzedawcy">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Produkty', '0', 'Dodaj pierwszy produkt', '🏷️'],
            ['Zamówienia (30 dni)', '0', 'Czekają na pierwszych klientów', '📦'],
            ['Przychód (30 dni)', '0 zł', 'Pierwsza sprzedaż przed Tobą', '💰'],
            ['Wyświetlenia (30 dni)', '0', 'Statystyka ruszy po publikacji', '👁️'],
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
        {{-- Onboarding "5 minut" --}}
        <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur lg:col-span-2">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-stone-900">Uruchom swój sklep w 5 minut</h2>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">0 / 4</span>
            </div>
            <p class="mt-1 text-sm text-stone-500">Cztery kroki dzielą Cię od pierwszej sprzedaży.</p>

            <ol class="mt-6 space-y-3">
                @foreach ([
                    ['Uzupełnij dane sklepu', 'Nazwa, opis i dane kontaktowe.'],
                    ['Dodaj pierwszy produkt', 'Zdjęcie, cena, krótki opis.'],
                    ['Wybierz wygląd', 'Kolory i układ strony głównej.'],
                    ['Opublikuj sklep', 'Udostępnij klientom swój adres.'],
                ] as $i => [$step, $desc])
                    <li class="flex items-start gap-4 rounded-2xl bg-white/60 px-4 py-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-stone-300 text-sm font-medium text-stone-500">{{ $i + 1 }}</span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-stone-900">{{ $step }}</p>
                            <p class="text-xs text-stone-500">{{ $desc }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>

            <button type="button" class="mt-6 w-full rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 sm:w-auto sm:px-6">
                Rozpocznij konfigurację
            </button>
        </div>

        {{-- Status sklepu --}}
        <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
            <h2 class="font-semibold text-stone-900">Twój sklep</h2>
            <div class="mt-6 flex flex-col items-center justify-center text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">🛍️</span>
                <p class="mt-4 font-medium text-stone-700">Sklep w przygotowaniu</p>
                <p class="mt-1 text-sm text-stone-500">Nie jest jeszcze widoczny dla klientów.</p>
                <span class="mt-4 rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-500">Szkic</span>
            </div>
        </div>
    </div>
</x-layouts.panel>
