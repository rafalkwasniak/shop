<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($title ?? '') . (isset($title) ? ' · ' : '') . config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-stone-100 text-stone-800 antialiased">
    <x-toasts />

    <div class="relative min-h-full overflow-hidden">
        {{-- miękkie kształty marki --}}
        <div class="pointer-events-none absolute -left-32 -top-20 h-96 w-96 rounded-full bg-amber-300 opacity-30 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 -right-20 h-[28rem] w-[28rem] rounded-full bg-rose-300 opacity-30 blur-3xl"></div>

        <div class="relative mx-auto w-full max-w-6xl px-6 py-12">
            <a href="{{ url('/') }}" class="mb-8 inline-flex items-center gap-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-rose-500 text-white">◐</span>
                <span class="text-xl font-semibold tracking-tight text-stone-900">{{ config('app.name') }}</span>
            </a>

            {{ $slot }}
        </div>
    </div>
</body>
</html>
