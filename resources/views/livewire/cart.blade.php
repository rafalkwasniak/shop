<div>
    @if (! empty($notices))
        {{-- Korekta dostępności: koszyk został po cichu uzgodniony ze stanem —
             tu tłumaczymy klientowi, co i dlaczego się zmieniło. --}}
        <div class="st-card st-border mb-6 flex gap-3 rounded-2xl border px-5 py-4" role="status" aria-live="polite">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-0.5 h-5 w-5 shrink-0 opacity-70" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/>
            </svg>
            <div class="min-w-0 space-y-1 text-sm">
                <p class="font-semibold">Zaktualizowaliśmy Twój koszyk</p>
                @foreach ($notices as $notice)
                    <p class="opacity-80">{{ $notice }}</p>
                @endforeach
            </div>
        </div>
    @endif

    @if ($lines->isEmpty())
        {{-- Pusty koszyk --}}
        <div class="st-card st-border mx-auto max-w-lg rounded-3xl border px-8 py-16 text-center">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" class="mx-auto h-12 w-12 opacity-40" aria-hidden="true">
                <circle cx="9" cy="20" r="1.4" fill="currentColor" stroke="none"/>
                <circle cx="18" cy="20" r="1.4" fill="currentColor" stroke="none"/>
                <path d="M2.5 3h2l2.2 11.2a1.5 1.5 0 0 0 1.5 1.2h8.3a1.5 1.5 0 0 0 1.5-1.2L21 7H6"/>
            </svg>
            <p class="mt-5 text-lg font-semibold">Twój koszyk jest pusty</p>
            <p class="mt-1 opacity-70">Dodaj produkty, a pojawią się tutaj.</p>
            <a href="/" wire:navigate
                class="st-btn mt-6 inline-block rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">
                Wróć do sklepu
            </a>
        </div>
    @else
        <div class="grid gap-8 lg:grid-cols-3">
            {{-- Pozycje --}}
            <div class="lg:col-span-2 space-y-4">
                @foreach ($lines as $line)
                    @php($product = $line['product'])
                    @php($image = $product->mainImage())
                    <div wire:key="line-{{ $product->id }}"
                        class="st-card st-border flex items-center gap-4 rounded-2xl border p-4">
                        <div class="st-border h-20 w-20 shrink-0 overflow-hidden rounded-xl border" style="aspect-ratio: 1 / 1;">
                            @if ($image)
                                <img src="{{ $image->url() }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                            @else
                                <div class="st-btn flex h-full w-full items-center justify-center">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-7 w-7 opacity-70" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5" fill="currentColor" stroke="none"/><path d="M4 17l4.5-4.5 3 3L15 12l5 5"/></svg>
                                </div>
                            @endif
                        </div>

                        @php($atMax = $product->track_stock && $product->stock !== null && $line['quantity'] >= $product->stock)
                        @php($atMin = $line['quantity'] <= $product->sale_unit->minQuantity())
                        <div class="min-w-0 flex-1">
                            <a href="{{ $product->storefrontPath() }}" wire:navigate class="block st-brand font-serif text-xl font-normal break-words hover:underline">{{ $product->name }}</a>
                            <p class="mt-0.5 text-sm opacity-70">{{ \App\Support\Money::pln($line['unit_price']) }} / {{ $product->sale_unit->abbreviation() }}</p>

                            {{-- Ilość: krok +/− wg jednostki (1 szt. / 0,5 kg), pole wpisywane z palca.
                                 Przy minimum lewy przycisk to KOSZ (usuwa), wyżej „−". --}}
                            <div class="mt-3 flex items-center gap-3">
                                <div class="inline-flex items-center gap-1">
                                    @if ($atMin)
                                        <button type="button" wire:click="remove({{ $product->id }})"
                                            class="st-border flex h-8 w-8 items-center justify-center rounded-full border transition hover:border-rose-400 hover:text-rose-600"
                                            aria-label="Usuń z koszyka" title="Usuń z koszyka">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4" aria-hidden="true"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m4 5v6m6-6v6"/></svg>
                                        </button>
                                    @else
                                        <button type="button" wire:click="decrement({{ $product->id }})"
                                            class="st-border flex h-8 w-8 items-center justify-center rounded-full border text-lg leading-none transition hover:brightness-95"
                                            aria-label="Zmniejsz ilość">−</button>
                                    @endif
                                    <input type="text" inputmode="decimal"
                                        wire:key="qty-{{ $product->id }}-{{ $line['quantity'] }}"
                                        value="{{ $product->sale_unit->inputAmount($line['quantity']) }}"
                                        x-on:change="$wire.updateQuantity({{ $product->id }}, $event.target.value)"
                                        aria-label="Ilość"
                                        class="st-border h-8 w-14 rounded-full border bg-transparent text-center text-sm font-semibold tabular-nums focus:outline-none focus:ring-2 focus:ring-current/20">
                                    <button type="button" wire:click="increment({{ $product->id }})" @disabled($atMax)
                                        class="st-border flex h-8 w-8 items-center justify-center rounded-full border text-lg leading-none transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                                        aria-label="Zwiększ ilość">+</button>
                                    <span class="ml-1 text-sm opacity-70">{{ $product->sale_unit->abbreviation() }}</span>
                                </div>
                                @if ($atMax)
                                    <span class="text-xs opacity-60">maks. {{ $product->sale_unit->formatQuantity($product->stock) }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center self-stretch">
                            <span class="font-bold tabular-nums">{{ \App\Support\Money::pln($line['line_total']) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Podsumowanie --}}
            <aside class="lg:col-span-1 space-y-4">
                {{-- Kod rabatowy: osobna karta NAD podsumowaniem. Pod przyciskiem
                     „Przejdź do kasy" nikt by go nie szukał, a w środku podsumowania
                     rozbijał rachunek. Zastosowany pokazujemy jako plakietkę; gdy
                     przestał działać (koszyk zszedł poniżej progu, zniknął produkt) —
                     zostaje z powodem, bo klient zwykle może go odzyskać.
                     Cała karta znika w sklepie bez uprawnienia `discount_codes`. --}}
                @if ($discountsEnabled)
                <div class="st-card st-border rounded-3xl border p-6">
                    <h2 class="st-brand st-box-title">Masz kod rabatowy?</h2>

                    @if ($discountCode === null)
                        <div class="mt-4 flex gap-2">
                            <label for="discount-code" class="sr-only">Kod rabatowy</label>
                            <input id="discount-code" type="text" wire:model="discountInput"
                                wire:keydown.enter="applyDiscount" placeholder="Wpisz kod"
                                class="st-border h-10 min-w-0 flex-1 rounded-full border bg-transparent px-4 text-sm uppercase focus:outline-none focus:ring-2 focus:ring-current/20">
                            <button type="button" wire:click="applyDiscount"
                                class="st-border h-10 shrink-0 rounded-full border px-4 text-sm font-semibold transition hover:brightness-95">
                                Zastosuj
                            </button>
                        </div>
                    @else
                        <div class="mt-4 flex items-baseline justify-between gap-3">
                            <span class="min-w-0 font-semibold uppercase tracking-wide break-words">{{ $discountCode }}</span>
                            <button type="button" wire:click="removeDiscount"
                                class="shrink-0 text-sm underline opacity-70 transition hover:opacity-100">Usuń</button>
                        </div>
                    @endif

                    {{-- Jeden kafelek na oba wyniki: kod działa albo nie działa.
                         Ten sam spokojny ton co przy korekcie koszyka na górze strony —
                         bez czerwieni, bo odmowa kodu to informacja o jego warunkach,
                         nie pomyłka klienta. Różni je tylko ikona: „ptaszek" albo „i". --}}
                    @if ($discountIssue !== null || $discountNote !== null)
                        <div class="st-border mt-3 flex gap-3 rounded-2xl border px-4 py-3" role="status" aria-live="polite">
                            @if ($discountNote !== null)
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="mt-0.5 h-4 w-4 shrink-0 opacity-70" aria-hidden="true">
                                    <circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>
                                </svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-0.5 h-4 w-4 shrink-0 opacity-60" aria-hidden="true">
                                    <circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/>
                                </svg>
                            @endif
                            <p class="min-w-0 text-sm opacity-80">{{ $discountIssue ?? $discountNote }}</p>
                        </div>
                    @endif
                </div>
                @endif

                {{-- Punkty za zakupy: osobna karta, jak kod rabatowy. Wartość w zł klient
                     widzi dopiero w PODSUMOWANIU, po kliknięciu „Wykorzystaj" — bo musi znać
                     cenę końcową (decyzja Rafała 07.10: poza tym same punkty). Ile
                     punktów, liczy LoyaltyLedger::redeemable() — saldo, minimum i limit
                     sklepu, a po punktach zostaje co najmniej loyalty.min_payable. --}}
                @if ($loyalty !== null)
                    <div class="st-card st-border rounded-3xl border p-6">
                        <h2 class="st-brand st-box-title">Twoje punkty</h2>
                        <p class="mt-3 text-sm">Masz <strong>{{ $loyalty->balance }} pkt</strong>.</p>

                        @if ($loyalty->canRedeem())
                            @if ($pointsApplied && ! $editingPoints)
                                <div class="mt-4 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                    <span class="min-w-0 text-sm">Wykorzystujesz <strong>{{ $loyalty->points }} pkt</strong></span>
                                    <span class="flex shrink-0 gap-3">
                                        <button type="button" wire:click="changePoints"
                                            class="text-sm underline opacity-70 transition hover:opacity-100">Zmień</button>
                                        <button type="button" wire:click="stopUsingPoints"
                                            class="text-sm underline opacity-70 transition hover:opacity-100">Nie wykorzystuj</button>
                                    </span>
                                </div>
                                <p class="mt-2 text-sm">Pozostanie: <strong>{{ $loyalty->balance - $loyalty->points }} pkt</strong>.</p>
                                @if ($loyalty->capped())
                                    <p class="mt-2 text-xs opacity-60">W tym zamówieniu możesz wykorzystać najwyżej {{ $loyalty->maximum }} pkt.</p>
                                @endif
                            @else
                                {{-- „Wszystkie" jednym kliknięciem albo własna liczba. Bez kwoty
                                     w zł (decyzja Rafała 07.10) — ta pokazuje się w podsumowaniu. --}}
                                <button type="button" wire:click="usePoints"
                                    class="st-border mt-4 w-full rounded-full border px-4 py-2.5 text-sm font-semibold transition hover:brightness-95">
                                    {{ $loyalty->maximum === $loyalty->balance ? 'Wykorzystaj wszystkie '.$loyalty->maximum.' pkt' : 'Wykorzystaj '.$loyalty->maximum.' pkt' }}
                                </button>
                                <p class="mt-4 text-sm opacity-70">albo wpisz, ile chcesz wykorzystać:</p>
                                <div class="mt-2 flex gap-2">
                                    <label for="points-amount" class="sr-only">Liczba punktów</label>
                                    <input id="points-amount" type="text" inputmode="numeric" wire:model="pointsInput"
                                        wire:keydown.enter="applyPoints" placeholder="np. 500"
                                        class="st-border h-10 min-w-0 flex-1 rounded-full border bg-transparent px-4 text-sm focus:outline-none focus:ring-2 focus:ring-current/20">
                                    <button type="button" wire:click="applyPoints"
                                        class="st-border h-10 shrink-0 rounded-full border px-4 text-sm font-semibold transition hover:brightness-95">
                                        Zastosuj
                                    </button>
                                </div>
                                @php($pointsMessage = $pointsError ?? ($loyalty->reason === 'below_minimum' ? 'Najmniej możesz wykorzystać '.$loyalty->minimum.' pkt.' : null))
                                @if ($pointsMessage)
                                    <p class="mt-2 text-sm opacity-80" role="status" aria-live="polite">{{ $pointsMessage }}</p>
                                @endif
                                @if ($loyalty->maximum < $loyalty->balance)
                                    <p class="mt-3 text-xs opacity-60">W tym zamówieniu możesz wykorzystać najwyżej {{ $loyalty->maximum }} z {{ $loyalty->balance }} pkt — reszta zostanie na kolejne zakupy.</p>
                                @endif
                            @endif
                        @else
                            <p class="mt-3 text-sm opacity-70">
                                @if ($loyalty->reason === 'minimum')
                                    Z punktów skorzystasz, gdy uzbierasz co najmniej {{ $loyalty->minimum }} pkt.
                                @else
                                    Dodaj więcej produktów, żeby zapłacić częścią punktów.
                                @endif
                            </p>
                        @endif
                    </div>
                @elseif ($loyaltyInvite)
                    <div class="st-card st-border rounded-3xl border p-6">
                        <h2 class="st-brand st-box-title">Masz punkty?</h2>
                        <p class="mt-3 text-sm opacity-80"><a href="/logowanie" class="underline underline-offset-2">Zaloguj się</a>, żeby z nich skorzystać.</p>
                    </div>
                @endif

                <div class="st-card st-border rounded-3xl border p-6">
                    <h2 class="st-brand st-box-title">Podsumowanie</h2>

                    <div class="mt-4 space-y-2 border-t st-border pt-4">
                        @php($codeDiscountShown = $discount !== null && $discount->accepted() && $discount->itemsDiscount > 0)
                        @if ($codeDiscountShown || $pointsDiscount > 0)
                            <div class="flex items-baseline justify-between text-sm">
                                <span class="opacity-70">Produkty</span>
                                <span class="tabular-nums opacity-70">{{ \App\Support\Money::pln($itemsTotal) }}</span>
                            </div>
                        @endif
                        @if ($codeDiscountShown)
                            <div class="flex items-baseline justify-between text-sm">
                                <span class="opacity-70">Rabat</span>
                                <span class="tabular-nums font-semibold">−{{ \App\Support\Money::pln($discount->itemsDiscount) }}</span>
                            </div>
                        @endif
                        @if ($pointsDiscount > 0)
                            <div class="flex items-baseline justify-between text-sm">
                                <span class="opacity-70">Punkty</span>
                                <span class="tabular-nums font-semibold">−{{ \App\Support\Money::pln($pointsDiscount) }}</span>
                            </div>
                        @endif
                        <div class="flex items-baseline justify-between">
                            <span class="opacity-70">Razem (brutto)</span>
                            <span class="text-xl font-bold tabular-nums">{{ \App\Support\Money::pln($total) }}</span>
                        </div>
                    </div>
                    <a href="/kasa" wire:navigate
                        class="st-btn mt-6 block w-full rounded-full px-8 py-3 text-center text-sm font-semibold shadow-sm transition hover:brightness-105">
                        Przejdź do kasy
                    </a>
                </div>
            </aside>
        </div>
    @endif
</div>
