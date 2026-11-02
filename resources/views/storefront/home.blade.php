<x-layouts.storefront :shop="$shop">
    {{-- Style lokalne WYŁĄCZNIE dla boxu 1 produktu na tej stronie. Trzymamy je
         tutaj, nie w layoucie — layout jest współdzielony przez cały storefront. --}}
    <style>
        /* WARIANT BEZ apli (do porównania): kafelek huggujący zdjęcie
           (inline-block, wyśrodkowany), a informacje POD zdjęciem w stylu
           strony produktowej. Zdjęcie ograniczone I szerokością (poziome =
           pełna szerokość kafla) I wysokością (pionowe = max-height związane
           z ekranem). Bez przycinania. */
        .solo { display: inline-block; max-width: 100%; }
        .solo-media { display: block; max-width: 100%; max-height: 68vh; width: auto; height: auto; transition: transform .3s ease; }
        /* Zoom na najechanie — jak na kaflach wykazu (scale 1.05); kafel ma
           overflow-hidden, więc powiększenie jest przycięte do ramki. */
        .solo:hover .solo-media { transform: scale(1.05); }
        /* Panel z info nie rozpycha kafla: width:0 + min-width:100% sprawia, że
           o szerokości boxu decyduje ZDJĘCIE, a tekst zawija się do jego
           szerokości (bez tego długa linia opisu robiłaby box szerszy). */
        .solo-info { width: 0; min-width: 100%; box-sizing: border-box; }

        /* Siatka 2+ produktów — kafle jednakowego wymiaru. Kadr o stałych
           proporcjach (4/5) + object-cover na obrazie = wszystkie kafle równe,
           zdjęcie wypełnia kadr (przycięte, ale możliwie oryginalne). Zoom na
           hover (przycięty przez overflow ramki). Opis clampowany do 3 linii,
           żeby wysokość tekstu — a więc i kafla — była jednakowa. */
        .hpc-img { transition: transform .3s ease; }
        .hpc:hover .hpc-img { transform: scale(1.05); }
        .hpc-excerpt { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    </style>

    {{-- Hero sklepu — editorial „okładka". Winieta u góry niesie logo/nazwę jako
         STAŁĄ nawigację, więc tu świadomie NIE powtarzamy logo — robimy jeden
         oddechowy gest: nazwa w serif display, duża skala, dużo powietrza.
         Opis sklepu NIE tutaj — jego miejsce to sekcja „O sklepie" na dole. --}}
    <header class="mx-auto max-w-3xl px-6 pt-20 text-center sm:pt-28">
        <h1 class="st-brand font-serif text-5xl font-normal leading-tight tracking-tight sm:text-6xl lg:text-7xl">{{ $shop->name }}</h1>
    </header>

    {{-- Banery pod nazwą sklepu — na całą szerokość, poza kontenerem treści. --}}
    <x-storefront.banners :shop="$shop" :banners="$shop->storefrontBanners()" />

    <main class="mx-auto max-w-6xl px-6 pt-12">
        {{-- 1 produkt → KAFELEK: zdjęcie w naturalnych proporcjach na górze,
             POD nim info (nazwa w akcencie, wycinek opisu, „Pokaż produkt").
             Główna NIE sprzedaje (bez ceny/koszyka). --}}
        @if ($products->count() === 1)
                @php($p = $products->first())
                @php($main = $p->mainImage())
                @php($excerpt = filled($p->description) ? \Illuminate\Support\Str::of(strip_tags($p->description))->squish()->limit(180, preserveWords: true) : null)
                <section class="text-center">
                    <div class="solo st-card st-border overflow-hidden rounded-3xl border text-left">
                        <a href="{{ $p->storefrontPath() }}" wire:navigate class="block overflow-hidden">
                            @if ($main)
                                <img src="{{ $main->url() }}" alt="{{ $p->name }}" class="solo-media">
                            @else
                                <div class="st-btn flex items-center justify-center" style="width: 26rem; height: 26rem; max-width: 100%;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-16 w-16 opacity-70" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5" fill="currentColor" stroke="none"/><path d="M4 17l4.5-4.5 3 3L15 12l5 5"/></svg>
                                </div>
                            @endif
                        </a>

                        {{-- Info pod zdjęciem --}}
                        <div class="solo-info p-6">
                            <h2 class="st-brand font-serif text-2xl font-normal sm:text-3xl">
                                <a href="{{ $p->storefrontPath() }}" wire:navigate class="transition hover:opacity-70">{{ $p->name }}</a>
                            </h2>
                            @if ($excerpt)
                                <p class="mt-3 leading-relaxed opacity-80">{{ $excerpt }}</p>
                            @endif
                            <a href="{{ $p->storefrontPath() }}" wire:navigate class="st-btn mt-5 inline-block rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">Pokaż produkt</a>
                        </div>
                    </div>
                </section>

        {{-- 2+ produktów → JEDNOLITA siatka kafli tego samego wymiaru. Kadr
             zdjęcia o stałych proporcjach (4/5) + object-cover: wszystkie kafle
             równe, każde (różne) zdjęcie wypełnia kadr — przycięte, ale możliwie
             oryginalne. Pod zdjęciem dane jak przy 1 produkcie (bez ceny/koszyka
             — główna nie sprzedaje). 2 → 2 kolumny, 3+ → 3. Lokalny kafel
             (NIE product-card, bo ta karmi wykaz). --}}
        @elseif ($products->count() >= 2)
            {{-- Kolumny wg liczby: 2→2, 3→3, 4→2 (2×2), 5/6→3. Baza sm:grid-cols-2
                 daje 2 kol; dla 4 celowo NIE dodajemy 3. kolumny (zostaje 2×2). --}}
            <section class="grid gap-6 sm:grid-cols-2 @if ($products->count() >= 3 && $products->count() !== 4) lg:grid-cols-3 @endif">
                @foreach ($products as $p)
                    @php($m = $p->mainImage())
                    @php($ex = filled($p->description) ? \Illuminate\Support\Str::of(strip_tags($p->description))->squish()->limit(120, preserveWords: true) : null)
                    <div class="hpc st-card st-border group flex flex-col overflow-hidden rounded-2xl border text-left">
                        <a href="{{ $p->storefrontPath() }}" wire:navigate class="block overflow-hidden">
                            <div class="w-full overflow-hidden" style="aspect-ratio: 4 / 5;">
                                @if ($m)
                                    <img src="{{ $m->url() }}" alt="{{ $p->name }}" class="hpc-img h-full w-full object-cover">
                                @else
                                    <div class="st-btn flex h-full w-full items-center justify-center">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-10 w-10 opacity-70" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5" fill="currentColor" stroke="none"/><path d="M4 17l4.5-4.5 3 3L15 12l5 5"/></svg>
                                    </div>
                                @endif
                            </div>
                        </a>
                        <div class="flex flex-1 flex-col p-5">
                            {{-- Stopień pisma jak w kafelku solo i w boxach treści —
                                 na głównej wszystkie tytuły mówią jednym głosem.
                                 h2, nie h3: sekcja produktów nie ma nad sobą żadnego
                                 nagłówka, więc h3 skakałby o poziom zaraz po h1
                                 (nazwie sklepu). Poziom nagłówka jest niewidoczny —
                                 rozmiar niosą klasy — więc pilnuje go test
                                 HeadingHierarchyTest.

                                 Limit dwóch linii jak na wykazie (product-card): kafle
                                 stoją obok siebie, więc długa nazwa rozpychałaby jeden
                                 z nich. Dwie linie, nie jedna — nazwa rozróżnia produkty,
                                 a ucięcie po ~22 znakach zjadałoby końcówki wariantów
                                 („Shima OpenAir Brown" vs „…Black"). --}}
                            <h2 class="st-brand font-serif text-2xl font-normal line-clamp-2 sm:text-3xl">
                                <a href="{{ $p->storefrontPath() }}" wire:navigate class="transition hover:opacity-70">{{ $p->name }}</a>
                            </h2>
                            @if ($ex)
                                <p class="hpc-excerpt mt-2 text-sm leading-relaxed opacity-80">{{ $ex }}</p>
                            @endif
                            <div class="mt-auto pt-4">
                                <a href="{{ $p->storefrontPath() }}" wire:navigate class="st-btn inline-block rounded-full px-6 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">Pokaż produkt</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </section>
        @endif

        {{-- Treści POD ofertą (nie na górze, gdzie psuły odbiór): głos sklepu i
             strony wyróżnione przez sprzedawcę. „O sklepie" jest tu zwykłym
             kafelkiem — pierwszym, ale bez wyjątków w układzie. Reguła jest jedna:
             policz kafelki, ułóż 1/2/3 w rzędzie. Zawinąć się nie ma jak, bo sufit
             (2 strony + „O sklepie") daje najwyżej 3.

             Kafelki są JEDNOLITE: ten sam limit znaków, ten sam kształt, ta sama
             typografia niezależnie od liczby. Stopień nagłówka NIE schodzi przy
             2+ — to boxy (rodzeństwo „Zobacz produkty"), a nie kafle siatki
             produktów, gdzie tytuł jest podpisem pod zdjęciem i może być mały.
             Wszystkie boxy na tej stronie mówią jednym głosem. --}}
        @php($tileCount = count($contentTiles))
        @if ($tileCount)
            <section @class([
                'mt-16 grid gap-6',
                'sm:grid-cols-2' => $tileCount >= 2,
                'lg:grid-cols-3' => $tileCount >= 3,
            ])>
                @foreach ($contentTiles as $tile)
                    <div class="st-card st-border flex flex-col rounded-3xl border p-8 text-left">
                        <h2 class="st-brand font-serif text-2xl font-normal sm:text-3xl">{{ $tile['title'] }}</h2>

                        @if ($tile['hasMore'])
                            {{-- Treść UCIĘTA: czysty wycinek (skracanie HTML-a
                                 rozjeżdża tagi) i droga po resztę. `mt-auto` trzyma
                                 odnośniki w jednej linii u dołu, mimo różnej
                                 długości zajawek. --}}
                            <p class="mt-5 leading-relaxed opacity-80">{{ $tile['text'] }}</p>
                            <div class="mt-auto pt-5">
                                <a href="{{ $tile['url'] }}" wire:navigate class="st-brand inline-block text-sm font-medium underline-offset-4 hover:underline">Czytaj więcej →</a>
                            </div>
                        @else
                            {{-- Treść się MIEŚCI: cała, z formatowaniem, bez
                                 odnośnika — nie ma dokąd wejść, bo cel miałby to
                                 samo. Linki w treści są klikalne wprost tutaj. --}}
                            <div class="st-prose mt-5 opacity-90">{!! \App\Support\Prose::render($tile['html'] ?? '') !!}</div>
                        @endif
                    </div>
                @endforeach
            </section>
        @endif

        @if (count($tagCloud))
            <section class="mt-16">
                {{-- Tagi w takim samym wizualnym kaflu jak „O sklepie", do lewej,
                     z nagłówkiem „Zobacz produkty". Chmura bez własnej etykiety
                     (label=""), bo nagłówek już jest opisem. --}}
                <div class="st-card st-border rounded-3xl border p-8 text-left">
                    <h2 class="st-brand font-serif text-2xl font-normal sm:text-3xl">Zobacz produkty</h2>
                    <x-storefront.tag-cloud :tags="$tagCloud" label="" />
                </div>
            </section>
        @endif
    </main>
</x-layouts.storefront>
