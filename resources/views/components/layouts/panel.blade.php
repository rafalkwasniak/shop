<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($title ?? 'Panel') . ' · ' . config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-stone-100 text-stone-800 antialiased">
    <x-toasts />

    @php
        $user = auth()->user();
        $isAdmin = $user?->isAdmin();
        $area = $isAdmin ? 'Administrator' : 'Sprzedawca';
        $nav = $isAdmin ? [
            ['label' => 'Pulpit', 'route' => 'administrator.dashboard', 'icon' => '🏠'],
            ['label' => 'Sklepy', 'route' => null, 'icon' => '🛍️'],
            ['label' => 'Sprzedawcy', 'route' => null, 'icon' => '👥'],
            ['label' => 'Zamówienia', 'route' => null, 'icon' => '📦'],
            ['label' => 'Pakiety', 'route' => null, 'icon' => '✨'],
            ['label' => 'Ustawienia', 'route' => null, 'icon' => '⚙️'],
        ] : [
            ['label' => 'Pulpit', 'route' => 'seller.dashboard', 'icon' => '🏠'],
            ['label' => 'Mój sklep', 'route' => null, 'icon' => '🛍️'],
            ['label' => 'Produkty', 'route' => null, 'icon' => '🏷️'],
            ['label' => 'Zamówienia', 'route' => null, 'icon' => '📦'],
            ['label' => 'Wygląd', 'route' => null, 'icon' => '🎨'],
            ['label' => 'Ustawienia', 'route' => null, 'icon' => '⚙️'],
        ];
        $initials = strtoupper(mb_substr($user->name ?? '?', 0, 1) . mb_substr($user->surname ?? '', 0, 1));
    @endphp

    <div class="relative min-h-full overflow-hidden">
        <div class="pointer-events-none absolute -left-40 top-0 h-96 w-96 rounded-full bg-amber-300 opacity-25 blur-3xl"></div>
        <div class="pointer-events-none absolute right-0 top-1/2 h-96 w-96 rounded-full bg-rose-300 opacity-20 blur-3xl"></div>

        <div class="relative flex min-h-full">
            {{-- Sidebar --}}
            <aside class="hidden w-64 shrink-0 flex-col p-4 lg:flex">
                <div class="flex items-center gap-2 px-3 py-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-rose-500 text-white">◐</span>
                    <span class="text-lg font-semibold tracking-tight text-stone-900">{{ config('app.name') }}</span>
                    <span class="ml-auto rounded-full bg-white/70 px-2 py-0.5 text-[11px] font-medium text-stone-500">{{ $area }}</span>
                </div>

                <nav class="mt-6 space-y-1 text-sm">
                    @foreach ($nav as $item)
                        @php $active = $item['route'] && request()->routeIs($item['route']); @endphp
                        <a href="{{ $item['route'] ? route($item['route']) : '#' }}"
                           @class([
                               'flex items-center gap-3 rounded-2xl px-4 py-2.5 transition',
                               'bg-white font-medium text-stone-900 shadow-sm' => $active,
                               'text-stone-500 hover:bg-white/60' => ! $active,
                           ])>
                            <span class="text-base leading-none">{{ $item['icon'] }}</span>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="mt-auto flex items-center gap-3 rounded-2xl bg-white/70 p-3 backdrop-blur">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-rose-500 text-sm font-semibold text-white">{{ $initials }}</span>
                    <div class="min-w-0 text-sm">
                        <p class="truncate font-medium text-stone-900">{{ $user->name }} {{ $user->surname }}</p>
                        <p class="text-stone-500">{{ $area }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                        @csrf
                        <button type="submit" title="Wyloguj"
                            class="rounded-lg px-2 py-1 text-stone-400 transition hover:bg-stone-100 hover:text-stone-700">⎋</button>
                    </form>
                </div>
            </aside>

            {{-- Treść --}}
            <div class="flex-1">
                <header class="flex h-16 items-center justify-between px-6">
                    <h1 class="text-lg font-semibold tracking-tight text-stone-900">{{ $heading ?? 'Dzień dobry, ' . $user->name }}</h1>
                    <div class="flex items-center gap-3">
                        {{ $actions ?? '' }}
                    </div>
                </header>

                <main class="p-6">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </div>
</body>
</html>
