@props(['type' => 'info'])

@php
    // Bez tła — samo obramowanie w kolorze tekstu, zaokrąglone rogi. Spójne w całej apce.
    $styles = [
        'info' => 'border-amber-300 text-amber-800',
        'success' => 'border-emerald-300 text-emerald-800',
        'error' => 'border-rose-300 text-rose-700',
    ];
    $classes = $styles[$type] ?? $styles['info'];
@endphp

<div {{ $attributes->merge(['class' => "rounded-2xl border px-4 py-3 text-sm $classes"]) }}>
    {{ $slot }}
</div>
