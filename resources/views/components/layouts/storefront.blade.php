@props([
    'shop',
    'title' => null,
    'bare' => false,
    // Meta pod wyszukiwarki i social media. Domyślnie opisujemy SKLEP; widoki
    // z bogatszą treścią (produkt, strona informacyjna) podają swoje.
    'description' => null,
    'image' => null,
    // Strony transakcyjne (koszyk, kasa, konto, płatność) nie mają czego szukać
    // w Google — a adres strony płatności niesie token.
    'noindex' => false,
])

@php
    $tokens = $shop->themeTokens();
    // Czym malujemy pasek górny i stopkę — druga oś szablonu obok palety
    // (patrz komentarz „CHROME" w config/themes.php). $chromeMix = siła pastelu
    // dla chrome `brand`, dostrajana w configu.
    $chrome = $shop->templateChrome();
    $chromeMix = (int) config('themes.chrome_brand_mix');
    $chromeTexture = $shop->templateChromeTexture();
    $cardMix = $shop->templateCardMix();
    // Charakter sklepu: krój nagłówków i stopień zaokrągleń. Dwie osie
    // NIEZALEŻNE od szablonu — patrz „Charakter sklepu" w config/themes.php.
    $font = $shop->themeFont();
    $fontSizes = $shop->themeFontSizes();
    $radiusVars = $shop->themeRadiusVars();
    // Google Analytics/Tag Manager: wstrzykujemy tylko gdy włączone (Ustawienia)
    // i skonfigurowane (Integracje). ID jest zwalidowane do [A-Z0-9-] (Form
    // Request), więc bezpieczne w <script>. GTM- → Tag Manager, G- → GA4.
    $gaId = $shop->tracksWithGoogleAnalytics() ? $shop->googleAnalyticsId() : null;

    // Weryfikacja własności w Google Search Console. Bez bramki pakietu i bez
    // włącznika: to jedyna droga, żeby sprzedawca potwierdził Google własność
    // swojej subdomeny i zgłosił mapę strony.
    $siteVerification = $shop->googleSiteVerification();

    // Nawigacja: jedno źródło pozycji „Informacje" (wirtualna „O sklepie" + strony)
    // dla nagłówka i stopki. Logo (jeśli jest) i adres NASZEJ Polityki prywatności
    // (na centrali, nie na subdomenie sklepu).
    $infoMenu = $shop->informationMenu();
    $logoUrl = $shop->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($shop->logo_path) : null;

    // Meta: widok podaje swoje albo bierzemy opis sklepu. Tytuł OG celowo bez
    // sufiksu z nazwą sklepu — na Facebooku nazwa idzie osobno (og:site_name).
    $metaTitle = $title ?? $shop->name;
    $metaDescription = $description ?? \App\Support\Seo::shopDescription($shop);
    $metaImage = $image ?? \App\Support\Seo::shopImage($shop);
    $canonical = \App\Support\Seo::canonical();
@endphp
<!doctype html>
<html lang="pl" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · '.$shop->name : $shop->name }}</title>

    {{-- Opis do wyników wyszukiwania. Bez niego Google wycina losowy fragment
         strony — audyt ursalogic wskazał to jako problem numer jeden SEO. --}}
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $canonical }}">

    {{-- Uniwersalna ikonka platformy (torba Kramio). Sklepy nie wgrywają własnych
         favicon — logo sklepu ma dowolne proporcje i w 16px robi się nieczytelne,
         a torba pasuje do każdego sklepu. Gdyby kiedyś sklep miał własną ikonkę,
         to tutaj jest miejsce na podmiankę per sklep.

         `apple-touch-icon` jest OSOBNYM plikiem, a nie tym samym co favicon:
         iOS ignoruje przezroczystość i podkłada CZARNE tło, więc zaokrąglone
         rogi ikonki wyszłyby czarnymi narożnikami. Plik 180×180 jest już
         spłaszczony na kremowej płytce — iOS sam nadaje mu swoje rogi. --}}
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/kramio-icon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <x-google-verification :code="$siteVerification" />
    @if ($noindex)
        <meta name="robots" content="noindex, follow">
    @endif

    {{-- Open Graph: tak wygląda link po wklejeniu na Facebooka czy Messengera.
         Bez tego sprzedawca widzi goły adres zamiast karty ze zdjęciem. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $shop->name }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:locale" content="pl_PL">
    @if ($metaImage)
        <meta property="og:image" content="{{ $metaImage }}">
    @endif
    <meta name="twitter:card" content="{{ $metaImage ? 'summary_large_image' : 'summary' }}">

    @vite('resources/css/app.css')

    {{-- Motyw sklepu: tokeny palety → zmienne CSS. To jedyne miejsce, z którego
         storefront bierze kolory (bez kompilacji CSS per-sklep). Kolory z configu
         (zaufane), nie z inputu użytkownika. --}}
    <style>
        :root {
            --brand: {{ $tokens['brand'] }};
            --brand-ink: {{ $tokens['brand_ink'] }};
            --surface: {{ $tokens['surface'] }};
            --ink: {{ $tokens['ink'] }};

            {{-- Charakter sklepu. Obie osie działają przez nadpisanie zmiennych,
                 z których korzysta JUŻ ZBUDOWANY Tailwind: `.font-serif` i
                 `.st-box-title` czytają --font-serif, a wszystkie `.rounded-*`
                 czytają --radius-*. Dlatego kilka linii tutaj przestawia ~200
                 miejsc w widokach — bez ich edycji i bez kompilacji per sklep.
                 Ten blok jest PO arkuszu Tailwinda, więc przy równej wadze
                 selektora (:root) wygrywa. --}}
            @if ($font === 'plain')
                {{-- Nagłówki tym samym krojem co treść. Efekt uboczny (mile
                     widziany): plik Instrument Serif nie zostaje pobrany. --}}
                --font-serif: var(--font-sans);
            @endif
            @foreach ($radiusVars as $step => $size)
                --radius-{{ $step }}: {{ $size }};
            @endforeach

            {{-- Chrome (pasek górny + stopka). Nagłówek jest półprzezroczysty
                 z backdrop-blur, stopka pełna — stąd dwie zmienne, nie jedna. --}}
            @if ($chrome === 'brand')
                {{-- Pastel marki o sile z configu (dostrajanej na oko — patrz
                     `chrome_brand_mix`). Tekst na --ink, nie --brand-ink:
                     biały na pastelu jest nieczytelny. --}}
                --chrome-top: color-mix(in srgb, var(--brand) {{ $chromeMix }}%, color-mix(in srgb, var(--surface) 85%, transparent));
                --chrome-bottom: color-mix(in srgb, var(--brand) {{ $chromeMix }}%, var(--surface));
                --chrome-ink: var(--ink);
            @elseif ($chrome === 'brand_tint')
                --chrome-top: color-mix(in srgb, var(--brand) 12%, color-mix(in srgb, var(--surface) 92%, transparent));
                --chrome-bottom: color-mix(in srgb, var(--brand) 8%, var(--surface));
                --chrome-ink: var(--ink);
            @else
                --chrome-top: color-mix(in srgb, var(--ink) 8%, color-mix(in srgb, var(--surface) 92%, transparent));
                --chrome-bottom: color-mix(in srgb, var(--ink) 5%, var(--surface));
                --chrome-ink: var(--ink);
            @endif
        }

        body { background: var(--surface); color: var(--ink); }
        .st-chrome { color: var(--chrome-ink); }
        .st-chrome-top { background: var(--chrome-top); }
        .st-chrome-bottom { background: var(--chrome-bottom); }
        @if ($chromeTexture !== 'none')
            {{-- Faktura chrome (patrz „CHROME_TEXTURE" w config/themes.php):
                 wzór w półprzezroczystym kolorze tła strony, więc idzie za paletą.
                 Te reguły są PO .st-chrome-top/-bottom, żeby background-image
                 wygrało z ich skrótem `background`. --}}
            .st-chrome-top, .st-chrome-bottom {
                --chrome-pattern: color-mix(in srgb, var(--surface) 55%, transparent);
                @if ($chromeTexture === 'awning')
                    background-image: repeating-linear-gradient(135deg, var(--chrome-pattern) 0 5px, transparent 5px 16px);
                @elseif ($chromeTexture === 'dots')
                    background-image:
                        radial-gradient(circle, var(--chrome-pattern) 1.6px, transparent 1.7px),
                        radial-gradient(circle, var(--chrome-pattern) 1.6px, transparent 1.7px);
                    background-size: 18px 18px;
                    background-position: 0 0, 9px 9px;
                @elseif ($chromeTexture === 'pinpoint')
                    background-image:
                        radial-gradient(circle, var(--chrome-pattern) 1.1px, transparent 1.2px),
                        radial-gradient(circle, var(--chrome-pattern) 1.1px, transparent 1.2px);
                    background-size: 10px 10px;
                    background-position: 0 0, 5px 5px;
                @elseif ($chromeTexture === 'stripes')
                    {{-- Skos w przeciwną stronę niż awning (45° vs 135°). --}}
                    background-image: repeating-linear-gradient(45deg, var(--chrome-pattern) 0 3px, transparent 3px 13px);
                @endif
            }
        @endif
        @if ($chrome === 'brand')
            /* Linki i nazwa sklepu na --ink zamiast --brand: kolor marki na
               własnym pastelu ma za słaby kontrast. Przycisk koszyka (st-btn)
               zostaje brandowy — na pastelu dobrze się odcina. */
            .st-chrome .st-brand { color: var(--chrome-ink); }
            /* Rozwijane menu mobilne ma tło z --surface — w środku wracają
               kolory strony (linki brandowe). */
            .st-chrome .st-menu .st-brand { color: var(--brand); }
        @endif
        .st-brand { color: var(--brand); }
        /* Nagłówek boxu/karty na storefroncie — jedno źródło kroju i ROZMIARU
           tytułów kart, żeby wszystkie były spójne i regulowane z jednego miejsca.
           Serif (Instrument Serif) czyta się optycznie mniej niż sans, więc
           trzymamy go odrobinę większym niż text-xl. Kolor NIE tu — zostaje na
           .st-brand (lub np. rose dla strefy „danger”). */
        {{-- Letter-spacing LEKKO dodatni, nie ujemny jak przy dużych h1: ozdobny
             serif w małym stopniu potrzebuje powietrza między literami (Rafał:
             „mało je widać"). Duże nagłówki zostają ciasne (tracking-tight). --}}
        .st-box-title { font-family: var(--font-serif); font-size: 1.375rem; font-weight: 400; letter-spacing: 0.01em; line-height: 1.3; }
        @if ($fontSizes !== [])
            /* Korekta stopni nagłówków dla kroju prostego (patrz `sizes` przy
               foncie w config/themes.php). Zbudowany Tailwind czyta rozmiar
               ze zmiennej — `.text-4xl{font-size:var(--text-4xl)}` — a zmienna
               ustawiona NA elemencie bije tę odziedziczoną z :root. Dlatego
               ta jedna reguła kurczy wszystkie nagłówki i tylko je: zwykły
               tekst nie ma klasy `.font-serif`, więc bierze rozmiary z :root. */
            .font-serif {
                @foreach ($fontSizes as $step => $size)
                    --text-{{ $step }}: {{ $size }};
                @endforeach
            }
        @endif
        @if ($font === 'plain')
            /* Rozmiar i rozstrzelenie wyżej są dobrane pod serif, który czyta
               się optycznie MNIEJ niż sans. Ten sam stopień w sansie wychodzi
               za duży i zbyt luźny, więc krój prosty dostaje własne wartości:
               mniejszy stopień, cięższa waga, ciaśniejszy tracking. */
            .st-box-title { font-size: 1.125rem; font-weight: 600; letter-spacing: -0.01em; }
        @endif
        .st-btn { background: var(--brand); color: var(--brand-ink); }
        .st-border { border-color: color-mix(in srgb, var(--ink) 12%, transparent); }
        /* Panel karty — o `card_mix`% ciemniejszy od tła (szablon decyduje, jak
           mocno; domieszka z --ink, więc odcień idzie za paletą, nie w szarość). */
        .st-card { background: color-mix(in srgb, var(--ink) {{ $cardMix }}%, var(--surface)); }
        /* Menu (rozwijane „Informacje" + mobilne): tło z tokenu, hover z --ink. */
        .st-menu { background: var(--surface); }
        .st-menu-item:hover { background: color-mix(in srgb, var(--ink) 7%, transparent); }

        /* Typografia treści stron „Informacje" (HTML z bazy). Bliźniak
           .legal-content, ale kolor DZIEDZICZY z motywu (--ink) — czytelny na
           jasnym i ciemnym szablonie; akcenty (marker listy, link) na --brand. */
        .st-prose { line-height: 1.75; }
        .st-prose h2 { margin-top: 2rem; margin-bottom: 0.75rem; font-size: 1.25rem; font-weight: 600; letter-spacing: -0.01em; }
        .st-prose h3 { margin-top: 1.5rem; margin-bottom: 0.5rem; font-size: 1.05rem; font-weight: 600; }
        .st-prose :is(h2, h3):first-child { margin-top: 0; }
        .st-prose p { margin-bottom: 1rem; }
        .st-prose ul, .st-prose ol { margin-bottom: 1rem; padding-left: 1.5rem; }
        .st-prose ul { list-style: disc; }
        .st-prose ol { list-style: decimal; }
        .st-prose li { margin-bottom: 0.4rem; }
        .st-prose li::marker { color: var(--brand); }
        .st-prose strong { font-weight: 600; }
        .st-prose a { color: var(--brand); text-decoration: underline; text-underline-offset: 2px; }
        .st-prose a:hover { opacity: 0.8; }
        [x-cloak] { display: none !important; }

        /* Skip link („Przejdź do treści"): schowany, dopóki ktoś nie dojdzie do
           niego klawiszem Tab. Osoba nawigująca klawiaturą omija w ten sposób
           całe menu zamiast przechodzić je na każdej podstronie — audyt WCAG
           wskazał brak tego linku na 100% stron. Własny CSS zamiast klas
           Tailwinda, bo `focus:not-sr-only` nie ma w zbudowanym arkuszu. */
        .st-skip {
            position: absolute;
            left: -9999px;
            top: 0;
            z-index: 100;
        }
        .st-skip:focus {
            left: 1rem;
            top: 1rem;
            padding: 0.75rem 1.25rem;
            border-radius: 9999px;
            background: var(--brand);
            color: var(--brand-ink);
            font-weight: 600;
            text-decoration: none;
        }
    </style>

    @livewireStyles

    <x-google-analytics :id="$gaId" />

    {{-- Zasoby dokładane przez konkretną podstronę (np. geowidget InPostu w kasie).
         Ładują się tylko tam, gdzie są potrzebne — nie obciążają całego sklepu. --}}
    @stack('head')
</head>

<body class="min-h-full font-sans antialiased">
    {{-- Pierwszy element w kolejności Tab — inaczej klawiatura musi przejść całe
         menu na każdej podstronie, zanim dotrze do oferty. --}}
    <a href="#tresc" class="st-skip">Przejdź do treści</a>

    <x-google-analytics-noscript :id="$gaId" />

    {{-- Zgodę zbieramy W IMIENIU SPRZEDAWCY — to on jest administratorem danych
         na swojej subdomenie, nie Kramio.

         Pytamy ZAWSZE, nie tylko gdy sklep ma włączony pomiar (decyzja Rafała):
         zgoda zebrana z zapasem nic nie kosztuje, a jej brak w dniu, w którym
         dojdzie piksel czy mapa, oznacza przerabianie wszystkiego od nowa. --}}
    <x-cookie-consent
        :owner="'Sklep '.$shop->name"
        privacy-url="/informacje/{{ config('pages.privacy.slug') }}">koszyk i logowanie działały poprawnie</x-cookie-consent>

    @unless ($bare)
    {{-- Nagłówek globalny — WINIETA. Mobile: kompaktowy pasek (hamburger · brand ·
         koszyk). Desktop: brand wyśrodkowany i duży (logo/serif), cienka linia,
         nav pod spodem. Rozwijane menu i menu mobilne na natywnym <details> —
         storefront jest JS-light (bez app.js), więc zero zależności. --}}
    <header class="st-chrome st-chrome-top st-border sticky top-0 z-30 border-b backdrop-blur">
        {{-- MOBILE: kompaktowy pasek --}}
        <div class="relative md:hidden">
            <div class="flex items-center justify-between px-4 py-3">
                <details>
                    <summary class="flex cursor-pointer select-none list-none items-center [&::-webkit-details-marker]:hidden" aria-label="Menu">
                        <svg class="h-6 w-6 opacity-80" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </summary>
                    <div class="st-menu absolute inset-x-0 top-full z-40 border-t st-border">
                        <nav class="flex flex-col px-4 py-2 text-sm font-medium">
                            <a href="/produkty" wire:navigate class="st-menu-item st-brand -mx-4 px-4 py-3">Produkty</a>
                            @foreach ($infoMenu as $item)
                                <a href="{{ $item['url'] }}" wire:navigate class="st-menu-item st-brand -mx-4 px-4 py-3">{{ $item['label'] }}</a>
                            @endforeach
                            @auth('customer')
                                @php
                                    $greetName = \App\Support\Vocative::of(auth('customer')->user()->name);
                                @endphp
                                <span class="-mx-4 px-4 pt-3 text-xs uppercase tracking-wide opacity-50">Cześć{{ $greetName ? ', '.$greetName : '' }}</span>
                                <a href="/moje-konto" wire:navigate class="st-menu-item st-brand -mx-4 px-4 py-3">Moje konto</a>
                                <form method="POST" action="/wyloguj" class="-mx-4">
                                    @csrf
                                    <button type="submit" class="st-menu-item st-brand w-full px-4 py-3 text-left">Wyloguj się</button>
                                </form>
                            @else
                                <a href="/logowanie" wire:navigate class="st-menu-item st-brand -mx-4 px-4 py-3">Zaloguj się</a>
                            @endauth
                        </nav>
                    </div>
                </details>

                <a href="/" wire:navigate class="absolute left-1/2 flex max-w-[60%] -translate-x-1/2 items-center justify-center">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $shop->name }}" class="h-11 w-auto max-w-full object-contain">
                    @else
                        <span class="st-brand truncate font-serif text-2xl leading-none">{{ $shop->name }}</span>
                    @endif
                </a>

                <livewire:cart-counter :shop-id="$shop->id" />
            </div>
        </div>

        {{-- DESKTOP: winieta wyśrodkowana. Odstęp brand → nawigacja to `gap-8`
             (32 px), nie `gap-3`: logo jest obrazem ciasno przyciętym do treści,
             więc przy 12 px menu wchodziło mu pod nogi, a winieta ma się czytać
             jako szyld z powietrzem wokół. Wersja tekstowa brandu zyskuje na tym
             samym oddechu, dlatego odstęp jest jeden dla obu. --}}
        <div class="relative mx-auto hidden max-w-6xl flex-col items-center gap-8 px-6 py-5 md:flex">
            {{-- Koszyk w prawym rogu --}}
            <div class="absolute right-6 top-5">
                <livewire:cart-counter :shop-id="$shop->id" />
            </div>

            {{-- Brand (duży, wyśrodkowany) --}}
            <a href="/" wire:navigate class="flex max-w-full items-center justify-center">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $shop->name }}" class="h-28 w-auto max-w-[18rem] object-contain">
                @else
                    <span class="st-brand text-center font-serif text-4xl leading-none tracking-tight">{{ $shop->name }}</span>
                @endif
            </a>

            {{-- Nawigacja --}}
            <nav class="flex items-center gap-8 text-base font-medium">
                <a href="/produkty" wire:navigate class="st-brand transition hover:brightness-95">Produkty</a>
                {{-- „Informacje" prowadzi na pierwszą podstronę; nawigację między
                     stronami przejmuje lewe menu w skorupie (information-shell). --}}
                @if (count($infoMenu))
                    <a href="{{ $infoMenu[0]['url'] }}" wire:navigate class="st-brand transition hover:brightness-95">Informacje</a>
                @endif

                {{-- Konto — za „Informacje", oddzielone smukłą pionową linią z nieco
                     większym odstępem (margines na separatorze ponad gap nawigacji). --}}
                {{-- Kolor z `currentColor`, nie z --ink: na pasku w kolorze marki
                     linia liczona z czerni byłaby ciemną kreską na kolorze. --}}
                <span aria-hidden="true" style="width:1px; height:1.35rem; margin:0 0.5rem; background: color-mix(in srgb, currentColor 30%, transparent);"></span>
                @auth('customer')
                    <a href="/moje-konto" wire:navigate class="st-brand transition hover:brightness-95">Moje konto</a>
                @else
                    <a href="/logowanie" wire:navigate class="st-brand transition hover:brightness-95">Zaloguj się</a>
                @endauth
            </nav>
        </div>
    </header>
    @endunless

    {{-- Cel skip linku. `tabindex="-1"` sprawia, że po kliknięciu fokus faktycznie
         przeskakuje tutaj, a nie tylko przewija się widok. --}}
    <div id="tresc" tabindex="-1">
        {{ $slot }}
    </div>

    @unless ($bare)
    {{-- Stopka globalna: brand · Informacje (te same pozycje co w menu + NASZA
         Polityka prywatności) · Kontakt. Pasek „Sklep na Kramio" na dole.
         Tło z chrome szablonu — jak nagłówek, żeby stopka nie zlewała się ze stroną. --}}
    <footer class="st-chrome st-chrome-bottom st-border mt-16 border-t">
        <div class="mx-auto grid max-w-6xl gap-8 px-6 py-12 sm:grid-cols-2 md:grid-cols-3">
            {{-- Brand + dane firmowe (jeśli sprzedawca uzupełnił) --}}
            <div>
                {{-- `max-w` idzie w parze z `h`: przy zbyt ciasnym limicie szerokości
                     object-contain ściąga logo poziome z powrotem w dół i wzrost
                     wysokości jest pozorny. 14rem przy h-16 mieści proporcje do ~3,5:1
                     bez duszenia (nagłówek-winieta ma h-28 / max-w-18rem). --}}
                <a href="/" wire:navigate class="inline-flex items-center gap-2">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $shop->name }}" class="h-16 w-auto max-w-[14rem] object-contain">
                    @else
                        <span class="st-brand font-serif text-2xl">{{ $shop->name }}</span>
                    @endif
                </a>
                @if (filled($shop->company_name) || filled($shop->street))
                    <address class="mt-4 text-sm not-italic leading-relaxed opacity-70">
                        @if (filled($shop->company_name))<div>{{ $shop->company_name }}</div>@endif
                        @if (filled($shop->street))
                            <div>{{ $shop->street }} {{ $shop->building_number }}@if (filled($shop->apartment_number))/{{ $shop->apartment_number }}@endif</div>
                        @endif
                        @if (filled($shop->postal_code) || filled($shop->city))
                            <div>{{ $shop->postal_code }} {{ $shop->city }}</div>
                        @endif
                    </address>
                @endif
            </div>

            {{-- Informacje --}}
            <div>
                {{-- h2, nie h3: kolumny stopki to sekcje dokumentu tuż pod h1
                     strony — nie ma między nimi żadnego h2, więc h3 skakałby
                     o poziom, i to na KAŻDEJ podstronie storefrontu. --}}
                <h2 class="text-sm font-semibold">Informacje</h2>
                {{-- Polityka prywatności jest ZAWSZE ostatnią pozycją menu — pinujemy
                     ją na końcu stopki, a limit (config) obcina tylko strony
                     sprzedawcy przed nią, żeby stopka się nie rozjechała. --}}
                @php
                    $footerPrivacy = end($infoMenu);
                    $footerPages = array_slice($infoMenu, 0, -1);
                @endphp
                <ul class="mt-3 space-y-2 text-sm opacity-80">
                    @foreach (array_slice($footerPages, 0, (int) config('pages.footer_menu_max') - 1) as $item)
                        <li><a href="{{ $item['url'] }}" wire:navigate class="transition hover:opacity-100">{{ $item['label'] }}</a></li>
                    @endforeach
                    <li><a href="{{ $footerPrivacy['url'] }}" wire:navigate class="transition hover:opacity-100">{{ $footerPrivacy['label'] }}</a></li>
                    {{-- Wycofanie zgody — ten sam komponent co w centrali.
                         Widoczne po podjęciu decyzji; wcześniej pyta o nią baner. --}}
                    <li><x-cookie-settings-link /></li>
                    <li><x-report-content-link /></li>
                </ul>
            </div>

            {{-- Kontakt --}}
            @if (filled($shop->contact_email) || filled($shop->contact_phone))
                <div>
                    <h2 class="text-sm font-semibold">Kontakt</h2>
                    <ul class="mt-3 space-y-2 text-sm opacity-80">
                        @if (filled($shop->contact_email))
                            <li><a href="mailto:{{ $shop->contact_email }}" class="transition hover:opacity-100">{{ $shop->contact_email }}</a></li>
                        @endif
                        @if (filled($shop->contact_phone))
                            <li><a href="tel:{{ $shop->contactPhoneHref() }}" class="transition hover:opacity-100">{{ $shop->formattedContactPhone() }}</a></li>
                        @endif
                    </ul>
                </div>
            @endif
        </div>

        <div class="st-border border-t py-6 text-center text-xs opacity-60">
            Sklep zbudowany na <a href="https://{{ config('tenancy.central_domain') }}" class="font-semibold">Kramio</a>
        </div>
    </footer>
    @endunless

    @livewireScripts
</body>

</html>
