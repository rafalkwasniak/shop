<div>
    @php($canOrder = count($deliveryOptions) > 0 && count($paymentOptions) > 0)

    @if ($lines->isEmpty())
        <div class="st-card st-border mx-auto max-w-lg rounded-3xl border px-8 py-16 text-center">
            <p class="text-lg font-semibold">Twój koszyk jest pusty</p>
            <p class="mt-1 opacity-70">Dodaj produkty, zanim przejdziesz do kasy.</p>
            <a href="/" wire:navigate class="st-btn mt-6 inline-block rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">Wróć do sklepu</a>
        </div>
    @elseif (! $canOrder)
        <div class="st-card st-border mx-auto max-w-lg rounded-3xl border px-8 py-12 text-center">
            <p class="text-lg font-semibold">Sklep nie przyjmuje jeszcze zamówień</p>
            <p class="mt-1 opacity-70">Sprzedawca nie skonfigurował dostawy lub płatności. Zajrzyj później.</p>
            <a href="/koszyk" wire:navigate class="mt-6 inline-block text-sm underline underline-offset-4 opacity-70 hover:opacity-100">Wróć do koszyka</a>
        </div>
    @else
        {{-- Komunikaty finalnej weryfikacji (auto-korekta) --}}
        @if (! empty($reviewMessages))
            <div class="st-border mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
                <p class="font-semibold">Zawartość koszyka wymaga sprawdzenia:</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    @foreach ($reviewMessages as $msg)
                        <li>{{ $msg }}</li>
                    @endforeach
                </ul>
                <a href="/koszyk" wire:navigate class="mt-2 inline-block font-medium underline underline-offset-2">Przejdź do koszyka</a>
            </div>
        @endif

        <form wire:submit="place" class="grid gap-8 lg:grid-cols-3">
            {{-- Formularz --}}
            <div class="space-y-6 lg:col-span-2">
                {{-- Dane kupującego --}}
                <div class="st-card st-border rounded-3xl border p-6">
                    <h2 class="st-brand st-box-title">Dane kupującego</h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="buyer_name" class="block text-sm opacity-80">Imię</label>
                            <input type="text" id="buyer_name" wire:model="buyer_name" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                            @error('buyer_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="buyer_surname" class="block text-sm opacity-80">Nazwisko</label>
                            <input type="text" id="buyer_surname" wire:model="buyer_surname" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                            @error('buyer_surname') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="buyer_email" class="block text-sm opacity-80">E-mail</label>
                            <input type="email" id="buyer_email" wire:model.blur="buyer_email" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                            @error('buyer_email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="buyer_phone" class="block text-sm opacity-80">Telefon</label>
                            <input type="text" id="buyer_phone" wire:model="buyer_phone" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                            @error('buyer_phone') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Kupuję jako firma --}}
                    <label class="mt-5 flex items-center gap-3 text-sm">
                        <input type="checkbox" wire:model.live="is_company" class="st-border h-5 w-5 rounded border" style="accent-color: var(--brand);">
                        Kupuję jako firma
                    </label>

                    @if ($is_company)
                        <div class="mt-4 space-y-4">
                            {{-- NIP + auto-uzupełnienie --}}
                            <div>
                                <label for="company_nip" class="block text-sm opacity-80">NIP</label>
                                <div class="mt-1 flex gap-2">
                                    <input type="text" id="company_nip" wire:model="company_nip" class="st-border block flex-1 rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                    <button type="button" wire:click="lookupCompany" wire:loading.attr="disabled" wire:target="lookupCompany"
                                        class="st-border shrink-0 rounded-xl border px-4 py-2.5 text-sm font-medium transition hover:brightness-95 disabled:opacity-60">
                                        <span wire:loading.remove wire:target="lookupCompany">Pobierz dane</span>
                                        <span wire:loading wire:target="lookupCompany">Pobieram…</span>
                                    </button>
                                </div>
                                @error('company_nip') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                <p class="mt-1 text-xs opacity-60">Wpisz NIP i kliknij „Pobierz dane", aby uzupełnić nazwę i adres firmy.</p>
                            </div>

                            <div>
                                <label for="company_name" class="block text-sm opacity-80">Nazwa firmy</label>
                                <input type="text" id="company_name" wire:model="company_name" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                @error('company_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- Adres firmy do faktury (opcjonalny, niezależny od dostawy) --}}
                            <div class="grid grid-cols-6 gap-3">
                                <div class="col-span-6 sm:col-span-4">
                                    <label for="company_street" class="block text-sm opacity-80">Ulica</label>
                                    <input type="text" id="company_street" wire:model="company_street" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                </div>
                                <div class="col-span-3 sm:col-span-1">
                                    <label for="company_building_number" class="block text-sm opacity-80">Nr</label>
                                    <input type="text" id="company_building_number" wire:model="company_building_number" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                </div>
                                <div class="col-span-3 sm:col-span-1">
                                    <label for="company_apartment_number" class="block text-sm opacity-80">Lok.</label>
                                    <input type="text" id="company_apartment_number" wire:model="company_apartment_number" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                </div>
                                <div class="col-span-3 sm:col-span-2">
                                    <label for="company_postal_code" class="block text-sm opacity-80">Kod</label>
                                    <input type="text" id="company_postal_code" wire:model="company_postal_code" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                </div>
                                <div class="col-span-3 sm:col-span-4">
                                    <label for="company_city" class="block text-sm opacity-80">Miejscowość</label>
                                    <input type="text" id="company_city" wire:model="company_city" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                </div>
                            </div>
                            <p class="text-xs opacity-60">Adres firmy do faktury — opcjonalny, niezależny od miejsca dostawy.</p>
                        </div>
                    @endif
                </div>

                {{-- Dostawa --}}
                <div class="st-card st-border rounded-3xl border p-6">
                    <h2 class="st-brand st-box-title">Dostawa</h2>
                    <div class="mt-4 space-y-2">
                        @foreach ($deliveryOptions as $value => $label)
                            <label class="st-border flex cursor-pointer items-center gap-3 rounded-xl border p-4 text-sm">
                                <input type="radio" wire:model.live="delivery_method" value="{{ $value }}" class="h-5 w-5 shrink-0" style="accent-color: var(--brand);">
                                @php($meta = $deliveryMeta[$value] ?? null)
                                <span class="flex-1">
                                    <span class="flex items-baseline justify-between gap-3">
                                        <span class="font-medium">{{ $label }}</span>
                                        @if ($meta)
                                            <span class="shrink-0 text-sm tabular-nums opacity-80">{{ $meta['cost'] > 0 ? \App\Support\Money::pln($meta['cost']) : 'Gratis' }}</span>
                                        @endif
                                    </span>
                                    @if ($value === 'pickup' && filled($pickupAddress))
                                        <span class="block text-xs opacity-60">{{ $pickupAddress }}</span>
                                    @elseif ($meta && $meta['free_from'] && $meta['cost'] > 0)
                                        <span class="block text-xs opacity-60">Darmowa dostawa od {{ \App\Support\Money::pln($meta['free_from']) }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('delivery_method') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                {{-- Adres dostawy — tylko przy metodzie „pod adres" (kurier).
                     Paczkomat jest wysyłką, ale adresu nie potrzebuje. --}}
                @if ($addressDelivery)
                    <div class="st-card st-border rounded-3xl border p-6">
                        <h2 class="st-brand st-box-title">Adres dostawy</h2>
                        <div class="mt-4 grid grid-cols-6 gap-3">
                            <div class="col-span-6 sm:col-span-4">
                                <label for="ship_street" class="block text-sm opacity-80">Ulica</label>
                                <input type="text" id="ship_street" wire:model="ship_street" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                @error('ship_street') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="col-span-3 sm:col-span-1">
                                <label for="ship_building_number" class="block text-sm opacity-80">Nr</label>
                                <input type="text" id="ship_building_number" wire:model="ship_building_number" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                @error('ship_building_number') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="col-span-3 sm:col-span-1">
                                <label for="ship_apartment_number" class="block text-sm opacity-80">Lok.</label>
                                <input type="text" id="ship_apartment_number" wire:model="ship_apartment_number" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                @error('ship_apartment_number') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="col-span-3 sm:col-span-2">
                                <label for="ship_postal_code" class="block text-sm opacity-80">Kod</label>
                                <input type="text" id="ship_postal_code" wire:model="ship_postal_code" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                @error('ship_postal_code') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="col-span-3 sm:col-span-4">
                                <label for="ship_city" class="block text-sm opacity-80">Miejscowość</label>
                                <input type="text" id="ship_city" wire:model="ship_city" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                @error('ship_city') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <p class="mt-3 text-xs opacity-60">Adres, pod który wyślemy zamówienie.</p>
                    </div>
                @endif

                {{-- Wybór paczkomatu. Mapa (geowidget) wypełnia pole kodu; pole
                     zostaje dostępne z palca, bo gdy mapa nie wstanie (wolne łącze,
                     brak tokenu), klient musi mieć jak dokończyć zakup. --}}
                @if ($parcelDelivery)
                    <div class="st-card st-border rounded-3xl border p-6"
                        x-data="{
                            mapOpen: false,
                            onPoint(point) {
                                const line1 = point?.address?.line1 ?? '';
                                const line2 = point?.address?.line2 ?? '';
                                const desc = [line1, line2].filter(Boolean).join(', ');
                                $wire.set('parcel_locker_code', point?.name ?? '');
                                $wire.set('parcel_locker_address', desc);
                                this.mapOpen = false;
                            },
                        }"
                        @inpost-point-selected.window="onPoint($event.detail)"
                        @keydown.escape.window="mapOpen = false">
                        <h2 class="st-brand st-box-title">Paczkomat</h2>

                        {{-- Pole kodu + przycisk mapy obok: oba ustawiają to samo. --}}
                        <div class="mt-4 flex flex-wrap items-end gap-3">
                            <div class="flex-1 sm:flex-none">
                                <label for="parcel_locker_code" class="block text-sm opacity-80">Kod paczkomatu</label>
                                <input type="text" id="parcel_locker_code" wire:model="parcel_locker_code" placeholder="np. KRA01A"
                                    class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm uppercase focus:outline-none sm:w-56">
                            </div>
                            @if ($geowidgetToken)
                                <button type="button" x-on:click="mapOpen = true"
                                    class="st-btn inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold shadow-sm transition hover:brightness-105">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4" aria-hidden="true"><path d="M9 20l-5.5 2.5v-16L9 4m0 16l6-2.5M9 20V4m6 13.5L20.5 20v-16L15 6.5m0 11V6.5M9 4l6 2.5"/></svg>
                                    Wybierz na mapie
                                </button>
                            @endif
                        </div>
                        @error('parcel_locker_code') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                        @if (filled($parcel_locker_address))
                            <p class="mt-2 text-xs opacity-60">{{ $parcel_locker_address }}</p>
                        @endif
                        <p class="mt-3 text-xs opacity-60">
                            @if ($geowidgetToken)
                                Wybierz punkt na mapie albo wpisz kod ręcznie.
                            @else
                                Kod znajdziesz w aplikacji InPost lub na <a href="https://inpost.pl/znajdz-paczkomat" target="_blank" rel="noopener" class="st-brand underline underline-offset-2">mapie paczkomatów</a>.
                            @endif
                        </p>

                        @if ($geowidgetToken)
                            {{-- Mapa w popupie: dostaje pełną szerokość i wysokość,
                                 zamiast dusić się w kolumnie formularza. --}}
                            <div x-show="mapOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
                                role="dialog" aria-modal="true" aria-label="Wybór paczkomatu">
                                <div class="absolute inset-0 bg-black/50" x-on:click="mapOpen = false"></div>
                                <div class="st-card relative z-10 flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl shadow-2xl">
                                    <div class="st-border flex items-center justify-between border-b px-5 py-4">
                                        <h3 class="font-semibold">Wybierz paczkomat</h3>
                                        <button type="button" x-on:click="mapOpen = false" aria-label="Zamknij"
                                            class="st-border flex h-8 w-8 items-center justify-center rounded-full border transition hover:brightness-95">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                        </button>
                                    </div>
                                    {{-- Widget montowany dopiero przy otwarciu (x-if): mapa w
                                         display:none nie zna wymiarów i wyszłaby połamana.
                                         wire:ignore trzyma go z dala od morphdomu Livewire. --}}
                                    <div class="min-h-0 flex-1 p-2" wire:ignore>
                                        <template x-if="mapOpen">
                                            <inpost-geowidget
                                                x-init="$el.addEventListener('inpost.geowidget.init', (e) => e.detail.api.changePointsType('PARCEL_LOCKER'))"
                                                token="{{ $geowidgetToken }}"
                                                language="pl"
                                                config="parcelCollect"
                                                onpoint="afterInpostPointSelected"
                                                class="block h-[70vh] w-full overflow-hidden rounded-2xl"></inpost-geowidget>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Płatność --}}
                <div class="st-card st-border rounded-3xl border p-6">
                    <h2 class="st-brand st-box-title">Płatność</h2>
                    <div class="mt-4 space-y-2">
                        @if ($cashOnDelivery)
                            {{-- Pobranie nie daje wyboru — płatność wynika z dostawy, więc
                                 zamiast listy z jedną pozycją pokazujemy zdanie. Sposób zapłaty
                                 MUSI być tu prawdziwy: paczkomat gotówki nie przyjmie, a klient
                                 dowiadujący się o tym przy skrytce paczki po prostu nie odbierze. --}}
                            <div class="st-border rounded-xl border p-4 text-sm">
                                <span class="font-medium">Płatność za pobraniem</span>
                                @if ($parcelDelivery)
                                    <span class="block text-xs opacity-60">Zapłacisz przy odbiorze paczki — BLIK, kartą lub w aplikacji InPost. Paczkomat nie przyjmuje gotówki.</span>
                                @else
                                    <span class="block text-xs opacity-60">Zapłacisz kurierowi przy odbiorze przesyłki — gotówką lub kartą.</span>
                                @endif
                            </div>
                        @else
                        @foreach ($paymentOptions as $value => $label)
                            <label class="st-border flex cursor-pointer items-center gap-3 rounded-xl border p-4 text-sm">
                                <input type="radio" wire:model="payment_method" value="{{ $value }}" class="h-5 w-5 shrink-0" style="accent-color: var(--brand);">
                                <span>
                                    <span class="font-medium">{{ $label }}</span>
                                    @if ($value === 'bank_transfer' && filled($bankName))
                                        <span class="block text-xs opacity-60">{{ $bankName }}</span>
                                    @elseif ($value === 'pay_on_pickup' && filled($pickupAddress))
                                        <span class="block text-xs opacity-60">Odbiór i płatność: {{ $pickupAddress }}</span>
                                    @elseif ($value === 'online')
                                        <span class="block text-xs opacity-60">BLIK, karta płatnicza, szybki przelew</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                        @endif
                    </div>
                    @error('payment_method') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                {{-- Uwagi --}}
                <div class="st-card st-border rounded-3xl border p-6">
                    <label for="note" class="st-brand st-box-title">Uwagi do zamówienia <span class="text-sm font-normal opacity-60">(opcjonalnie)</span></label>
                    <textarea id="note" wire:model="note" rows="3" class="st-border mt-3 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none"></textarea>
                    @error('note') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Podsumowanie --}}
            <aside class="lg:col-span-1">
                <div class="st-card st-border rounded-3xl border p-6">
                    <h2 class="st-brand st-box-title">Twoje zamówienie</h2>
                    <ul class="mt-4 space-y-3">
                        @foreach ($lines as $line)
                            <li class="flex justify-between gap-3 text-sm">
                                <span class="opacity-80">{{ $line['product']->sale_unit->formatQuantity($line['quantity']) }} × {{ $line['product']->name }}</span>
                                <span class="shrink-0 tabular-nums">{{ \App\Support\Money::pln($line['line_total']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @php($hasReductions = $formattedDiscount !== null || $formattedPoints !== null)
                    @if ($hasReductions)
                        {{-- Rabat i punkty: osobne wiersze nad dostawą, żeby było widać,
                             od czego zeszły (produkty) i że wysyłki nie dotyczą. --}}
                        <div class="st-border mt-4 space-y-2 border-t pt-4 text-sm">
                            <div class="flex items-baseline justify-between">
                                <span class="opacity-70">Produkty</span>
                                <span class="shrink-0 tabular-nums opacity-70">{{ $formattedItems }}</span>
                            </div>
                            @if ($formattedDiscount !== null)
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="min-w-0 opacity-70">Rabat <span class="font-semibold uppercase tracking-wide break-words">{{ $discountCode }}</span></span>
                                    <span class="shrink-0 font-semibold tabular-nums">−{{ $formattedDiscount }}</span>
                                </div>
                            @endif
                            @if ($formattedPoints !== null)
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="min-w-0 opacity-70">Punkty</span>
                                    <span class="shrink-0 font-semibold tabular-nums">−{{ $formattedPoints }}</span>
                                </div>
                            @endif
                        </div>
                    @endif
                    @if ($shippedDelivery)
                        <div class="st-border flex items-baseline justify-between text-sm {{ $hasReductions ? 'mt-2' : 'mt-4 border-t pt-4' }}">
                            <span class="opacity-70">Dostawa (kurier)</span>
                            <span class="shrink-0 tabular-nums">{{ $formattedDelivery }}</span>
                        </div>
                    @endif
                    <div class="st-border flex items-baseline justify-between {{ $shippedDelivery || $hasReductions ? 'mt-3' : 'mt-4 border-t pt-4' }}">
                        <span class="opacity-70">Razem (brutto)</span>
                        <span class="text-xl font-bold tabular-nums">{{ $formattedTotal }}</span>
                    </div>
                    <p class="mt-1 text-right text-xs opacity-60">{{ $formattedNet }} netto</p>

                    <label class="mt-5 flex items-start gap-3 text-sm">
                        <input type="checkbox" wire:model="accept_terms" class="st-border mt-0.5 h-5 w-5 shrink-0 rounded border" style="accent-color: var(--brand);">
                        <span>
                            Akceptuję
                            @if ($termsUrl)
                                <a href="{{ $termsUrl }}" target="_blank" rel="noopener" class="underline underline-offset-2 hover:opacity-80">regulamin sklepu</a>.
                            @else
                                regulamin sklepu.
                            @endif
                        </span>
                    </label>
                    @error('accept_terms') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror

                    <label class="mt-3 flex items-start gap-3 text-sm">
                        <input type="checkbox" wire:model="accept_privacy" class="st-border mt-0.5 h-5 w-5 shrink-0 rounded border" style="accent-color: var(--brand);">
                        <span>
                            Akceptuję
                            <a href="{{ $privacyUrl }}" target="_blank" rel="noopener" class="underline underline-offset-2 hover:opacity-80">politykę prywatności</a>
                            i zasady przetwarzania danych.
                        </span>
                    </label>
                    @error('accept_privacy') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror

                    <button type="submit" wire:loading.attr="disabled" wire:target="place"
                        class="st-btn mt-6 w-full rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105 disabled:opacity-60">
                        <span wire:loading.remove wire:target="place">Zamawiam i płacę</span>
                        <span wire:loading wire:target="place">Składam zamówienie…</span>
                    </button>
                </div>

                {{-- Konto klienta — osobny box pod podsumowaniem: zalogowany → info;
                     e-mail z kontem → dopiszemy do historii; wolny e-mail → opcja
                     założenia konta (mail aktywacyjny po zamówieniu). --}}
                <div class="st-card st-border mt-6 rounded-3xl border p-6">
                    <h2 class="st-brand st-box-title">Konto</h2>
                    @if ($this->authCustomer)
                        <p class="mt-3 text-sm opacity-70">
                            Zamawiasz jako <strong class="st-brand">{{ $this->authCustomer->email }}</strong> — zamówienie trafi do historii Twojego konta.
                        </p>
                    @elseif ($this->accountExists)
                        <p class="mt-3 text-sm opacity-70">
                            Ten e-mail ma już konto w tym sklepie — dopiszemy zamówienie do jego historii.
                            <a href="/logowanie" class="st-brand underline underline-offset-2">Zaloguj się</a>
                        </p>
                    @else
                        <label class="mt-3 flex items-start gap-3 text-sm">
                            <input type="checkbox" wire:model="create_account" class="st-border mt-0.5 h-5 w-5 shrink-0 rounded border" style="accent-color: var(--brand);">
                            <span>Załóż konto na ten e-mail — po zamówieniu wyślemy link do ustawienia hasła. Zobaczysz historię zamówień i szybciej złożysz kolejne.</span>
                        </label>
                    @endif
                </div>
            </aside>
        </form>
    @endif
</div>
