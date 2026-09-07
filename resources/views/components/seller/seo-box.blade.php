@props(['value' => null, 'preview' => null, 'hint' => null, 'sourceField' => null, 'nameField' => 'name'])

{{-- Box „SEO" — wspólny dla formularza produktu i sklepu. Nazwany wprost, żeby
     sprzedawca wiedział, że pisze pod wyszukiwarkę, a nie na stronę; z czasem
     dojdą tu kolejne pola (tytuł, słowa kluczowe).

     Licznik znaków jest ostrzeżeniem, nie blokadą: Google ucina opis po ~155
     znakach, ale dłuższy tekst nie jest błędem — po prostu nie cały się pokaże.
     Alpine jedzie z Livewire'em, który panel i tak ładuje. --}}
<div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur"
    x-data="{
        text: @js((string) $value),
        limit: {{ \App\Support\Seo::MAX_DESCRIPTION }},
        busy: false,
        error: null,
        // Wyczerpana pula to informacja, nie awaria — trzymamy ją osobno, żeby
        // pokazać ją spokojnym kafelkiem zamiast czerwonego komunikatu o błędzie.
        notice: null,
        async generate() {
            this.busy = true;
            this.error = null;
            this.notice = null;
            try {
                // Treść bierzemy WPROST Z FORMULARZA, nie z bazy — sprzedawca może
                // poprosić o opis do tego, co właśnie napisał, przed zapisaniem.
                const source = document.querySelector(@js($sourceField ? 'textarea[name=\''.$sourceField.'\'], input[name=\''.$sourceField.'\']' : 'body'));
                const name = document.querySelector(@js('[name=\''.$nameField.'\']'));
                const response = await fetch(@js(route('ai.seo-description')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ text: source ? source.value : '', name: name ? name.value : '' }),
                });
                const data = await response.json();
                if (! response.ok) {
                    // 429 = wyczerpana pula: spokojny kafelek, nie czerwony błąd.
                    if (response.status === 429) this.notice = data.message;
                    else this.error = data.message ?? 'Nie udało się napisać opisu.';

                    return;
                }
                // Tekst LĄDUJE W POLU, ale się nie zapisuje — sprzedawca widzi, co dostał,
                // może poprawić i dopiero zatwierdzić przyciskiem „Zapisz”.
                this.text = data.text;
                // Licznik zbijamy NATYCHMIAST — bez tego pokazywałby starą liczbę
                // aż do odświeżenia strony i wyglądałby na zepsuty.
                if (window.setAiQuota) window.setAiQuota(data.remaining);
            } catch (e) {
                this.error = 'Nie udało się połączyć z usługą AI.';
            } finally {
                this.busy = false;
            }
        },
    }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-semibold text-stone-900">SEO</h2>
        <span class="text-xs tabular-nums" :class="text.length > limit ? 'text-amber-700' : 'text-stone-400'"
            x-text="text.length + ' / ' + limit"></span>
    </div>
    <p class="mt-1 text-sm text-stone-500">
        Opis, który Google pokazuje pod tytułem w wynikach wyszukiwania. Jedno–dwa zdania zachęty.
    </p>

    <div class="mt-4">
        <label for="meta_description" class="sr-only">Opis SEO</label>
        <textarea id="meta_description" name="meta_description" rows="3" x-model="text" maxlength="255"
            placeholder="{{ $preview }}"
            class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">{{ old('meta_description', $value) }}</textarea>
        @error('meta_description')
            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
        @enderror

        {{-- Objaśnienie POD POLEM, a nad przyciskiem — tak jak przy pozostałych
             polach z AI w panelu: najpierw pole, potem zasada, na końcu akcja. --}}
        <p class="mt-2 text-xs text-stone-400">
            {{ $hint ?? 'Zostaw puste, a opis ułożymy automatycznie z Twojej treści.' }}
            Po wpisaniu własnego tekstu zostaje on na stałe — automat go nie zmieni. Aby wrócić do automatycznego, wyczyść pole.
        </p>

        @if ($sourceField)
            {{-- Przycisk domyka lukę, którą tworzy reguła „ręczna edycja wygrywa":
                 kto raz napisał opis sam, bez tego przycisku nie mógłby już poprosić
                 automatu o nową wersję. --}}
            @php($quotaLeft = auth()->user()?->currentShop() ? app(\App\Services\AiQuota::class)->remaining(auth()->user()->currentShop()) : null)
            {{-- Blok AI wyrównany DO PRAWEJ — tak samo jak przy edytorze tekstu,
                 żeby przyciski AI w całym panelu trzymały jedną krawędź. --}}
            <div class="mt-3 text-right">
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <span x-show="error" x-cloak class="text-sm text-rose-600" x-text="error"></span>
                    <button type="button" data-ai-generate x-on:click="generate()" :disabled="busy" @disabled($quotaLeft === 0)
                        class="rounded-xl border border-stone-200 bg-white px-3 py-1.5 text-sm font-medium text-stone-700 transition hover:bg-stone-100 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="! busy">✨ Wygeneruj z AI</span>
                        <span x-show="busy" x-cloak>Piszę…</span>
                    </button>
                </div>
                {{-- Wyczerpana pula: kafelek w tonie marki, z ikoną i wyraźną datą
                     powrotu. Ten sam spokojny język, co przy kodach rabatowych. --}}
                <div x-show="notice" x-cloak class="mt-3 flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-left">
                    <span class="text-lg leading-none" aria-hidden="true">✨</span>
                    <p class="min-w-0 text-sm text-amber-900" x-text="notice"></p>
                </div>

                <div x-show="! notice"><x-seller.ai-quota /></div>
            </div>
        @endif
    </div>
</div>
