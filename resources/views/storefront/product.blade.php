<x-layouts.storefront :shop="$shop" :title="$product->name"
    :description="\App\Support\Seo::productDescription($product, $shop)"
    :image="\App\Support\Seo::productImage($product, $shop)">
    <div class="mx-auto max-w-6xl px-6 pt-10">
        <x-storefront.breadcrumbs :back="$back" :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Produkty', 'url' => '/produkty'],
            ['label' => $product->name],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">{{ $product->name }}</h1>

        <div class="st-border mt-8 border-t"></div>

        <div class="mt-8 grid gap-8 md:grid-cols-5">
            {{-- Galeria — szersza kolumna (3/5) --}}
            <div class="md:col-span-3">
                {{-- #st-gallery obejmuje TYLKO zdjęcia i miniatury — to strefa pauzy
                     samoczynnej zmiany. Gdyby w kolumnie było coś jeszcze (np. opis),
                     najechanie na to nie może zatrzymywać galerii. --}}
                <div id="st-gallery" data-gallery-interval="5000">
                {{-- Karta produktu: zdjęcia takie, jak dodał sprzedawca — naturalne
                     proporcje, bez przycinania i bez wypełniania.

                     Wszystkie zdjęcia leżą jedno na drugim w tej samej komórce siatki,
                     widać tylko aktywne. Komórka ma wysokość NAJWYŻSZEGO zdjęcia, więc
                     miniatury pod spodem stoją w miejscu — przy przełączaniu zmienia się
                     tylko wolna przestrzeń pod niższym zdjęciem. Ukryte przez
                     `visibility`, nie `display: none`: muszą dalej zajmować miejsce.
                     Style inline, a nie klasy Tailwinda — działa bez przebudowy CSS. --}}
                @if ($product->images->isNotEmpty())
                    <div style="display: grid;">
                        @foreach ($product->images as $img)
                            <img src="{{ $img->url() }}" alt="{{ $product->name }}" data-gallery-slide
                                class="st-border w-full rounded-3xl border"
                                style="grid-area: 1 / 1; align-self: start; transition: opacity .6s ease, visibility .6s;{{ $loop->first ? '' : ' opacity: 0; visibility: hidden;' }}">
                        @endforeach
                    </div>
                @else
                    <div class="st-card st-border flex items-center justify-center overflow-hidden rounded-3xl border" style="aspect-ratio: 1 / 1;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-16 w-16 opacity-70" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5" fill="currentColor" stroke="none"/><path d="M4 17l4.5-4.5 3 3L15 12l5 5"/></svg>
                    </div>
                @endif

                @if ($product->images->count() > 1)
                    <div class="mt-3 flex flex-wrap gap-3">
                        @foreach ($product->images as $img)
                            {{-- Miniatury: kwadrat, przycięte (object-cover) — jak kafle na wykazie.
                                 `aria-label` na przycisku, bo jego jedyną treścią jest obrazek —
                                 bez nazwy czytnik ekranu mówi tylko „przycisk". Zdjęcie w środku
                                 zostaje z pustym `alt`: opisuje je etykieta przycisku, więc
                                 powtórzenie byłoby szumem.
                                 Aktywna miniatura: obwódka w kolorze sklepu (`--brand`). Ramka
                                 + cień 1px zamiast grubszej ramki — rozmiar przycisku się nie
                                 zmienia, więc nic nie drga. Pierwszą zaznaczamy już w HTML-u,
                                 resztą zarządza skrypt galerii. --}}
                            <button type="button" data-gallery-thumb
                                aria-label="Pokaż zdjęcie {{ $loop->iteration }} z {{ $loop->count }}: {{ $product->name }}"
                                @if ($loop->first) aria-current="true" style="border-color: var(--brand); box-shadow: 0 0 0 1px var(--brand);" @endif
                                class="st-border h-16 w-16 overflow-hidden rounded-xl border transition hover:brightness-105">
                                <img src="{{ $img->url() }}" alt="" class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
                </div>
            </div>

            {{-- Zakup — węższa kolumna po prawej (2/5). Całość wyrównana do prawej
                 krawędzi: zdjęcie trzyma lewą stronę, panel zakupu domyka prawą. --}}
            <div class="md:col-span-2 text-right">
                {{-- Kwota w kolorze przewodnim sklepu (jak nazwa produktu / nagłówki);
                     etykieta „Cena" i ewentualne „/kg" pozostają wyciszone. --}}
                <p class="text-3xl font-bold"><span class="text-lg font-medium opacity-60">Cena:</span> <span class="st-brand">{{ \App\Support\Money::pln($product->price_gross) }}</span>@if ($product->sale_unit->isWeight())<span class="text-lg font-medium opacity-60"> / {{ $product->sale_unit->abbreviation() }}</span>@endif</p>
                @php($lowest = $product->lowestPriceLast30Days())
                @if ($lowest !== null)
                    <p class="mt-1 text-sm opacity-70">Najniższa cena z 30 dni: {{ \App\Support\Money::pln($lowest) }}</p>
                @endif

                {{-- Punkty za zakupy: liczone od ceny brutto, tą samą formułą co
                     naliczenie (LoyaltyLedger::pointsFromBase). Przy produkcie na
                     wagę — za 1 kg, jak cena obok. Tylko gdy sklep punkty NALICZA.
                     SAME PUNKTY, bez wartości w zł i bez odnośnika do zasad — te są w menu
                     sklepu (decyzja Rafała 07.10). --}}
                @if ($shop->loyaltyActive())
                    @php($productPoints = app(\App\Services\LoyaltyLedger::class)->pointsFromBase($shop, (float) $product->price_gross))
                    @if ($productPoints > 0)
                        <p class="mt-2 text-sm">
                            Za ten zakup dostaniesz <strong class="st-brand">{{ $productPoints }} pkt</strong>@if ($product->sale_unit->isWeight()) za 1 {{ $product->sale_unit->abbreviation() }}@endif
                        </p>
                    @endif
                @endif

                {{-- Dostępność jako dana produktu (statyczna); realny limit i tak pilnuje komponent koszyka. --}}
                @if ($product->track_stock && $product->stock !== null)
                    <p class="mt-3 text-sm opacity-70">Dostępne: {{ $product->sale_unit->formatAmount((float) $product->stock) }} {{ $product->sale_unit->abbreviation() }}</p>
                @endif

                {{-- Koszt wysyłki i możliwe metody dostawy/płatności jako info
                     (nie formularz) — wypełnia prawą kolumnę i zdejmuje klientowi
                     niepewność „ile wyjdzie wysyłka" jeszcze przed koszykiem. --}}
                <x-storefront.delivery-summary :shop="$shop" class="mt-6" />

                {{-- Przycisk „Do koszyka" pod tabelką, wyrównany do prawej krawędzi
                     kolumny (compact = komponent nie dubluje linii dostępności).
                     Sklep bez dostawy albo bez płatności przycisku nie pokazuje —
                     zakupu i tak nie da się dokończyć, a strona zostaje wykazem
                     oferty (kontakt ze sprzedawcą wg opisu). Bez komunikatu:
                     nieobecność przycisku mówi wszystko, czego klient potrzebuje. --}}
                @if ($shop->acceptsOrders())
                    <div class="mt-6 flex justify-end">
                        <livewire:add-to-cart :product="$product" :compact="true" />
                    </div>
                @endif

                {{-- Tagi w kaflu jak na wykazie (Filtruj). --}}
                @if (count($productTags))
                    <div class="st-card st-border mt-8 rounded-3xl border p-6 text-left">
                        <h2 class="st-brand st-box-title">Podobne produkty</h2>
                        <x-storefront.tag-cloud :tags="$productTags" label="" />
                    </div>
                @endif
            </div>
        </div>

        {{-- Opis na całą szerokość pod galerią i zakupem. Nagłówek to nazwa
             produktu, nie etykieta „O produkcie": opis leży grubo pod pierwszym
             ekranem, więc nazwa zakotwicza czytelnika ponownie (h1 dawno zjechał
             mu z widoku), a układ tytuł–linia–proza sam mówi, że to opis. --}}
        @if (filled($product->description))
            <div class="mt-12">
                <h2 class="st-brand font-serif text-2xl font-normal sm:text-3xl">{{ $product->name }}</h2>
                <div class="st-prose st-border mt-6 border-t pt-6 opacity-90">{!! \App\Support\Prose::render($product->description ?? '') !!}</div>
            </div>
        @endif
    </div>

    {{-- Galeria: przełączanie z miniatur + samoczynna zmiana co kilka sekund
         (zero zależności). Przejście to animacja samego `opacity` — liczy ją
         karta graficzna, bez przeliczania układu strony. Pauza, gdy kursor albo
         fokus klawiatury jest nad galerią. Przy „ogranicz ruch” w systemie nie
         ma ani przejścia, ani samoczynnej zmiany. --}}
    <script>
        (function () {
            var root = document.getElementById('st-gallery');
            if (!root) return;
            var slides = root.querySelectorAll('[data-gallery-slide]');
            var thumbs = root.querySelectorAll('[data-gallery-thumb]');
            if (slides.length < 2) return;

            var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var interval = parseInt(root.getAttribute('data-gallery-interval'), 10) || 5000;
            var current = 0, timer = null, hovered = false, focused = false;

            if (reduced) slides.forEach(function (s) { s.style.transition = 'none'; });

            function show(i) {
                current = (i + slides.length) % slides.length;
                slides.forEach(function (s, n) {
                    s.style.opacity = n === current ? '1' : '0';
                    s.style.visibility = n === current ? 'visible' : 'hidden';
                });
                thumbs.forEach(function (t, n) {
                    var on = n === current;
                    if (on) t.setAttribute('aria-current', 'true');
                    else t.removeAttribute('aria-current');
                    t.style.borderColor = on ? 'var(--brand)' : '';
                    t.style.boxShadow = on ? '0 0 0 1px var(--brand)' : '';
                });
            }

            function stop() { clearInterval(timer); timer = null; }
            function start() {
                stop();
                if (reduced || hovered || focused) return;
                timer = setInterval(function () { show(current + 1); }, interval);
            }

            thumbs.forEach(function (btn, n) {
                btn.addEventListener('click', function () { show(n); start(); });
            });
            root.addEventListener('mouseenter', function () { hovered = true; stop(); });
            root.addEventListener('mouseleave', function () { hovered = false; start(); });
            root.addEventListener('focusin', function () { focused = true; stop(); });
            root.addEventListener('focusout', function (e) {
                if (!root.contains(e.relatedTarget)) { focused = false; start(); }
            });

            show(0);
            start();
        })();
    </script>
</x-layouts.storefront>
