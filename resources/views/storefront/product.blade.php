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
                @php($main = $product->mainImage())
                {{-- Karta produktu: zdjęcie takie, jak dodał sprzedawca — naturalne
                     proporcje, bez przycinania i bez wypełniania. --}}
                @if ($main)
                    <img id="st-gallery-main" src="{{ $main->url() }}" alt="{{ $product->name }}"
                        class="st-border w-full rounded-3xl border">
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
                                 powtórzenie byłoby szumem. --}}
                            <button type="button" data-gallery-thumb="{{ $img->url() }}"
                                aria-label="Pokaż zdjęcie {{ $loop->iteration }} z {{ $loop->count }}: {{ $product->name }}"
                                class="st-border h-16 w-16 overflow-hidden rounded-xl border transition hover:brightness-105">
                                <img src="{{ $img->url() }}" alt="" class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
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
                     wagę — za 1 kg, jak cena obok. Tylko gdy sklep punkty NALICZA. --}}
                @if ($shop->loyaltyActive())
                    @php($productPoints = app(\App\Services\LoyaltyLedger::class)->pointsFromBase($shop, (float) $product->price_gross))
                    @if ($productPoints > 0)
                        @php($rulesPage = \App\Support\LoyaltyRules::publishedPage($shop))
                        <p class="mt-2 text-sm">
                            Za ten zakup dostaniesz <strong class="st-brand">{{ $productPoints }} pkt</strong>@if ($product->sale_unit->isWeight()) za 1 {{ $product->sale_unit->abbreviation() }}@endif
                            <span class="opacity-70">({{ \App\Support\Money::pln($productPoints * $shop->loyaltyPointValue()) }} na kolejne zakupy)</span>
                            @if ($rulesPage)
                                · <a href="{{ $rulesPage->storefrontPath() }}" wire:navigate class="underline underline-offset-2 opacity-70 hover:opacity-100">zasady</a>
                            @endif
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

    {{-- Przełączanie zdjęcia głównego z miniatur (zero zależności). --}}
    <script>
        (function () {
            var main = document.getElementById('st-gallery-main');
            if (!main) return;
            document.querySelectorAll('[data-gallery-thumb]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    main.src = btn.getAttribute('data-gallery-thumb');
                });
            });
        })();
    </script>
</x-layouts.storefront>
