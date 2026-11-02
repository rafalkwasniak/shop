<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php($metaTitle = config('app.name').' — Twój sklep internetowy w 15 minut')
    @php($metaDescription = config('app.name').' to platforma, na której uruchomisz własny sklep internetowy w kilkanaście minut — bez wiedzy technicznej. Własny adres, gotowa strona, płatności i dostawy.')

    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ url('/') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/kramio-icon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    {{-- Open Graph: tak wygląda link do kramio.pl wklejony na Facebooka czy
         Messengera. Bez tego widać goły adres — a to najczęstszy sposób, w jaki
         ktoś pierwszy raz zobaczy platformę. Grafika: config/seo.php. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:locale" content="pl_PL">
    <meta property="og:image" content="{{ \App\Support\Seo::platformImage() }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ config('app.name') }} — twój sklep online">
    <meta name="twitter:card" content="summary_large_image">

    <x-google-verification :code="config('services.google.site_verification')" />
    <x-google-analytics :id="config('services.google.analytics_id')" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-stone-100 text-stone-800 antialiased">
    @php($domain = config('tenancy.central_domain'))

    <div class="relative min-h-full">
        {{-- Miękkie kształty marki. Kadrowanie (overflow-hidden) siedzi na tej
             warstwie, nie na rodzicu: kształty wystają poza stronę i gdyby ciął
             je rodzic, stawałby się przewijalnym kontenerem. Safari przy skoku
             do kotwicy (#jak-to-dziala) przewijał wtedy TEN kontener, nagłówek
             z logo wyjeżdżał poza kadr i nie wracał — bo użytkownik przewija
             okno, nie kontener. --}}
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="pointer-events-none absolute -left-40 -top-24 h-[30rem] w-[30rem] rounded-full bg-amber-300 opacity-30 blur-3xl"></div>
            <div class="pointer-events-none absolute right-0 top-1/3 h-[28rem] w-[28rem] rounded-full bg-rose-300 opacity-25 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-32 left-1/4 h-[26rem] w-[26rem] rounded-full bg-orange-200 opacity-30 blur-3xl"></div>
        </div>

        <div class="relative mx-auto w-full max-w-6xl px-6">
            {{-- Nawigacja --}}
            <header class="flex items-center justify-between py-6">
                <a href="{{ url('/') }}" class="inline-flex items-center">
                    <img src="{{ asset('images/kramio-logo.png') }}" alt="{{ config('app.name') }} — twój sklep w 15 minut" class="h-12 w-auto">
                </a>
                <nav class="flex items-center gap-2 sm:gap-4">
                    <a href="#funkcje" class="hidden text-sm font-medium text-stone-500 transition hover:text-stone-800 sm:inline">Możliwości</a>
                    <a href="#jak-to-dziala" class="hidden text-sm font-medium text-stone-500 transition hover:text-stone-800 sm:inline">Jak to działa</a>
                    <a href="#pakiety" class="hidden text-sm font-medium text-stone-500 transition hover:text-stone-800 sm:inline">Pakiety</a>
                    <a href="{{ route('login') }}" class="rounded-2xl px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-white/70">Zaloguj się</a>
                    <a href="{{ route('register') }}" class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">Załóż sklep</a>
                </nav>
            </header>

            {{-- Hero --}}
            <section class="grid items-center gap-12 py-12 lg:grid-cols-2 lg:py-20">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/70 px-3 py-1 text-xs font-medium text-amber-700 backdrop-blur">
                        Pakiet Kram — zacznij bez opłat
                    </span>
                    <h1 class="mt-5 text-4xl font-semibold leading-tight tracking-tight text-stone-900 sm:text-5xl">
                        Twój sklep internetowy <span class="bg-gradient-to-br from-amber-500 to-rose-500 bg-clip-text text-transparent">w 15 minut</span>
                    </h1>
                    <p class="mt-5 max-w-xl text-lg text-stone-600">
                        Kiedy konkurencja dopiero konfiguruje sklep, Ty już sprzedajesz. Załóż konto, dodaj pierwszy produkt i publikuj — bez wiedzy technicznej.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <a href="{{ route('register') }}" class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                            Załóż sklep za darmo
                        </a>
                        <a href="{{ route('login') }}" class="rounded-2xl border border-stone-200 bg-white/70 px-6 py-3.5 text-sm font-semibold text-stone-700 backdrop-blur transition hover:bg-white">
                            Mam już konto
                        </a>
                    </div>
                    <p class="mt-4 text-sm text-stone-500">Bez karty. Własny adres <span class="font-medium text-stone-700">twojsklep.{{ $domain }}</span> od razu.</p>
                </div>

                {{-- Makieta sklepu (dane przykładowe) --}}
                <div class="relative">
                    <div class="overflow-hidden rounded-3xl border border-white/60 bg-white/80 shadow-2xl shadow-amber-900/10 backdrop-blur-xl">
                        <div class="flex items-center gap-2 border-b border-stone-100 px-4 py-3">
                            <span class="h-3 w-3 rounded-full bg-rose-300"></span>
                            <span class="h-3 w-3 rounded-full bg-amber-300"></span>
                            <span class="h-3 w-3 rounded-full bg-emerald-300"></span>
                            <span class="ml-3 flex-1 truncate rounded-lg bg-stone-100 px-3 py-1 text-xs text-stone-500">bukiety-anny.{{ $domain }}</span>
                        </div>
                        <div class="p-5">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-stone-900">Bukiety Anny</p>
                                    <p class="text-xs text-stone-500">Kwiaty i florystyka okolicznościowa</p>
                                </div>
                                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-rose-400 text-white">🌷</span>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-3">
                                @foreach ([['Bukiet wiosenny', '89 zł', '💐'], ['Wiązanka ślubna', '249 zł', '💍'], ['Kompozycja w skrzynce', '139 zł', '🪴'], ['Storczyk w doniczce', '69 zł', '🌸']] as [$name, $price, $icon])
                                    <div class="rounded-2xl border border-stone-100 bg-white p-3">
                                        <div class="flex h-16 items-center justify-center rounded-xl bg-stone-50 text-2xl">{{ $icon }}</div>
                                        <p class="mt-2 truncate text-xs font-medium text-stone-800">{{ $name }}</p>
                                        <p class="text-xs font-semibold text-amber-700">{{ $price }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="pointer-events-none absolute -right-4 -top-4 rounded-2xl border border-white/60 bg-white/90 px-3 py-2 text-xs font-medium text-stone-700 shadow-lg backdrop-blur">
                        Przykładowy sklep
                    </div>
                </div>
            </section>

            {{-- Pakiety (dane realne z config/shop.php — jedno źródło prawdy) --}}
            <section id="pakiety" class="py-10">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-semibold tracking-tight text-stone-900">Zacznij za darmo, rośnij, gdy zechcesz</h2>
                    {{-- Reguła zmiany pakietu podana ŚCIŚLE. Poprzednie „zmienisz je
                         w każdej chwili" było nieprawdą dla zejścia niżej:
                         `PackageUpgrade::downsize()` wpuszcza tańszy pakiet dopiero
                         w oknie `shop.subscription.notice_days` przed końcem okresu.
                         Okno czytamy z configu, żeby tekst nie zaczął kłamać po
                         zmianie liczby. --}}
                    @php($noticeDays = (int) config('shop.subscription.notice_days'))
                    <p class="mt-3 text-stone-600">Pakiet Kram jest bezpłatny bez limitu czasu. Płatne pakiety odblokowują integrację płatności online, wysyłkę z nadawaniem paczek i faktury, a do tego więcej produktów, banerów na stronie głównej i zdjęć w stronach informacyjnych. Wyżej przejdziesz kiedy zechcesz, dopłacając tylko różnicę.</p>
                    <p class="mt-2 text-stone-600">Obsługa zwrotów konsumenckich jest w <span class="font-medium text-stone-800">każdym</span> pakiecie, także darmowym — razem z pouczeniem w mailu, formularzem odstąpienia dla klienta i rozliczeniem zwrotu w panelu. Tak samo zakładka Kontakt z formularzem, przez który klient zada pytanie przed zakupem.</p>
                    {{-- Doprecyzowanie postawione WPROST, bo poprzednia wersja („pakiet
                         odblokowuje płatności online") czytała się tak, jakbyśmy
                         odsprzedawali usługę płatniczą w abonamencie. Nie robimy tego:
                         pakiet daje integrację, umowę z operatorem sprzedawca zawiera
                         sam, a pieniądze nigdy nie przechodzą przez Kramio. --}}
                    <p class="mt-2 text-stone-600">Płatności online działają na <span class="font-medium text-stone-800">Twojej własnej umowie z Paynow</span> (bramka mBanku) — konto płatnicze zakładasz u operatora i to on Cię weryfikuje. Pakiet daje gotową integrację, w którą wklejasz swoje klucze; pieniądze od klientów idą wprost na Twoje konto, a Kramio nie jest stroną tych transakcji ani ich nie pośredniczy.</p>
                    {{-- Pobranie postawione OBOK Paynow, a nie w liście funkcji, bo dla
                         części sprzedawców jest odpowiedzią na akapit wyżej: „nie chcę
                         zakładać konta u operatora". Warunki podane wprost (konto InPost,
                         płatny pakiet), żeby zdanie nie obiecywało sprzedaży bez niczego —
                         landing ma nie wyprzedzać kodu. --}}
                    <p class="mt-2 text-stone-600">Nie chcesz zakładać konta u operatora płatności? Włącz <span class="font-medium text-stone-800">dostawę za pobraniem</span> — klient zapłaci przy odbiorze paczki, a pieniądze zbierze InPost i przeleje je na Twoje konto. Potrzebujesz do tego własnego konta w InPoście i płatnego pakietu; tak jak wyżej, Kramio tych pieniędzy nie dotyka.</p>
                </div>
                <div class="mt-8 grid gap-5 sm:grid-cols-3">
                    {{-- Cechy pakietów liczy App\Support\PackageFeatures: zna kolejność
                         pakietów, więc wie, co w każdym DOCHODZI względem tańszego. --}}
                    @foreach (\App\Support\PackageFeatures::landing() as $pkg)
                        @php($yearly = $pkg['price_yearly'])
                        {{-- Rytm abonamentu z configu: rok = 10 miesięcy, 2 gratis.
                             `$listYearly` to cena 12 miesięcy — kwota przekreślona. --}}
                        @php($monthsPaid = (int) config('shop.billing.months_paid'))
                        @php($monthsTotal = (int) config('shop.billing.months_total'))
                        @php($monthly = intdiv($yearly, $monthsPaid))
                        @php($listYearly = $monthly * $monthsTotal)
                        @php($freeMonths = $monthsTotal - $monthsPaid)
                        @php($featured = $pkg['name'] === 'Stragan')
                        @php($features = $pkg['features'])
                        {{-- `flex h-full flex-col` + `mt-auto` na przycisku: karty
                             w gridzie mają równą wysokość, więc przyciski wypadają
                             w jednej linii niezależnie od długości listy funkcji. --}}
                        <div class="relative flex h-full flex-col rounded-3xl border bg-white/70 p-6 backdrop-blur {{ $featured ? 'border-amber-300 ring-2 ring-amber-400/50 shadow-lg shadow-amber-900/5' : 'border-white/60' }}">
                            @if ($featured)
                                <span class="absolute -top-3 left-6 rounded-full bg-gradient-to-br from-amber-500 to-rose-500 px-3 py-1 text-[11px] font-semibold text-white shadow">Najczęściej wybierany</span>
                            @endif
                            <p class="font-semibold text-stone-900">{{ $pkg['name'] }}</p>
                            @if ($yearly === 0)
                                {{-- Ten sam układ co w kartach płatnych (flex + gap + wspólna
                                     linia bazowa), żeby trzy karty czytały się identycznie,
                                     a drobny tekst nie kleił się do kwoty. --}}
                                <p class="mt-3 flex items-baseline gap-2">
                                    <span class="text-3xl font-semibold tracking-tight text-stone-900">0 zł</span>
                                    {{-- „na zawsze" pogrubione jak „rok" przy płatnych:
                                         bezterminowość jest tu najmocniejszym argumentem,
                                         a nie przypisem. --}}
                                    <span class="text-sm text-stone-500">/ <span class="font-semibold text-stone-700">na zawsze</span></span>
                                </p>
                                {{-- Bez powtarzania „bez limitu czasu" — to samo mówi już
                                     „na zawsze" wyżej. Linijka niesie za to fakt, którego
                                     nie ma w cenie: to nie jest okres próbny. --}}
                                <p class="mt-1 text-xs text-stone-500">Bez karty · bez okresu próbnego</p>
                            @else
                                {{-- Cena 12 miesięcy przekreślona obok rocznej — zysk widać
                                     bez liczenia. `<s>` zamiast klasy `line-through`, bo tej
                                     w zbudowanym arkuszu nie ma, a klasa spoza buildu po cichu
                                     nic nie robi (przekreślenie z UA przeglądarki, preflight
                                     Tailwinda nie rusza `<s>`). --}}
                                <p class="mt-3 flex items-baseline gap-2">
                                    {{-- `text-xl`, nie `text-sm`: przekreślenie zjada drobny
                                         tekst i kwoty nie da się odczytać. Kolor trzyma
                                         hierarchię — cena roczna zostaje najmocniejsza. --}}
                                    <s class="text-xl text-stone-400">{{ $listYearly }} zł</s>
                                    <span class="text-3xl font-semibold tracking-tight text-stone-900">{{ $yearly }} zł</span>
                                    {{-- „rok" pogrubione: bez tego kwota czyta się jak cena
                                         miesięczna albo bliżej nieokreślona, a to ZA CAŁY ROK
                                         jest tu najmocniejszym argumentem. --}}
                                    <span class="text-sm text-stone-500">/ <span class="font-semibold text-stone-700">rok</span></span>
                                </p>
                                <p class="mt-1 text-xs">
                                    <span class="font-medium text-emerald-700">{{ $freeMonths }} {{ trans_choice('miesiąc|miesiące|miesięcy', $freeMonths) }} za darmo</span>
                                    <span class="text-stone-500">— {{ $monthly }} zł/mies., płacisz za {{ $monthsPaid }}</span>
                                </p>
                            @endif
                            {{-- Pogrubione = to, czego nie ma pakiet niżej. Karta ma
                                 odpowiadać na pytanie „co dostanę, jeśli dopłacę",
                                 bez porównywania trzech kolumn linijka po linijce. --}}
                            <ul class="mt-5 space-y-2 text-sm">
                                @foreach ($features as $feature)
                                    <li class="flex items-start gap-2 {{ $feature['is_new'] ? 'font-medium text-stone-900' : 'text-stone-600' }}">
                                        <span class="mt-0.5 {{ $feature['is_new'] ? 'text-amber-600' : 'text-stone-300' }}">✓</span>
                                        <span>{{ $feature['label'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            @if (collect($features)->contains('is_new', true))
                                <p class="mt-4 text-xs text-stone-400">Pogrubione — nowe względem pakietu {{ $loop->index === 1 ? 'Kram' : 'Stragan' }}.</p>
                            @endif

                            {{-- Przycisk odpowiada na intencję „chcę TEN pakiet".
                                 Prowadzi do rejestracji (jedyna droga), ale nie każe
                                 się domyślać, że trzeba iść przez darmowe konto. --}}
                            {{-- `mt-auto` na wrapperze, nie na linku: przycisk siedzi
                                 na dole karty, a `pt-5` trzyma odstęp od treści także
                                 w najwyższej karcie, gdzie nic go już nie odpycha. --}}
                            <div class="mt-auto pt-5">
                            <a href="{{ route('register') }}"
                                class="block rounded-2xl px-5 py-2.5 text-center text-sm font-semibold transition {{ $featured ? 'bg-gradient-to-br from-amber-500 to-rose-500 text-white shadow-lg shadow-rose-500/20 hover:brightness-105' : 'border border-stone-200 bg-white/70 text-stone-700 hover:bg-white' }}">
                                {{ $yearly === 0 ? 'Zacznij za darmo' : 'Wybierz '.$pkg['name'] }}
                            </a>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="mt-4 text-xs text-stone-500">Ceny brutto, rozliczenie roczne. Na wyższy pakiet przejdziesz w dowolnym momencie; na niższy — w ostatnich {{ $noticeDays }} dniach opłaconego okresu albo pozwalając mu wygasnąć.</p>

                {{-- „Jak kupić pakiet" — bo ceny same nie mówią, CO ZROBIĆ. Świadomie
                     nie budujemy tu drugiej ścieżki zakupu: każda i tak przechodzi
                     przez rejestrację (bez sklepu nie ma czego ulepszać), więc
                     wyjaśniamy drogę zamiast dublować ekrany zakupowe. --}}
                <div class="mt-8 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h3 class="text-lg font-semibold text-stone-900">Jak kupić pakiet?</h3>
                    <ol class="mt-5 grid gap-5 sm:grid-cols-3">
                        @foreach ([
                            ['Załóż darmowe konto', 'Bez karty, bez zobowiązań. Sklep dostajesz od razu.'],
                            ['Wejdź w „Mój pakiet"', 'W panelu zobaczysz ceny i kwotę do zapłaty.'],
                            ['Zapłać i sprzedawaj', 'BLIK, karta lub przelew. Pakiet włącza się od razu, fakturę dostajesz mailem.'],
                        ] as $i => [$title, $desc])
                            <li class="flex items-start gap-3">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-rose-500 text-xs font-semibold text-white">{{ $i + 1 }}</span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-stone-900">{{ $title }}</span>
                                    <span class="mt-0.5 block text-sm text-stone-500">{{ $desc }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>

                    <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-stone-100 pt-5">
                        <a href="{{ route('register') }}" class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                            Załóż konto i wybierz pakiet
                        </a>
                        {{-- Reguła zmiany pakietu wyłożona wprost: to argument ZA
                             wejściem od razu wyżej, a nie ukryty haczyk. --}}
                        <p class="text-xs text-stone-500">
                            Przejście wyżej w trakcie roku? Płacisz tylko różnicę — niewykorzystany okres wraca jako zniżka.
                        </p>
                    </div>
                </div>
            </section>

            {{-- Funkcje --}}
            <section id="funkcje" class="py-16 lg:py-24">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-semibold tracking-tight text-stone-900">Wszystko, czego potrzebujesz, by sprzedawać</h2>
                    <p class="mt-3 text-stone-600">Skupiamy się na tym, co najważniejsze — żebyś Ty mógł skupić się na swoich produktach.</p>
                </div>
                {{-- Kafelki z `App\Support\PackageFeatures::highlights()` — JEDNO
                     źródło. Wpisana tu wcześniej lista rozjeżdżała się po każdym
                     wdrożeniu (audyt 08.08: brakowało mapy strony, nadawania paczek
                     i całej warstwy wyglądu), a plakietka pakietu liczy się teraz
                     z configu, więc kafelek nie obieca w Kramie czegoś płatnego. --}}
                <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (\App\Support\PackageFeatures::highlights() as $feature)
                        @php($gate = $feature['requires'] ? \App\Support\PackageFeatures::cheapestWith($feature['requires']) : null)
                        <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-amber-900/5">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-100 to-rose-100 text-xl">{{ $feature['icon'] }}</span>
                            <h3 class="mt-4 font-semibold text-stone-900">{{ $feature['title'] }}</h3>
                            <p class="mt-1.5 text-sm text-stone-500">{{ $feature['description'] }}</p>
                            @if ($gate)
                                <span class="mt-3 inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-medium text-amber-800">od pakietu {{ $gate['name'] }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Jak to działa --}}
            <section id="jak-to-dziala" class="py-16 lg:py-20">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-semibold tracking-tight text-stone-900">Od pomysłu do sprzedaży w czterech krokach</h2>
                    {{-- Nie „bez umów": rejestracja to akceptacja Regulaminu, a
                         płatności online stoją na własnej umowie sprzedawcy
                         z operatorem — mówimy o tym dwie sekcje wyżej. --}}
                    <p class="mt-3 text-stone-600">Bez instalacji, bez wdrożenia, bez czekania.</p>
                </div>
                <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['Załóż konto', 'Podaj nazwę sklepu i e-mail. Adres sklepu tworzymy automatycznie.'],
                        ['Aktywuj sklep', 'Ustaw hasło, uzupełnij dane firmy i adres — także z NIP.'],
                        ['Dodaj produkt', 'Zdjęcie, cena, krótki opis — wsparty przez AI.'],
                        ['Sprzedawaj', 'Sklep publikuje się automatycznie. Udostępnij swój adres klientom.'],
                    ] as $i => [$title, $desc])
                        <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-rose-500 text-sm font-semibold text-white">{{ $i + 1 }}</span>
                            <h3 class="mt-4 font-semibold text-stone-900">{{ $title }}</h3>
                            <p class="mt-1.5 text-sm text-stone-500">{{ $desc }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Dla kogo jest Kramio (branże, nie konkretne sklepy) --}}
            <section class="py-16 lg:py-20">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-semibold tracking-tight text-stone-900">Dla kogo jest Kramio</h2>
                    <p class="mt-3 text-stone-600">Dla twórców i lokalnych marek, które chcą sprzedawać po swojemu — bez prowizji marketplace'u i bez agencji.</p>
                </div>
                <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['Rękodzieło i sztuka', 'Ceramika, biżuteria, ilustracja — rzeczy robione ręcznie.', '🏺'],
                        ['Dom i zapachy', 'Świece, dekoracje, tekstylia i drobiazgi do wnętrz.', '🕯️'],
                        ['Moda i galanteria', 'Ubrania, torby, dodatki ze skóry i tkanin.', '👜'],
                        ['Lokalna żywność', 'Kawa, miód, przetwory, wypieki i produkty regionalne.', '☕'],
                        ['Dla dzieci', 'Zabawki, ubranka i wyprawki od małych twórców.', '🧸'],
                        ['Rośliny i florystyka', 'Bukiety, kompozycje i rośliny doniczkowe.', '🌷'],
                    ] as [$title, $desc, $icon])
                        <div class="flex items-start gap-4 rounded-3xl border border-white/60 bg-white/70 p-5 backdrop-blur">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-100 to-rose-100 text-2xl">{{ $icon }}</span>
                            <div class="min-w-0">
                                <p class="font-semibold text-stone-900">{{ $title }}</p>
                                <p class="mt-0.5 text-xs text-stone-500">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- CTA końcowe --}}
            <section class="py-12">
                <div class="overflow-hidden rounded-[2rem] border border-white/60 bg-gradient-to-br from-amber-500 to-rose-500 p-10 text-center shadow-2xl shadow-rose-500/20 sm:p-14">
                    <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">Otwórz swój sklep już dziś</h2>
                    <p class="mx-auto mt-3 max-w-xl text-white/90">Załóż konto za darmo i sprawdź, jak szybko można zacząć sprzedawać własne produkty.</p>
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <a href="{{ route('register') }}" class="rounded-2xl bg-white px-6 py-3.5 text-sm font-semibold text-stone-900 shadow-lg transition hover:bg-stone-50">
                            Załóż sklep za darmo
                        </a>
                        <a href="{{ route('login') }}" class="rounded-2xl border border-white/40 px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-white/10">
                            Zaloguj się
                        </a>
                    </div>
                </div>
            </section>

            {{-- Stopka --}}
            <footer class="border-t border-stone-200/70 py-10">
                <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                    <a href="{{ url('/') }}" class="inline-flex items-center">
                        <img src="{{ asset('images/kramio-logo.png') }}" alt="{{ config('app.name') }} — twój sklep w 15 minut" class="h-9 w-auto">
                    </a>
                    <nav class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-stone-500">
                        <a href="{{ route('register') }}" class="transition hover:text-stone-800">Załóż sklep</a>
                        <a href="{{ route('login') }}" class="transition hover:text-stone-800">Zaloguj się</a>
                        <a href="{{ route('legal.terms') }}" class="transition hover:text-stone-800">Regulamin</a>
                        <a href="{{ route('legal.privacy') }}" class="transition hover:text-stone-800">Polityka prywatności</a>
                        <x-cookie-settings-link class="inline text-stone-500 transition hover:text-stone-800" />
                        <x-report-content-link class="inline text-stone-500 transition hover:text-stone-800" />
                    </nav>
                </div>
                <p class="mt-6 text-center text-xs text-stone-400">© {{ now()->year }} {{ config('app.name') }}. Widok sklepu w nagłówku to wizualizacja produktu.</p>
            </footer>
        </div>
    </div>
    <x-cookie-consent owner="Kramio" privacy-url="/polityka-prywatnosci">logowanie i Twój panel działały poprawnie</x-cookie-consent>
</body>
</html>
