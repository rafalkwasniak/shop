<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($title ?? 'Logowanie') . ' · ' . config('app.name') }}</title>
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/kramio-icon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <x-google-verification :code="config('services.google.site_verification')" />
    <x-google-analytics :id="config('services.google.analytics_id')" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-stone-100 text-stone-800 antialiased">
    <x-toasts />

    <div class="relative min-h-full">
        {{-- Miękkie kształty marki — kadrowanie na osobnej warstwie, żeby rodzic
             nie stał się przewijalnym kontenerem (patrz welcome.blade.php). --}}
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="pointer-events-none absolute -left-32 -top-20 h-96 w-96 rounded-full bg-amber-300 opacity-40 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-24 -right-20 h-[28rem] w-[28rem] rounded-full bg-rose-300 opacity-40 blur-3xl"></div>
            <div class="pointer-events-none absolute right-1/4 top-1/4 h-72 w-72 rounded-full bg-orange-200 opacity-30 blur-3xl"></div>
        </div>

        <div class="relative flex min-h-full items-center justify-center px-6 py-12">
            <div class="w-full max-w-md">
                {{-- Logo klikalne — jak w layoucie stron publicznych. Z logowania,
                     rejestracji, aktywacji i resetu hasła nie było jak wrócić na
                     stronę główną; nazwa platformy to naturalne wyjście.

                     Adres bezwzględny na centralę, bo tego layoutu używa też
                     strona wolnej subdomeny — `url('/')` prowadziłoby wtedy
                     z powrotem na tę samą subdomenę, a nie do Kramio. --}}
                <a href="{{ \App\Support\Central::url('/') }}" class="mb-6 flex items-center justify-center transition hover:opacity-80" title="Strona główna {{ config('app.name') }}">
                    <img src="{{ asset('images/kramio-logo.png') }}" alt="{{ config('app.name') }} — twój sklep w 15 minut" class="h-12 w-auto">
                </a>

                {{ $slot }}

            {{-- Zmiana decyzji o ciasteczkach — te layouty nie mają stopki,
                 więc link stoi dyskretnie pod treścią. Kontener blokowy, nie
                 akapit: „Ciasteczka" to formularz, a formularz w akapicie
                 przeglądarka wyrzuca poza niego — linki traciły wtedy
                 wyśrodkowanie (zgłosił Rafał 07.10). --}}
            <div class="mt-10 text-center text-sm text-stone-500">
                <x-cookie-settings-link class="inline" />
                <span class="px-1">·</span>
                <x-report-content-link class="inline" />
            </div>

            </div>
        </div>
    </div>
    <x-cookie-consent owner="Kramio" :privacy-url="\App\Support\Central::url('/polityka-prywatnosci')">logowanie i Twój panel działały poprawnie</x-cookie-consent>
</body>
</html>
