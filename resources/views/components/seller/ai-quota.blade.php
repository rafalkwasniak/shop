@props(['inline' => false])

@php
    // Licznik dotyczy sklepu zalogowanego sprzedawcy. Bez sklepu (świeże konto
    // przed aktywacją) nie ma czego pokazywać.
    $shop = auth()->user()?->currentShop();
    $quota = $shop ? app(\App\Services\AiQuota::class) : null;
    $remaining = $quota?->remaining($shop);
    $limit = $shop ? \App\Services\AiQuota::limitFor($shop) : 0;
    $resetsAt = \App\Services\AiQuota::resetsAt()->translatedFormat('l, j F');
@endphp

@if ($shop && $limit > 0)
    {{-- Ile użyć AI zostało w tym tygodniu. Pokazujemy PRZED kliknięciem, a nie
         dopiero po wyczerpaniu — sprzedawca ma planować pracę, a nie odbijać się
         od komunikatu w połowie uzupełniania sklepu.

         Oba warianty (zostało / wyczerpane) są w DOM od razu i przełącza je
         `window.setAiQuota()`. Dzięki temu licznik spada zaraz po użyciu AI,
         bez odświeżania strony — inaczej sprzedawca klika i widzi wciąż tę samą
         liczbę, co wygląda jak zepsuty licznik. --}}
    <div data-ai-quota class="{{ $inline ? 'mt-1' : 'mt-2' }} text-xs">
        <p data-ai-quota-left class="text-stone-400 {{ $remaining === 0 ? 'hidden' : '' }}">
            Pozostało <span data-ai-quota-remaining class="font-medium tabular-nums">{{ $remaining }}</span>
            z {{ $limit }} użyć AI w tym tygodniu.
        </p>
        <p data-ai-quota-empty class="text-amber-700 {{ $remaining > 0 ? 'hidden' : '' }}">
            Limit AI na ten tydzień wykorzystany. Wróci {{ $resetsAt }} — w wyższym pakiecie jest większy.
        </p>
    </div>
@endif

@once
    @if ($shop && $limit > 0)
        {{-- Skrypt inline (nie w paczce Vite), żeby licznik działał od razu i nie
             wymagał przebudowania assetów przy każdej zmianie tekstu. --}}
        <script>
            window.setAiQuota = function (remaining) {
                if (remaining === null || remaining === undefined) return;

                document.querySelectorAll('[data-ai-quota]').forEach((box) => {
                    const left = box.querySelector('[data-ai-quota-left]');
                    const empty = box.querySelector('[data-ai-quota-empty]');
                    const value = box.querySelector('[data-ai-quota-remaining]');

                    if (value) value.textContent = remaining;
                    if (left) left.classList.toggle('hidden', remaining === 0);
                    if (empty) empty.classList.toggle('hidden', remaining > 0);
                });

                // Pusta pula = martwy przycisk. Wyłączamy go od razu, żeby kolejne
                // kliknięcie nie kończyło się komunikatem o wyczerpaniu.
                if (remaining === 0) {
                    document.querySelectorAll('[data-ai-button], [data-ai-generate]')
                        .forEach((button) => { button.disabled = true; });
                }
            };
        </script>
    @endif
@endonce
