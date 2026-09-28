<x-layouts.panel title="Kody rabatowe">
    <x-slot:heading>Kody rabatowe</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: lista kodów --}}
        <div class="lg:col-span-8">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-stone-900">Twoje kody</h2>
                        <p class="mt-1 text-sm text-stone-500">Zniżka na produkty w koszyku — procentowa, kwotowa albo darmowa wysyłka.</p>
                    </div>
                    @if ($allowed)
                        <a href="{{ route('seller.discounts.create') }}"
                            class="shrink-0 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                            Dodaj kod
                        </a>
                    @endif
                </div>

                @unless ($allowed)
                    {{-- Bez uprawnienia `discount_codes` — wspólna zachęta z drogą do
                         zakupu (nazwa i cena pakietu z configu, nie z treści). --}}
                    <div class="mt-6">
                        <x-seller.locked-feature feature="discount_codes" icon="🎟️" title="Kody rabatowe" :shop="$shop">
                            Wystawiaj kody na cały koszyk lub wybrany produkt, ograniczaj je terminem, liczbą użyć
                            albo minimalną wartością zakupów.
                        </x-seller.locked-feature>
                    </div>
                @elseif ($total === 0)
                    <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">🎟️</span>
                        <p class="mt-4 font-medium text-stone-700">Nie masz jeszcze kodów</p>
                        <p class="mt-1 text-sm text-stone-500">Dodaj pierwszy kod — klient wpisze go w koszyku, a zniżka zejdzie z wartości produktów.</p>
                    </div>
                @else
                    @if ($codes->isEmpty())
                        <div class="mt-6 rounded-2xl border border-dashed border-stone-300 px-6 py-10 text-center">
                            @if ($search !== '')
                                <p class="font-medium text-stone-700">Nic nie pasuje do „{{ $search }}"</p>
                                <p class="mt-1 text-sm text-stone-500">Szukamy po kodzie, nazwie produktu i danych klienta.</p>
                                <a href="{{ route('seller.discounts.index', $filter === 'wszystkie' ? [] : ['stan' => $filter]) }}"
                                    class="mt-4 inline-flex rounded-2xl border border-stone-200 bg-white/70 px-5 py-2.5 text-sm font-semibold text-stone-700 transition hover:bg-white">
                                    Wyczyść wyszukiwanie
                                </a>
                            @else
                                <p class="font-medium text-stone-700">Brak kodów w tym widoku</p>
                                <p class="mt-1 text-sm text-stone-500">Zmień widok po prawej, aby zobaczyć pozostałe kody.</p>
                            @endif
                        </div>
                    @else
                    <div class="mt-6 space-y-2">
                        @foreach ($codes as $code)
                            <div class="relative rounded-2xl border border-stone-200 bg-white/80 px-4 py-3.5 shadow-sm transition hover:border-amber-300">
                                <div class="flex items-start justify-between gap-4">
                                    {{-- Lewa: sam kod + na co działa i jak długo --}}
                                    <div class="min-w-0">
                                        <a href="{{ route('seller.discounts.edit', ['discountCode' => $code] + $listQuery) }}"
                                            class="font-mono font-semibold uppercase tracking-wide text-stone-900 transition hover:text-amber-700">{{ $code->code }}</a>
                                        <p class="mt-0.5 truncate text-sm text-stone-600">{{ $code->targetLabel() }}</p>
                                        <p class="text-xs text-stone-400">
                                            {{ $code->validityLabel() }}
                                            @if ($code->min_items_total !== null)
                                                · od {{ \App\Support\Money::pln($code->min_items_total) }}
                                            @endif
                                            @if ($code->isPersonal())
                                                · tylko dla: {{ trim(($code->customer?->name ?? '').' '.($code->customer?->surname ?? '')) ?: $code->customer?->email }}
                                            @endif
                                        </p>
                                    </div>

                                    {{-- Prawa: wysokość rabatu, stan i wykorzystanie --}}
                                    <div class="flex shrink-0 flex-col items-end gap-1.5 text-right">
                                        <span class="font-semibold text-stone-900">{{ $code->discountLabel() }}</span>
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $code->status()->badgeClasses() }}">
                                            {{ $code->status()->label() }}
                                        </span>
                                        <span class="text-xs text-stone-400">Użyto: {{ $code->usageLabel() }}</span>
                                    </div>
                                </div>

                                {{-- Akcje wiersza. „Kopiuj" bez budowania paczki JS — kod
                                     rabatowy najczęściej się gdzieś wkleja, więc ma być
                                     pod ręką. --}}
                                <div class="mt-3 flex flex-wrap items-center gap-3 border-t border-stone-100 pt-3">
                                    <button type="button" data-copy="{{ $code->code }}"
                                        onclick="navigator.clipboard.writeText(this.dataset.copy); const t=this.textContent; this.textContent='Skopiowano'; setTimeout(() => this.textContent = t, 1500);"
                                        class="rounded-xl border border-stone-200 bg-white px-3 py-1.5 text-sm font-medium text-stone-700 transition hover:bg-stone-100">Kopiuj</button>

                                    <a href="{{ route('seller.discounts.edit', ['discountCode' => $code] + $listQuery) }}"
                                        class="rounded-xl border border-stone-200 bg-white px-3 py-1.5 text-sm font-medium text-stone-700 transition hover:bg-stone-100">Edytuj</a>

                                    <form method="POST" action="{{ route('seller.discounts.toggle', $code) }}">
                                        @csrf
                                        <button type="submit"
                                            class="rounded-xl border border-stone-200 bg-white px-3 py-1.5 text-sm font-medium text-stone-700 transition hover:bg-stone-100">
                                            {{ $code->is_active ? 'Wyłącz' : 'Włącz' }}
                                        </button>
                                    </form>

                                    {{-- Zdanie o zamówieniach tylko wtedy, gdy kod był używany —
                                         inaczej uspokajałoby w sprawie, której nie ma. --}}
                                    <div class="ml-auto">
                                        <livewire:confirm-action
                                            :key="'usun-kod-'.$code->id"
                                            :action="route('seller.discounts.destroy', ['discountCode' => $code] + $listQuery)"
                                            :title="'Usunąć kod '.$code->code.'?'"
                                            :lines="array_values(array_filter([
                                                'Kod zniknie z listy i przestanie działać w kasie.',
                                                $code->usedCount() > 0 ? 'Zamówienia, w których go użyto, zachowają zapis rabatu.' : null,
                                                'Tej operacji nie da się cofnąć.',
                                            ]))"
                                            label="Usuń kod"
                                            text="Usuń"
                                            icon="none"
                                            trigger-class="text-sm font-medium text-rose-700 transition hover:text-rose-800" />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($codes->hasPages())
                        <div class="mt-6">
                            {{ $codes->onEachSide(1)->links() }}
                        </div>
                    @endif
                    @endif
                @endunless
            </div>
        </div>

        <aside class="lg:col-span-4 space-y-6">
            {{-- Szukanie: jedno pole na kod, produkt i klienta — sprzedawca zwykle
                 pamięta „coś" o kodzie, nie wie, w której kolumnie to siedzi.
                 GET bez `page` → nowe szukanie wraca na pierwszą stronę. --}}
            @if ($allowed && $total > 0)
                <form method="GET" action="{{ route('seller.discounts.index') }}"
                    class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-stone-900">Szukaj</h2>
                        @if ($search !== '')
                            <a href="{{ route('seller.discounts.index', $filter === 'wszystkie' ? [] : ['stan' => $filter]) }}"
                                class="text-xs font-medium text-stone-500 underline decoration-stone-300 underline-offset-2 transition hover:text-stone-700">Wyczyść</a>
                        @endif
                    </div>

                    {{-- Aktywny widok niesiemy dalej, żeby szukanie go nie gubiło. --}}
                    @if ($filter !== 'wszystkie')
                        <input type="hidden" name="stan" value="{{ $filter }}">
                    @endif

                    <div class="mt-3 flex items-center gap-2">
                        <label for="szukaj" class="sr-only">Szukaj kodu</label>
                        <input id="szukaj" type="search" name="szukaj" value="{{ $search }}" placeholder="kod, produkt lub klient"
                            class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-3 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        <button type="submit"
                            class="shrink-0 rounded-2xl border border-stone-200 bg-white px-4 py-2.5 text-sm font-semibold text-stone-700 transition hover:bg-stone-100">
                            Szukaj
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-stone-400">Przeszukujemy sam kod, nazwę produktu, którego dotyczy, oraz imię, nazwisko i e-mail przypisanego klienta.</p>
                </form>
            @endif

            {{-- Widok: proste linki GET (bez JS), ten sam wzorzec co selektor okresu
                 w Analityce. Pokazujemy dopiero, gdy jest co filtrować. --}}
            @if ($allowed && $total > 0)
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Widok</h2>
                    <div class="mt-4 space-y-1">
                        @foreach ($filters as $key => $label)
                            {{-- Zmiana widoku niesie aktywne szukanie (i zeruje stronę). --}}
                            <a href="{{ route('seller.discounts.index', array_filter([
                                    'stan' => $key === 'wszystkie' ? null : $key,
                                    'szukaj' => $search !== '' ? $search : null,
                                ])) }}"
                               @class([
                                   'flex items-center justify-between rounded-2xl px-4 py-2.5 text-sm transition',
                                   'bg-white font-medium text-stone-900 shadow-sm' => $filter === $key,
                                   'text-stone-500 hover:bg-white/60' => $filter !== $key,
                               ])>
                                <span>{{ $label }}</span>
                                @if ($filter === $key)
                                    <span class="text-amber-500" aria-hidden="true">✓</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to działa</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🎟️</span>
                        <span>Klient wpisuje kod w koszyku. Zniżka schodzi z <span class="font-medium text-stone-700">wartości produktów</span> — koszt wysyłki zostaje nietknięty.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🚚</span>
                        <span>Wyjątkiem jest kod <span class="font-medium text-stone-700">darmowa wysyłka</span> — ten nie rusza produktów, tylko zeruje koszt dostawy.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">⏳</span>
                        <span>Kod możesz ograniczyć terminem, liczbą użyć albo minimalną wartością zakupów — wszystko jest opcjonalne.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">↩️</span>
                        <span>Anulowane zamówienie <span class="font-medium text-stone-700">oddaje użycie</span> — kod jednorazowy znów zadziała.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🏷️</span>
                        <span>Kod nie zmienia ceny w katalogu, więc nie wpływa na „najniższą cenę z 30 dni".</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
