@props([
    'field',
    'target',
    'label' => 'Popraw przez AI',
])

@php
    // Tygodniowy limit sklepu (inny niż limit „na pole", którego pilnuje JS):
    // po wyczerpaniu przycisk jest wyłączony, żeby kliknięcie nie kończyło się
    // komunikatem o błędzie za każdym razem.
    $shop = auth()->user()?->currentShop();
    $quotaLeft = $shop ? app(\App\Services\AiQuota::class)->remaining($shop) : null;
@endphp

{{-- Wywołuje redakcję AI pola o id $target. JS (resources/js/ai.js) pilnuje
     limitu użyć na pole/ładowanie strony. --}}
<button type="button"
    @disabled($quotaLeft === 0)
    data-ai-button
    data-ai-field="{{ $field }}"
    data-ai-target="{{ $target }}"
    data-ai-url="{{ route('ai.improve') }}"
    data-ai-max="{{ (int) config('shop.ai.max_uses_per_field') }}"
    {{-- Docelowa wielkość fragmentu; JS tnie po blokach, żeby długi tekst nie
         przekroczył timeoutu w jednym wywołaniu. --}}
    data-ai-chunk="{{ (int) config('ai.chunk_chars') }}"
    {{-- Streaming: tekst pojawia się w polu w trakcie pisania przez model.
         Atrybut steruje też współbieżnością w JS (strumień = fragmenty po
         kolei, żeby dwa nie pisały w polu naraz). Brak atrybutu = ścieżka
         JSON jak dotąd — to wyłącznik awaryjny AI_STREAMING w .env. --}}
    @if (config('ai.streaming')) data-ai-stream @endif
    data-ai-label="{{ $label }}"
    {{ $attributes->merge(['class' => 'mt-2 inline-flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-800 transition-colors hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50']) }}>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4">
        <path d="M9 1.5 10.2 5 13.5 6.2 10.2 7.4 9 10.9 7.8 7.4 4.5 6.2 7.8 5 9 1.5Z" />
        <path d="M17 9.5 18 12.5 21 13.5 18 14.5 17 17.5 16 14.5 13 13.5 16 12.5 17 9.5Z" />
    </svg>
    {{-- tabular-nums: podczas pracy etykieta tyka sekundami, cyfry o równej
         szerokości nie rozpychają przycisku co sekundę. --}}
    <span data-ai-text class="tabular-nums">{{ $label }}</span>
</button>
