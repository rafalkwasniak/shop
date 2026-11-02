@props([
    'name',
    'value' => '',
    'aiField' => null,
    'max' => null,
    // Zdjęcia w treści: adres wgrywania + licznik [użyte, limit]. Tylko strony
    // „Informacje" — opisy produktów i sklepu zdjęć w treści nie mają.
    'imageUploadUrl' => null,
    'imageUsage' => null,
])

@php($toolbarId = $name.'-toolbar')

{{-- Edytor tekstu sformatowanego (Trix) — komponent wielokrotnego użytku.
     Ukryte pole trzyma HTML; konwersja starych <h1> na <h2>. Własny polski pasek
     z ikonami SVG; bez cytatu/kodu/załączników. Licznik znaków i (opcjonalnie)
     przycisk „Popraw przez AI". --}}
<input id="{{ $name }}" type="hidden" name="{{ $name }}"
    value="{{ str_replace(['<h1>', '</h1>'], ['<h2>', '</h2>'], (string) $value) }}">

<trix-toolbar id="{{ $toolbarId }}">
    <div class="trix-button-row">
        <span class="trix-button-group trix-button-group--text-tools" data-trix-button-group="text-tools">
            <button type="button" class="trix-button" data-trix-attribute="bold" data-trix-key="b" title="Pogrubienie" tabindex="-1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 12a4 4 0 0 0 0-8H6v8"/><path d="M15 20a4 4 0 0 0 0-8H6v8Z"/></svg>
            </button>
            <button type="button" class="trix-button" data-trix-attribute="italic" data-trix-key="i" title="Kursywa" tabindex="-1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>
            </button>
            <button type="button" class="trix-button" data-trix-attribute="strike" title="Przekreślenie" tabindex="-1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4H9a3 3 0 0 0-2.83 4"/><path d="M14 12a4 4 0 0 1 0 8H6"/><line x1="4" y1="12" x2="20" y2="12"/></svg>
            </button>
            <button type="button" class="trix-button" data-trix-attribute="href" data-trix-action="link" data-trix-key="k" title="Odnośnik" tabindex="-1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            </button>
        </span>
        <span class="trix-button-group trix-button-group--block-tools" data-trix-button-group="block-tools">
            <button type="button" class="trix-button" data-trix-attribute="heading1" title="Nagłówek" tabindex="-1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6v12"/><path d="M16 6v12"/><path d="M6 12h10"/></svg>
            </button>
            <button type="button" class="trix-button" data-trix-attribute="bullet" title="Lista punktowana" tabindex="-1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            </button>
            <button type="button" class="trix-button" data-trix-attribute="number" title="Lista numerowana" tabindex="-1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>
            </button>
            @if ($imageUploadUrl)
                {{-- Nie `data-trix-action` — własny przycisk; obsługa w skrypcie niżej. --}}
                <button type="button" class="trix-button" data-image-button data-for="{{ $name }}" title="Wstaw zdjęcie" tabindex="-1">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/></svg>
                </button>
            @endif
        </span>
        <span class="trix-button-group-spacer"></span>
        <span class="trix-button-group trix-button-group--history-tools" data-trix-button-group="history-tools">
            <button type="button" class="trix-button" data-trix-action="undo" data-trix-key="z" title="Cofnij" tabindex="-1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg>
            </button>
            <button type="button" class="trix-button" data-trix-action="redo" data-trix-key="shift+z" title="Ponów" tabindex="-1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 14 5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/></svg>
            </button>
        </span>
    </div>
    <div class="trix-dialogs" data-trix-dialogs>
        <div class="trix-dialog trix-dialog--link" data-trix-dialog="href" data-trix-dialog-attribute="href">
            <div class="trix-dialog__link-fields">
                <input type="url" name="href" class="trix-input trix-input--dialog" placeholder="Wklej lub wpisz adres" aria-label="Adres URL" required data-trix-input>
                <div class="trix-button-group">
                    <input type="button" class="trix-button trix-button--dialog" value="Wstaw" data-trix-method="setAttribute">
                    <input type="button" class="trix-button trix-button--dialog" value="Usuń" data-trix-method="removeAttribute">
                </div>
            </div>
        </div>
    </div>
</trix-toolbar>
<trix-editor input="{{ $name }}" toolbar="{{ $toolbarId }}" class="trix-content"></trix-editor>

@if ($imageUploadUrl)
    <input type="file" accept="image/jpeg,image/png,image/webp" hidden
        data-image-input data-for="{{ $name }}" data-upload-url="{{ $imageUploadUrl }}">

    {{-- Wstawianie zdjęć: wgranie przez fetch i `<img>` do edytora. Skrypt
         WBUDOWANY, nie w app.js — bez przebudowy front-endu. app.js blokuje
         przeciąganie plików do Trixa (`trix-file-accept`) i tak zostaje:
         jedyna droga to ten przycisk, który pilnuje limitu i zmniejsza zdjęcie. --}}
    @once
        <script>
            document.addEventListener('click', function (event) {
                var button = event.target.closest('[data-image-button]');
                if (!button) return;
                var input = document.querySelector('[data-image-input][data-for="' + button.dataset.for + '"]');
                if (input) input.click();
            });

            document.addEventListener('change', function (event) {
                var input = event.target.closest('[data-image-input]');
                if (!input || !input.files.length) return;

                var editor = document.querySelector('trix-editor[input="' + input.dataset.for + '"]');
                var button = document.querySelector('[data-image-button][data-for="' + input.dataset.for + '"]');
                var body = new FormData();
                body.append('image', input.files[0]);
                input.value = '';
                button.disabled = true;

                fetch(input.dataset.uploadUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: body,
                })
                    .then(function (response) {
                        return response.json().then(function (data) { return { ok: response.ok, data: data }; });
                    })
                    .then(function (result) {
                        if (!result.ok) {
                            var errors = result.data.errors && result.data.errors.image;
                            throw new Error(errors ? errors[0] : (result.data.message || 'Nie udało się wgrać zdjęcia.'));
                        }
                        editor.focus();
                        editor.editor.insertHTML('<img src="' + result.data.src + '" width="' + result.data.width + '" height="' + result.data.height + '" alt="">');
                        document.querySelectorAll('[data-image-used][data-for="' + input.dataset.for + '"]').forEach(function (el) {
                            el.textContent = result.data.used;
                        });
                    })
                    .catch(function (error) {
                        if (window.showToast) window.showToast(error.message || 'Nie udało się wgrać zdjęcia.', 'error');
                    })
                    .finally(function () { button.disabled = false; });
            });
        </script>
    @endonce
@endif

<div class="mt-1 flex items-start justify-between gap-3">
    <p class="text-xs text-stone-400">
        {{ $slot }}
        @if ($max)
            <span class="whitespace-nowrap text-stone-500"><span data-rich-count data-for="{{ $name }}">0</span> / {{ $max }} znaków (z formatowaniem)</span>
        @endif
        @if ($imageUploadUrl && $imageUsage)
            {{-- Limit „praktycznie bez końca" (sklep dedykowany) nie ma czego pokazywać. --}}
            <span class="whitespace-nowrap text-stone-500">· Zdjęcia w stronach: <span data-image-used data-for="{{ $name }}">{{ $imageUsage[0] }}</span>@if ($imageUsage[1] < 10000) z {{ $imageUsage[1] }}@endif</span>
        @endif
    </p>
    @if ($aiField)
        {{-- Wyrównanie DO PRAWEJ: szerokość bloku wyznacza dłuższy od przycisku
             licznik użyć, więc bez tego przycisk odklejałby się od prawej krawędzi
             i wyglądał na zgubiony w środku wiersza. --}}
        <div class="shrink-0 text-right">
            <x-ai-improve-button :field="$aiField" :target="$name" class="mt-0" />
            <x-seller.ai-quota inline />
        </div>
    @endif
</div>
