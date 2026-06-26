@php
    // Flash → toast. Klucz sesji => wariant. Spójny wygląd w całej apce:
    // biała apla (czytelna nad treścią), obramowanie i tekst w kolorze wariantu,
    // pasek odliczający na dole. Sterowanie czasem/zamykaniem: resources/js/app.js.
    $variants = [
        'success' => ['border' => 'border-emerald-300', 'text' => 'text-emerald-800', 'bar' => 'bg-emerald-400'],
        'error' => ['border' => 'border-rose-300', 'text' => 'text-rose-700', 'bar' => 'bg-rose-400'],
        'status' => ['border' => 'border-amber-300', 'text' => 'text-amber-800', 'bar' => 'bg-amber-400'],
    ];

    $toasts = [];
    foreach ($variants as $key => $style) {
        if (session()->has($key)) {
            $toasts[] = ['message' => session($key), 'style' => $style];
        }
    }
@endphp

<div id="toast-region" class="pointer-events-none fixed right-4 top-4 z-50 flex w-80 max-w-[90vw] flex-col gap-3" aria-live="polite">
    @foreach ($toasts as $toast)
        <div data-toast data-toast-duration="30000"
            class="toast-enter pointer-events-auto overflow-hidden rounded-2xl border bg-white shadow-lg shadow-stone-900/10 {{ $toast['style']['border'] }}">
            <div class="flex items-start gap-3 px-4 py-3">
                <p class="flex-1 text-sm {{ $toast['style']['text'] }}">{{ $toast['message'] }}</p>
                <button type="button" data-toast-close aria-label="Zamknij"
                    class="-mr-1 -mt-0.5 shrink-0 rounded-md p-1 text-stone-400 transition hover:bg-stone-100 hover:text-stone-600">✕</button>
            </div>
            <div class="h-1 w-full bg-stone-100">
                <div data-toast-bar class="h-full w-full {{ $toast['style']['bar'] }}"></div>
            </div>
        </div>
    @endforeach
</div>
