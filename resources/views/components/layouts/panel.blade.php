<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($title ?? 'Panel') . ' · ' . config('app.name') }}</title>
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/kramio-icon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    {{-- Google Analytics ŚWIADOMIE tu nie ma — to nie przeoczenie.
         Panel jest narzędziem pracy za logowaniem, nie stroną, która ma
         przyciągać ruch: mierzenie go niczego nie podpowiada (kto się loguje,
         wiemy z własnej bazy), a adresy podstron niosą numery zamówień i
         klientów sprzedawcy, których nie ma po co wysyłać do Google.
         Pomiar obejmuje strony publiczne centrali: landing, rejestrację,
         logowanie i dokumenty prawne. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Elementy Alpine ukryte do czasu startu skryptu (np. „Piszę…" w boksie SEO).
         Reguła inline, bo klasy `x-cloak` nie ma w zbudowanym arkuszu. --}}
    <style>[x-cloak] { display: none !important; }</style>
    @livewireStyles
</head>
<body class="h-full bg-stone-100 text-stone-800 antialiased">
    <x-toasts />

    @php
        $user = auth()->user();
        $isAdmin = $user?->isAdmin();
        $area = $isAdmin ? 'Administrator' : 'Sprzedawca';
        $nav = $isAdmin ? [
            ['label' => 'Pulpit', 'route' => 'administrator.dashboard', 'icon' => '🏠'],
            ['label' => 'Sklepy', 'route' => 'administrator.shops.index', 'active' => 'administrator.shops.*', 'icon' => '🛍️'],
            ['label' => 'Sprzedawcy', 'route' => 'administrator.sellers.index', 'active' => 'administrator.sellers.*', 'icon' => '👥'],
            ['label' => 'Wiadomości', 'route' => 'administrator.mailings.index', 'active' => 'administrator.mailings.*', 'icon' => '📣'],
            ['label' => 'Zamówienia', 'route' => 'administrator.orders.index', 'active' => 'administrator.orders.*', 'icon' => '📦'],
            ['label' => 'Analityka', 'route' => 'administrator.analytics.index', 'icon' => '📊'],
            ['label' => 'Pakiety', 'route' => 'administrator.packages.index', 'active' => 'administrator.packages.*', 'icon' => '✨'],
            // Odznaka liczy nierozpatrzone: od chwili zgłoszenia mamy wiedzę o
            // treści, więc to jedyna kolejka w panelu, w której zaleganie ma
            // koszt prawny (art. 6 DSA). COUNT po indeksowanym `status`.
            // Komentarz PHP, NIE bladowy — jesteśmy w środku bloku @php.
            ['label' => 'Zgłoszenia', 'route' => 'administrator.reports.index', 'active' => 'administrator.reports.*', 'icon' => '🚩', 'badge' => \App\Models\ContentReport::pending()->count()],
            ['label' => 'Ustawienia', 'route' => 'administrator.settings.index', 'active' => 'administrator.settings.*', 'icon' => '⚙️'],
        ] : [
            ['label' => 'Pulpit', 'route' => 'seller.dashboard', 'icon' => '🏠'],
            ['label' => 'Mój sklep', 'route' => 'seller.shop.edit', 'icon' => '🛍️', 'owner' => true],
            // „Mój pakiet" tylko w Kramio — sklep dedykowany jest opłacony
            // jednorazowo i nie ma czego oglądać ani przedłużać. Trasa i tak
            // odpowiada wtedy 404 (middleware `saas`), ale odnośnik prowadzący
            // w pustkę wygląda jak usterka, a nie jak decyzja.
            ...(\App\Support\Mode::saas()
                ? [['label' => 'Mój pakiet', 'route' => 'seller.package.show', 'icon' => '✨', 'owner' => true]]
                : []),
            ['label' => 'Produkty', 'route' => 'seller.products.index', 'active' => 'seller.products.*', 'icon' => '🏷️', 'section' => 'products'],
            ['label' => 'Zamówienia', 'route' => 'seller.orders.index', 'active' => 'seller.orders.*', 'icon' => '📦', 'badge' => (int) ($user->currentShop()?->unseen_orders_count ?? 0), 'section' => 'orders'],
            ['label' => 'Klienci', 'route' => 'seller.customers.index', 'active' => 'seller.customers.*', 'icon' => '👥', 'section' => 'customers'],
            ['label' => 'Kody rabatowe', 'route' => 'seller.discounts.index', 'active' => 'seller.discounts.*', 'icon' => '🎟️', 'section' => 'marketing'],
            ['label' => 'Wiadomości', 'route' => 'seller.mailings.index', 'active' => 'seller.mailings.*', 'icon' => '📣', 'section' => 'marketing'],
            ['label' => 'Analityka', 'route' => 'seller.analytics.index', 'icon' => '📊', 'section' => 'analytics'],
            ['label' => 'Informacje', 'route' => 'seller.pages.index', 'active' => 'seller.pages.*', 'icon' => '📄', 'section' => 'content'],
            ['label' => 'Wygląd', 'route' => 'seller.appearance.edit', 'icon' => '🎨', 'section' => 'content'],
            ['label' => 'Ustawienia', 'route' => 'seller.settings.edit', 'icon' => '⚙️', 'owner' => true],
            ['label' => 'Integracje', 'route' => 'seller.integrations.edit', 'icon' => '🔌', 'owner' => true],
        ];
        // Menu filtruje sie DOKLADNIE tym, czym bramkuja sie trasy
        // (`User::canAccess()`), bo pozycja prowadzaca w 403 wyglada jak awaria,
        // a nie jak brak uprawnien. `owner` chowa rzeczy, ktorych sprzedawca nie
        // oddaje razem z dzialem: pakiet, dane firmy, ustawienia, integracje.
        // Komentarz PHP, NIE bladowy — jestesmy w srodku bloku @php.
        $nav = array_values(array_filter($nav, function (array $item) use ($user): bool {
            if (($item['owner'] ?? false) && $user->isEmployee()) {
                return false;
            }

            $section = $item['section'] ?? null;

            return $section === null || $user->canAccess(\App\Enums\PanelSection::from($section));
        }));

        $initials = strtoupper(mb_substr($user->name ?? '?', 0, 1) . mb_substr($user->surname ?? '', 0, 1));
        $avatar = $user?->avatar_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar_path) : null;
    @endphp

    <div class="relative min-h-full">
        {{-- Miękkie kształty marki — kadrowanie na osobnej warstwie, żeby rodzic
             nie stał się przewijalnym kontenerem (patrz welcome.blade.php). --}}
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="pointer-events-none absolute -left-40 top-0 h-96 w-96 rounded-full bg-amber-300 opacity-25 blur-3xl"></div>
            <div class="pointer-events-none absolute right-0 top-1/2 h-96 w-96 rounded-full bg-rose-300 opacity-20 blur-3xl"></div>
        </div>

        <div class="relative flex min-h-full">
            {{-- Sidebar --}}
            <aside class="hidden w-64 shrink-0 flex-col lg:flex">
                {{-- Nagłówek marki o wysokości h-16 — zrównuje logo z nagłówkiem strony,
                     a treść poniżej z głównym boxem po prawej. --}}
                <div class="flex h-16 shrink-0 items-center gap-2 px-4">
                    <img src="{{ asset('images/kramio-logo.png') }}" alt="{{ config('app.name') }} — twój sklep w 15 minut" class="h-9 w-auto">
                    <span class="ml-auto rounded-full bg-white/70 px-2 py-0.5 text-[11px] font-medium text-stone-500">{{ $area }}</span>
                </div>

                <div class="px-4 pb-4 pt-6">
                <div class="flex items-center gap-3 rounded-2xl bg-white/70 p-3 backdrop-blur">
                    <a href="{{ route('profile.edit') }}" class="flex min-w-0 flex-1 items-center gap-3" title="Mój profil">
                        @if ($avatar)
                            <img src="{{ $avatar }}" alt="Awatar" class="h-9 w-9 shrink-0 rounded-full object-cover">
                        @else
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-rose-500 text-sm font-semibold text-white">{{ $initials }}</span>
                        @endif
                        <div class="min-w-0 text-sm">
                            <p class="truncate font-medium text-stone-900">{{ $user->name }} {{ $user->surname }}</p>
                            <p class="text-stone-500">{{ $area }}</p>
                        </div>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                        @csrf
                        <button type="submit" title="Wyloguj"
                            class="rounded-lg px-2 py-1 text-stone-400 transition hover:bg-stone-100 hover:text-stone-700">⎋</button>
                    </form>
                </div>

                <nav class="mt-6 space-y-1 text-sm">
                    <x-panel-nav-items :items="$nav" />
                </nav>
                </div>
            </aside>

            {{-- Treść --}}
            <div class="flex-1">
                <header class="flex h-16 items-center justify-between px-6">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" data-mobile-nav-open aria-label="Otwórz menu"
                            class="-ml-2 shrink-0 rounded-xl p-2 text-stone-600 transition hover:bg-white/70 lg:hidden">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-6 w-6">
                                <path d="M3 6h18M3 12h18M3 18h18" />
                            </svg>
                        </button>
                        @php
                            $greetName = \App\Support\Vocative::of($user->name);
                        @endphp
                        <h1 class="truncate text-lg font-semibold tracking-tight text-stone-900">{{ $heading ?? ($greetName ? 'Dzień dobry, '.$greetName : 'Dzień dobry') }}</h1>
                    </div>
                    <div class="flex items-center gap-3">
                        {{ $actions ?? '' }}
                    </div>
                </header>

                <main class="p-6">
                    {{-- Przerwa techniczna nad wszystkim innym: to komunikat o
                         stanie CAŁEJ platformy, więc dotyczy każdego ekranu i
                         każdej roli. Tylko w centrali — na storefroncie sprzedawcy
                         komunikat Kramio przejmowałby jego markę przed jego
                         własnym klientem. --}}
                    @if ($maintenanceNotice = \App\Models\PlatformSetting::maintenanceNotice())
                        <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            <p class="font-medium">Przerwa techniczna</p>
                            <p class="mt-1 text-amber-800">{{ $maintenanceNotice }}</p>
                        </div>
                    @endif

                    {{-- Stan abonamentu nad KAŻDYM ekranem panelu, nie tylko na
                         „Mój pakiet": w karencji sprzedawca musi zobaczyć termin
                         nawet wtedy, gdy przyszedł tu po coś innego, a po
                         wygaśnięciu — dowiedzieć się, dlaczego funkcje zniknęły.
                         Cicha karencja byłaby nieodróżnialna od „wszystko OK". --}}
                    @php($panelShop = $user->currentShop())

                    {{-- Zlecone usunięcie bije wszystko inne: sklep jest już
                         niewidoczny dla klientów, a za kilka dni zniknie razem
                         z kontem. Baner wisi nad KAŻDYM ekranem (także nad
                         „Mój pakiet"), bo termin biegnie niezależnie od tego,
                         po co sprzedawca tu przyszedł. --}}
                    @if ($panelShop?->deletion_scheduled_at !== null && ! request()->routeIs('seller.deletion.show'))
                        <div class="mb-6 rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            <p class="font-medium">Usuniemy Twój sklep {{ $panelShop->deletion_scheduled_at->format('d.m.Y') }}</p>
                            <p class="mt-1 text-xs text-rose-800">
                                Sklep jest już niewidoczny dla klientów. Do tego dnia wszystko wraca jednym kliknięciem —
                                <a href="{{ route('seller.deletion.show') }}" class="font-medium underline">zatrzymaj usunięcie</a>.
                            </p>
                        </div>
                    @endif

                    @if ($panelShop !== null && $panelShop->deletion_scheduled_at === null && ! request()->routeIs('seller.package.show'))
                        @if ($panelShop->inSubscriptionGrace())
                            {{-- Karencja NIEBIESKO, nie żółto: amber znaczy „zbliża
                                 się termin", róż „wygasło", a to trzeci stan z inną
                                 akcją — zapłać, choć nic jeszcze nie przestało
                                 działać. Jedyny zimny kolor w ciepłej palecie
                                 paneli, dlatego miękki `sky`, nie błękit. --}}
                            <div class="mb-6 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                                <p class="font-medium">Abonament pakietu {{ $panelShop->packageName() }} czeka na opłatę</p>
                                <p class="mt-1 text-xs text-sky-800">
                                    Termin minął {{ $panelShop->subscription_ends_at->format('d.m.Y') }}. Sklep działa normalnie
                                    @if ($panelShop->graceDaysLeft() > 0)
                                        jeszcze {{ $panelShop->graceDaysLeft() }} {{ trans_choice('{1}dzień|[2,4]dni|[5,*]dni', $panelShop->graceDaysLeft()) }} —
                                    @else
                                        do końca dnia —
                                    @endif
                                    <a href="{{ route('seller.package.show') }}" class="font-medium underline">opłać pakiet</a>, żeby nic się nie wyłączyło.
                                </p>
                            </div>
                        @elseif (! $panelShop->subscriptionActive())
                            <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                                <p class="font-medium">Pakiet {{ $panelShop->packageName() }} wygasł — sklep działa na zasadach pakietu {{ $panelShop->effectivePackageName() }}</p>
                                <p class="mt-1 text-xs text-rose-800">
                                    Nic nie zostało usunięte. Po opłaceniu wszystko wraca takie, jak było —
                                    <a href="{{ route('seller.package.show') }}" class="font-medium underline">przejdź do pakietu</a>.
                                </p>
                            </div>
                        @endif
                    @endif

                    {{ $slot }}

                </main>
            </div>
        </div>
    </div>

    {{-- Nawigacja mobilna: wysuwany panel (drawer). Widoczny tylko poniżej lg;
         sterowany przez resources/js/mobile-nav.js (zero zależności). --}}
    <div data-mobile-nav class="fixed inset-0 z-40 hidden lg:hidden">
        <div data-mobile-nav-backdrop class="absolute inset-0 bg-stone-900/30 backdrop-blur-sm"></div>
        <aside data-mobile-nav-panel
            class="absolute left-0 top-0 flex h-full w-72 max-w-[85%] -translate-x-full flex-col bg-stone-50 shadow-xl transition-transform duration-300 ease-out">
            <div class="flex h-16 shrink-0 items-center gap-2 px-4">
                <img src="{{ asset('images/kramio-logo.png') }}" alt="{{ config('app.name') }} — twój sklep w 15 minut" class="h-9 w-auto">
                <span class="ml-auto rounded-full bg-white/70 px-2 py-0.5 text-[11px] font-medium text-stone-500">{{ $area }}</span>
                <button type="button" data-mobile-nav-close aria-label="Zamknij menu"
                    class="rounded-lg p-1.5 text-stone-400 transition hover:bg-stone-200 hover:text-stone-700">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-5 w-5">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="px-4 pb-4 pt-4">
                <div class="flex items-center gap-3 rounded-2xl bg-white/70 p-3">
                    <a href="{{ route('profile.edit') }}" class="flex min-w-0 flex-1 items-center gap-3">
                        @if ($avatar)
                            <img src="{{ $avatar }}" alt="Awatar" class="h-9 w-9 shrink-0 rounded-full object-cover">
                        @else
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-rose-500 text-sm font-semibold text-white">{{ $initials }}</span>
                        @endif
                        <div class="min-w-0 text-sm">
                            <p class="truncate font-medium text-stone-900">{{ $user->name }} {{ $user->surname }}</p>
                            <p class="text-stone-500">{{ $area }}</p>
                        </div>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                        @csrf
                        <button type="submit" title="Wyloguj"
                            class="rounded-lg px-2 py-1 text-stone-400 transition hover:bg-stone-100 hover:text-stone-700">⎋</button>
                    </form>
                </div>

                <nav class="mt-6 space-y-1 text-sm">
                    <x-panel-nav-items :items="$nav" />
                </nav>
            </div>
        </aside>
    </div>

    @livewireScripts
    {{-- Zgoda dotyczy CAŁEJ domeny kramio.pl, a nie pojedynczej strony. Panel
         sam pomiaru nie ładuje, ale ta sama decyzja rządzi landingiem i
         dokumentami — więc pytamy też tutaj. Bez tego sprzedawca, który
         wyczyścił decyzję, zostawał bez możliwości podjęcia jej na nowo. --}}
    <x-cookie-consent owner="Kramio" privacy-url="/polityka-prywatnosci">logowanie i Twój panel działały poprawnie</x-cookie-consent>
</body>
</html>
