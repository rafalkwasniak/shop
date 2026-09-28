<x-layouts.panel title="Produkty">
    <x-slot:heading>Produkty</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: produkty --}}
        <div class="lg:col-span-8">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-stone-900">Twoje produkty</h2>
                        <p class="mt-1 text-sm text-stone-500">{{ $total }} / {{ $max }} w pakiecie {{ $shop?->effectivePackageName() }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        {{-- Sortowanie: GET bez `page` → zmiana zeruje paginację; hidden pola
                             niosą aktywne filtry, żeby sort ich nie gubił. --}}
                        @if ($total > 0)
                            <form method="GET" action="{{ route('seller.products.index') }}" class="flex items-center gap-2">
                                @if ($filters['cena_od'] !== null)<input type="hidden" name="cena_od" value="{{ $filters['cena_od'] }}">@endif
                                @if ($filters['cena_do'] !== null)<input type="hidden" name="cena_do" value="{{ $filters['cena_do'] }}">@endif
                                @if ($filters['szukaj'] !== '')<input type="hidden" name="szukaj" value="{{ $filters['szukaj'] }}">@endif
                                @if ($filters['tag'] !== '')<input type="hidden" name="tag" value="{{ $filters['tag'] }}">@endif
                                <label for="sortowanie" class="text-sm text-stone-500">Sortuj</label>
                                <select id="sortowanie" name="sortowanie" onchange="this.form.submit()"
                                    class="rounded-2xl border border-stone-200 bg-white/80 px-3 py-2 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                    @foreach ($sortOptions as $opt)
                                        <option value="{{ $opt['key'] }}" @selected($opt['active'])>{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @endif
                        @if ($total < $max)
                            <a href="{{ route('seller.products.create') }}"
                                class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                                Dodaj produkt
                            </a>
                        @endif
                    </div>
                </div>

                @if ($total === 0)
                    <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">🏷️</span>
                        <p class="mt-4 font-medium text-stone-700">Nie masz jeszcze produktów</p>
                        <p class="mt-1 text-sm text-stone-500">Dodaj pierwszy produkt — to ostatni krok do otwarcia sklepu.</p>
                        <a href="{{ route('seller.products.create') }}"
                            class="mt-5 inline-flex rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                            Dodaj pierwszy produkt
                        </a>
                    </div>
                @elseif ($products->isEmpty())
                    <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">🔍</span>
                        <p class="mt-4 font-medium text-stone-700">Brak produktów pasujących do filtrów</p>
                        <p class="mt-1 text-sm text-stone-500">Zmień kryteria lub wyczyść filtry, aby zobaczyć więcej.</p>
                        <a href="{{ route('seller.products.index', $sortKey !== 'domyslne' ? ['sortowanie' => $sortKey] : []) }}"
                            class="mt-5 inline-flex rounded-2xl border border-stone-200 bg-white/70 px-5 py-2.5 text-sm font-semibold text-stone-700 transition hover:bg-white">
                            Wyczyść filtry
                        </a>
                    </div>
                @else
                    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach ($products as $product)
                            @php($main = $product->mainImage())
                            @php($editUrl = route('seller.products.edit', ['product' => $product] + $listQuery))
                            <div class="group relative flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white/80 shadow-sm transition hover:shadow-md">
                                {{-- Zdjęcie + status --}}
                                <a href="{{ $editUrl }}" class="relative block aspect-square overflow-hidden bg-stone-50">
                                    @if ($main)
                                        <img src="{{ $main->url() }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                                    @else
                                        <span class="flex h-full w-full items-center justify-center text-3xl text-stone-300">🏷️</span>
                                    @endif
                                    <span @class([
                                        'absolute left-2 top-2 rounded-full px-2 py-0.5 text-[11px] font-medium backdrop-blur',
                                        'bg-emerald-100/90 text-emerald-700' => $product->is_active,
                                        'bg-stone-200/90 text-stone-600' => ! $product->is_active,
                                    ])>{{ $product->is_active ? 'Aktywny' : 'Ukryty' }}</span>
                                </a>

                                {{-- Treść --}}
                                <div class="flex flex-1 flex-col p-3">
                                    <a href="{{ $editUrl }}" class="line-clamp-2 text-sm font-medium text-stone-900 transition hover:text-amber-700">{{ $product->name }}</a>

                                    {{-- Dolny blok: cena + stan + akcje, zawsze przyklejony do spodu karty --}}
                                    <div class="mt-auto pt-3">
                                        <div class="flex items-baseline justify-between gap-1.5">
                                            <span class="font-semibold text-stone-900">{{ number_format((float) $product->price_gross, 2, ',', ' ') }} zł</span>
                                            @if ($product->track_stock)
                                                <span @class([
                                                    'text-[11px] font-medium',
                                                    'text-stone-500' => $product->stock > 0,
                                                    'text-rose-600' => $product->stock <= 0,
                                                ])>{{ $product->stock > 0 ? 'Zostało '.$product->sale_unit->formatQuantity((float) $product->stock) : 'Brak na stanie' }}</span>
                                            @endif
                                        </div>

                                        @php($lowest30 = $product->lowestPriceLast30Days())
                                        @if ($lowest30 !== null)
                                            <p class="mt-1 text-[11px] leading-tight text-stone-400" title="Wymóg dyrektywy Omnibus">
                                                Najniższa cena z 30 dni: {{ number_format($lowest30, 2, ',', ' ') }} zł
                                            </p>
                                        @endif

                                        {{-- Akcje --}}
                                        <div class="mt-3 flex items-center gap-2 border-t border-stone-100 pt-2.5">
                                        <a href="{{ $editUrl }}" title="Edytuj" aria-label="Edytuj produkt"
                                            class="inline-flex items-center justify-center rounded-lg border border-stone-200 bg-white/70 p-1.5 text-stone-600 transition hover:bg-white hover:text-stone-900">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                                                <path d="M12 20h9" /><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                                            </svg>
                                        </a>
                                        {{-- Kopia pyta, mimo że niczego nie psuje: stoi tuż obok „Edytuj",
                                             a skutek pomyłki jest cichy — patrz Product::duplicationConsequences(). --}}
                                        <livewire:confirm-action
                                            :key="'kopia-'.$product->id"
                                            :action="route('seller.products.duplicate', $product)"
                                            :title="'Skopiować '.$product->name.'?'"
                                            :lines="$product->duplicationConsequences()"
                                            confirm-label="Tak, kopiuj"
                                            label="Zrób kopię produktu"
                                            text="Kopiuj"
                                            icon="copy"
                                            tone="amber" />

                                        {{-- Usunięcie pyta w kafelku, a nie okienkiem przeglądarki: to są
                                             DWIE różne operacje i sprzedawca ma wiedzieć, która go czeka.
                                             `order_items_exists` dokłada lista jednym zapytaniem (withExists),
                                             więc zdanie nie kosztuje pytania na każdy kafelek. --}}
                                        <div class="ml-auto">
                                            <livewire:confirm-action
                                                :key="'usun-'.$product->id"
                                                :action="route('seller.products.destroy', $product)"
                                                :title="'Usunąć '.$product->name.'?'"
                                                :lines="$product->deletionConsequences()"
                                                label="Usuń produkt" />
                                        </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($products->hasPages())
                        <div class="mt-6">
                            {{ $products->onEachSide(1)->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>

        {{-- Kolumna pomocnicza: filtry + opisy --}}
        <aside class="lg:col-span-4 space-y-6">
            {{-- Filtry: GET bez `page` → włączenie/zmiana filtra zeruje paginację do
                 pierwszej strony. Aktywne sortowanie niesiemy hidden polem. --}}
            @if ($total > 0 || $hasFilters)
                <form method="GET" action="{{ route('seller.products.index') }}" class="space-y-4 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold text-stone-900">Filtry</h2>
                        @if ($hasFilters)
                            <a href="{{ route('seller.products.index', $sortKey !== 'domyslne' ? ['sortowanie' => $sortKey] : []) }}"
                                class="text-xs font-medium text-stone-500 underline decoration-stone-300 underline-offset-2 transition hover:text-stone-700">Wyczyść</a>
                        @endif
                    </div>

                    @if ($sortKey !== 'domyslne')
                        <input type="hidden" name="sortowanie" value="{{ $sortKey }}">
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-stone-700">Cena (zł)</label>
                        <div class="mt-1.5 flex items-center gap-2">
                            <input type="text" inputmode="decimal" name="cena_od" placeholder="od" aria-label="Cena od"
                                value="{{ $filters['cena_od'] !== null ? number_format($filters['cena_od'], 2, ',', '') : '' }}"
                                class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-3 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            <span class="text-stone-400">–</span>
                            <input type="text" inputmode="decimal" name="cena_do" placeholder="do" aria-label="Cena do"
                                value="{{ $filters['cena_do'] !== null ? number_format($filters['cena_do'], 2, ',', '') : '' }}"
                                class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-3 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        </div>
                    </div>

                    <div>
                        <label for="szukaj" class="block text-sm font-medium text-stone-700">Szukaj w produktach</label>
                        <input id="szukaj" type="search" name="szukaj" placeholder="nazwa lub opis"
                            value="{{ $filters['szukaj'] }}"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-3 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    </div>

                    <div>
                        <label for="tag" class="block text-sm font-medium text-stone-700">Tag</label>
                        <input id="tag" type="text" name="tag" list="tag-suggestions" placeholder="wpisz lub wybierz z listy"
                            value="{{ $filters['tag'] }}" autocomplete="off"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-3 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        <datalist id="tag-suggestions">
                            @foreach ($tagSuggestions as $tagName)
                                <option value="{{ $tagName }}"></option>
                            @endforeach
                        </datalist>
                    </div>

                    <button type="submit"
                        class="w-full rounded-2xl border border-amber-200 bg-amber-50 px-5 py-2.5 text-sm font-semibold text-amber-800 transition hover:bg-amber-100">
                        Filtruj
                    </button>
                </form>
            @endif

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">O produktach</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🖼️</span>
                        <span>Pierwsze zdjęcie produktu jest jego miniaturą — zadbaj o dobre, jasne ujęcie.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">📦</span>
                        <span>Stan magazynowy pokazujemy tylko przy produktach z kontrolą stanu — po wyczerpaniu stają się niedostępne.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">👁️</span>
                        <span>Ukryte produkty nie są widoczne dla klientów — używaj tego, gdy szykujesz coś na później.</span>
                    </li>
                </ul>
            </div>

            <div class="rounded-3xl border border-amber-200/70 bg-amber-50/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Pakiet {{ $shop?->effectivePackageName() }}</h2>
                <p class="mt-2 text-sm text-stone-600">
                    Wykorzystano <span class="font-medium text-amber-800">{{ $total }} z {{ $max }}</span> miejsc na produkty.
                    Wyższe pakiety dają więcej miejsca i dodatkowe funkcje.
                </p>
            </div>
        </aside>
    </div>
</x-layouts.panel>
