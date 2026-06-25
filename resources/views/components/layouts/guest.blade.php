<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($title ?? 'Logowanie') . ' · ' . config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-stone-100 text-stone-800 antialiased">
    <div class="relative min-h-full overflow-hidden">
        {{-- miękkie kształty marki --}}
        <div class="pointer-events-none absolute -left-32 -top-20 h-96 w-96 rounded-full bg-amber-300 opacity-40 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 -right-20 h-[28rem] w-[28rem] rounded-full bg-rose-300 opacity-40 blur-3xl"></div>
        <div class="pointer-events-none absolute right-1/4 top-1/4 h-72 w-72 rounded-full bg-orange-200 opacity-30 blur-3xl"></div>

        <div class="relative flex min-h-full items-center justify-center px-6 py-12">
            <div class="w-full max-w-md">
                <div class="mb-6 flex items-center justify-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-rose-500 text-white">◐</span>
                    <span class="text-xl font-semibold tracking-tight text-stone-900">{{ config('app.name') }}</span>
                </div>

                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
