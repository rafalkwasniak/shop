@php($rose = $tone === 'rose')
<div>
    @if ($asking)
        {{-- NAKŁADKA przykrywa cały kafelek, więc nie da się potwierdzić „przy
             okazji", patrząc na coś innego; rodzic musi mieć `relative`.
             W WIERSZU (`inline`) pytanie staje w miejscu przycisku i rozpycha
             wiersz — dla list, w których na nakładkę nie ma wysokości.

             `overflow-auto`: kafelek bywa wąski (cztery kolumny na dużym ekranie,
             dwie na telefonie), a rodzic ma `overflow-hidden` — bez tego dłuższe
             wyjaśnienie zostałoby ucięte razem z przyciskami. --}}
        <div @class([
            'flex flex-col gap-3 text-left',
            'absolute inset-0 z-10 justify-center overflow-auto p-4' => ! $inline,
            'rounded-2xl border p-3' => $inline,
            'bg-rose-50' => $rose,
            'bg-amber-50' => ! $rose,
            'border-rose-300' => $inline && $rose,
            'border-amber-300' => $inline && ! $rose,
        ])>
            <p @class(['text-sm font-semibold', 'text-rose-900' => $rose, 'text-amber-900' => ! $rose])>{{ $title }}</p>

            @if ($lines !== [])
                <ul @class(['space-y-1 text-xs', 'text-rose-800' => $rose, 'text-amber-800' => ! $rose])>
                    @foreach ($lines as $line)
                        <li>• {{ $line }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="flex flex-wrap items-center gap-2">
                {{-- Zwykły POST na trasę kontrolera: autoryzacja i sprzątanie
                     zostają tam, gdzie były. --}}
                <form method="POST" action="{{ $action }}">
                    @csrf
                    <button type="submit" @class([
                        'rounded-lg px-3 py-1.5 text-xs font-semibold text-white transition',
                        'bg-rose-600 hover:bg-rose-700' => $rose,
                        'bg-amber-600 hover:bg-amber-700' => ! $rose,
                    ])>
                        {{ $confirmLabel }}
                    </button>
                </form>
                <button type="button" wire:click="cancel" @class([
                    'rounded-lg border bg-white px-3 py-1.5 text-xs font-medium transition',
                    'border-rose-300 text-rose-800 hover:bg-rose-100' => $rose,
                    'border-amber-300 text-amber-800 hover:bg-amber-100' => ! $rose,
                ])>
                    Anuluj
                </button>
            </div>
        </div>
    @endif

    {{-- W trybie „w wierszu" przycisk ustępuje miejsca pytaniu — inaczej wiersz
         miałby dwa wezwania do tego samego. Przy nakładce zostaje pod spodem. --}}
    @unless ($inline && $asking)
        <button type="button" wire:click="ask" title="{{ $label }}" aria-label="{{ $label }}"
            @class([
                $triggerClass => $triggerClass !== '',
                'inline-flex items-center justify-center gap-1.5 rounded-lg border p-1.5 text-xs font-medium transition' => $triggerClass === '',
                'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100' => $triggerClass === '' && $rose,
                'border-stone-200 bg-white/70 text-stone-600 hover:bg-white hover:text-stone-900' => $triggerClass === '' && ! $rose,
                'px-2' => $triggerClass === '' && $text !== '',
            ])>
            @if ($icon === 'copy')
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                    <rect x="9" y="9" width="13" height="13" rx="2" /><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                </svg>
            @elseif ($icon === 'trash')
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                    <path d="M3 6h18" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" />
                </svg>
            @endif

            @if ($text !== '')
                {{ $text }}
            @endif
        </button>
    @endunless
</div>
