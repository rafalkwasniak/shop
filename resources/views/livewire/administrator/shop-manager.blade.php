<div class="space-y-6">
    {{-- Nagłówek sklepu --}}
    <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-stone-900">{{ $shop->name }}</h2>
                <p class="mt-1 text-sm text-stone-500">
                    {{ trim(($shop->owner?->name ?? '').' '.($shop->owner?->surname ?? '')) ?: '—' }}
                    @if ($shop->owner?->email)· {{ $shop->owner->email }}@endif
                </p>
                <p class="mt-0.5 text-xs text-stone-400">{{ $shop->slug }}</p>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-3 py-1 text-sm font-medium text-stone-700">
                {{ $shop->packageName() }}
            </span>
        </div>
    </div>

    {{-- Preset pakietu --}}
    <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
        <h3 class="font-semibold text-stone-900">Nadaj pakiet</h3>
        <p class="mt-1 text-sm text-stone-500">Wypełnia pola wartościami pakietu. Możesz potem nadpisać pojedyncze opcje, a zapis zatwierdza całość.</p>
        <div class="mt-4 flex flex-wrap gap-3">
            @foreach (config('shop.packages') as $slug => $pkg)
                <button type="button" wire:click="applyPreset('{{ $slug }}')"
                    @class([
                        'rounded-2xl border px-5 py-3 text-left transition',
                        'border-amber-400 bg-amber-50/70 shadow-sm' => $package === $slug,
                        'border-stone-200 bg-white/60 hover:bg-white' => $package !== $slug,
                    ])>
                    <span class="block text-sm font-semibold text-stone-900">{{ $pkg['name'] }}</span>
                    <span class="mt-0.5 block text-xs text-stone-500">
                        {{ (int) $pkg['price_yearly'] > 0 ? number_format($pkg['price_yearly'], 0, ',', ' ').' zł / rok' : 'za darmo' }}
                    </span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Formularz zapisu --}}
    <form wire:submit="save" class="space-y-6">
        {{-- Uprawnienia --}}
        <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
            <h3 class="font-semibold text-stone-900">Uprawnienia</h3>
            <p class="mt-1 text-sm text-stone-500">Każdą funkcję można włączyć niezależnie od pakietu (deal per sklep).</p>

            <div class="mt-5 space-y-3">
                @foreach ($this->booleanEntitlements() as $key => $label)
                    <label class="flex cursor-pointer items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-white/60 px-4 py-3">
                        <span class="text-sm font-medium text-stone-800">{{ $label }}</span>
                        <input type="checkbox" wire:model="{{ $key }}"
                            class="h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20">
                    </label>
                @endforeach

                {{-- Uprawnienia LICZBOWE lecą z jednej listy (`numericEntitlements()`),
                     tak jak przełączniki obok. Wypisane z ręki gubiły się przy
                     dokładaniu nowego: zapis pisze CAŁY snapshot, więc klucz bez
                     pola w formularzu znikał przy każdym „Zapisz" i kasował ręczne
                     nadanie. Zdarzyło się to przy puli AI, a potem przy kontach
                     pracowniczych. --}}
                @foreach ($this->numericEntitlements() as $key => $label)
                    <div class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-white/60 px-4 py-3">
                        <label for="{{ $key }}" class="text-sm font-medium text-stone-800">{{ $label }}</label>
                        <input type="number" id="{{ $key }}" wire:model="{{ $key }}" min="0" style="width: 7rem"
                            class="rounded-xl border border-stone-200 bg-white px-3 py-2 text-right text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    </div>
                    @error($key) <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                @endforeach
            </div>
        </div>

        {{-- Abonament --}}
        <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
            <h3 class="font-semibold text-stone-900">Abonament</h3>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="price_yearly" class="block text-sm font-medium text-stone-700">Cena roczna (brutto, zł)</label>
                    <input type="number" id="price_yearly" wire:model="price_yearly" min="0" step="1"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    @error('price_yearly') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="subscription_ends_at" class="block text-sm font-medium text-stone-700">Koniec abonamentu</label>
                    <input type="date" id="subscription_ends_at" wire:model="subscription_ends_at"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    <p class="mt-1.5 text-xs text-stone-400">Puste = nieustalone. Przy „gratis" i tak nie wygasa.</p>
                    @error('subscription_ends_at') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <label class="mt-5 flex cursor-pointer items-start gap-3 rounded-2xl border border-stone-200 bg-white/60 p-4">
                <input type="checkbox" wire:model="comped"
                    class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-emerald-600 focus:ring-4 focus:ring-amber-500/20">
                <span>
                    <span class="block text-sm font-medium text-stone-800">Dostęp gratisowy (comped)</span>
                    <span class="mt-0.5 block text-xs text-stone-500">Nie wygasa i omija auto-zejście — dla testerów i znajomych.</span>
                </span>
            </label>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                Zapisz
            </button>
        </div>
    </form>
</div>
