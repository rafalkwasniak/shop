<x-layouts.panel title="Klienci">
    <x-slot:heading>Klienci</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: wykaz klientów ze stronicowaniem --}}
        <div class="lg:col-span-8">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-semibold text-stone-900">Twoi klienci</h2>
                    {{-- Sortowanie stoi przy liście (jak w Zamówieniach), nie w filtrach.
                         Hidden pola niosą aktywne filtry, żeby zmiana sortowania ich nie
                         gubiła; brak `page` zeruje paginację do pierwszej strony. --}}
                    @if ($total > 0)
                        <form method="GET" action="{{ route('seller.customers.index') }}" class="flex items-center gap-2">
                            @if ($search !== '')<input type="hidden" name="szukaj" value="{{ $search }}">@endif
                            @if ($filters['konto'] !== null)<input type="hidden" name="konto" value="{{ $filters['konto'] ? '1' : '0' }}">@endif
                            @if ($filters['zgoda'] !== null)<input type="hidden" name="zgoda" value="{{ $filters['zgoda'] ? '1' : '0' }}">@endif
                            <label for="sortuj" class="text-sm text-stone-500">Sortuj</label>
                            <select id="sortuj" name="sortuj" onchange="this.form.submit()"
                                class="rounded-2xl border border-stone-200 bg-white/80 px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @foreach ($sorts as $key => $label)
                                    <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                </div>

                @if ($total === 0)
                    <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">👥</span>
                        <p class="mt-4 font-medium text-stone-700">Nie masz jeszcze klientów</p>
                        <p class="mt-1 text-sm text-stone-500">Kartoteka wypełni się sama, gdy spłyną pierwsze zamówienia.</p>
                    </div>
                @elseif ($customers->isEmpty())
                    <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">🔍</span>
                        <p class="mt-4 font-medium text-stone-700">Brak klientów pasujących do filtrów</p>
                        <p class="mt-1 text-sm text-stone-500">Zmień kryteria lub wyczyść filtry, aby zobaczyć więcej.</p>
                        <a href="{{ route('seller.customers.index', $sort !== 'ostatnie' ? ['sortuj' => $sort] : []) }}"
                            class="mt-5 inline-flex rounded-2xl border border-stone-200 bg-white/70 px-5 py-2.5 text-sm font-semibold text-stone-700 transition hover:bg-white">
                            Wyczyść filtry
                        </a>
                    </div>
                @else
                    <ul class="mt-6 space-y-2">
                        @foreach ($customers as $row)
                            <li>
                                <a href="{{ route('seller.customers.show', ['email' => $row['email']]) }}"
                                    class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-white/80 px-4 py-3.5 shadow-sm transition hover:border-amber-300 hover:shadow-md">
                                    {{-- Lewa: kto, znaczniki, kontakt · ostatni zakup --}}
                                    <div class="min-w-0">
                                        <p class="flex flex-wrap items-center gap-2 font-semibold text-stone-900">
                                            <span class="break-words">{{ $row['name'] !== '' ? $row['name'] : $row['email'] }}</span>
                                            @if ($row['has_account'])
                                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">konto</span>
                                            @endif
                                            @if ($row['has_consent'])
                                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800">zgoda</span>
                                            @endif
                                            @if (($pointBalances[$row['email']] ?? 0) !== 0)
                                                <span class="rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-medium tabular-nums text-stone-600">{{ $pointBalances[$row['email']] }} pkt</span>
                                            @endif
                                        </p>
                                        <p class="mt-0.5 truncate text-sm font-medium text-stone-700">{{ $row['email'] }}</p>
                                        <p class="text-xs text-stone-400">
                                            @if (filled($row['phone']))
                                                {{ $row['phone'] }} ·
                                            @endif
                                            ostatni zakup {{ $row['last_order_at']->format('d.m.Y') }}
                                        </p>
                                    </div>
                                    {{-- Prawa: wydatki + liczba zamówień --}}
                                    <div class="flex shrink-0 flex-col items-end gap-1.5 text-right">
                                        <span class="font-semibold tabular-nums text-stone-900">{{ \App\Support\Money::pln($row['total_spent']) }}</span>
                                        <span class="text-xs text-stone-400">
                                            {{ $row['orders_count'] }} {{ trans_choice('{1}zamówienie|[2,4]zamówienia|[5,*]zamówień', $row['orders_count']) }}
                                            @if ($row['cancelled_count'] > 0)
                                                · {{ $row['cancelled_count'] }} anul.
                                            @endif
                                        </span>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    @if ($customers->hasPages())
                        <div class="mt-6">
                            {{ $customers->onEachSide(1)->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>

        {{-- Kolumna boczna: filtry — ten sam układ i te same style co w Zamówieniach --}}
        <aside class="lg:col-span-4 space-y-6">
            @if ($total > 0 || $filtered)
                <form method="GET" action="{{ route('seller.customers.index') }}" class="space-y-4 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-stone-900">Filtry</h2>
                        @if ($filtered)
                            <a href="{{ route('seller.customers.index', $sort !== 'ostatnie' ? ['sortuj' => $sort] : []) }}"
                                class="text-xs font-medium text-stone-500 underline decoration-stone-300 underline-offset-2 transition hover:text-stone-700">Wyczyść</a>
                        @endif
                    </div>

                    {{-- Aktywne sortowanie przechodzi przez filtrowanie. --}}
                    @if ($sort !== 'ostatnie')
                        <input type="hidden" name="sortuj" value="{{ $sort }}">
                    @endif

                    <div>
                        <label for="szukaj" class="block text-sm font-medium text-stone-700">Szukaj</label>
                        <input type="search" id="szukaj" name="szukaj" value="{{ $search }}" placeholder="Nazwisko, e-mail lub telefon"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-3 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    </div>

                    <div>
                        <label for="konto" class="block text-sm font-medium text-stone-700">Konto w sklepie</label>
                        <select id="konto" name="konto"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-3 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            <option value="">— dowolne —</option>
                            <option value="1" @selected($filters['konto'] === true)>Ma konto</option>
                            <option value="0" @selected($filters['konto'] === false)>Kupował jako gość</option>
                        </select>
                    </div>

                    <div>
                        <label for="zgoda" class="block text-sm font-medium text-stone-700">Zgoda na wiadomości</label>
                        <select id="zgoda" name="zgoda"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-3 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            <option value="">— dowolna —</option>
                            <option value="1" @selected($filters['zgoda'] === true)>Zgodził się</option>
                            <option value="0" @selected($filters['zgoda'] === false)>Bez zgody</option>
                        </select>
                        <p class="mt-1.5 text-xs text-stone-400">Zgodę zaznacza sam klient — Ty jej nie dodasz za niego.</p>
                    </div>

                    <button type="submit"
                        class="w-full rounded-2xl border border-amber-200 bg-amber-50 px-5 py-2.5 text-sm font-semibold text-amber-800 transition hover:bg-amber-100">
                        Filtruj
                    </button>
                </form>

                {{-- Podsumowanie wyświetlonego zbioru — liczone ze WSZYSTKICH stron,
                     nie tylko z bieżącej, jak kafelki sprzedaży w Zamówieniach. --}}
                @if ($total > 0)
                    <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                        <h2 class="text-sm font-medium text-stone-500">Twoi klienci</h2>
                        <p class="mt-1 text-xs text-stone-400">{{ $filtered ? 'Z wyświetlonych klientów' : 'Ze wszystkich klientów' }}, wydatki bez anulowanych</p>
                        <dl class="mt-4 space-y-3">
                            @foreach ([
                                ['Klienci', (string) $summary['customers'], '👥'],
                                ['Zamówienia', (string) $summary['orders'], '📦'],
                                ['Wydali łącznie', \App\Support\Money::pln($summary['spent']), '💰'],
                            ] as [$label, $value, $icon])
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="flex items-center gap-2 text-sm text-stone-500"><span>{{ $icon }}</span>{{ $label }}</dt>
                                    <dd class="font-semibold tabular-nums text-stone-900">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif
            @endif

            {{-- „Jak to działa" — BEZWARUNKOWO, jak w Kodach rabatowych: przy pustej
                 liście kolumna nie może świecić pustką (filtry i podsumowanie mają
                 sens dopiero przy danych, ale kontekst działu — zawsze). --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to działa</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">👥</span>
                        <span>Kartoteka buduje się <span class="font-medium text-stone-700">sama, z zamówień</span> — nie dodajesz tu nikogo ręcznie.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">✉️</span>
                        <span>Kluczem jest <span class="font-medium text-stone-700">adres e-mail</span>: zakupy gościa i konta z tym samym adresem to jeden klient.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🛍️</span>
                        <span>W karcie klienta zobaczysz historię zamówień i <span class="font-medium text-stone-700">łączne wydatki</span> (bez anulowanych).</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">✅</span>
                        <span>Zgoda marketingowa — zbierana per sklep, z dowodem — decyduje, kto dostanie Twoje <span class="font-medium text-stone-700">wiadomości do klientów</span>.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🔍</span>
                        <span>Gdy klientów przybędzie, do gry wejdą filtry: konto czy gość, zgoda, wydatki, daty zakupów.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
