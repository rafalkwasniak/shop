<div>
    {{-- Ten sam podział 8/4 co w Pakietach i u Sprzedawców: po lewej to, co się
         wypełnia, po prawej to, co z tego wyniknie i jak to czytać. --}}
    <form wire:submit="save" class="grid gap-6 lg:grid-cols-12">
        <div class="space-y-6 lg:col-span-8">
            {{-- Sklep i pakiet są u góry, bo to one podpowiadają wszystko niżej:
                 wybór sklepu ustawia jego obecny pakiet, a para sklep+pakiet
                 wypełnia kwotę i termin wyceną z ekranu sprzedawcy. --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h3 class="font-semibold text-stone-900">Czego dotyczy wpłata</h3>
                <p class="mt-1 text-sm text-stone-500">
                    Wybór sklepu i pakietu podpowie kwotę oraz termin — dokładnie tak, jak wyceniłby je ekran „Mój pakiet".
                    Obie podpowiedzi możesz nadpisać.
                </p>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="shop_id" class="block text-sm font-medium text-stone-700">Sklep</label>
                        <select id="shop_id" wire:model.live="shop_id"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            <option value="">— wybierz sklep —</option>
                            @foreach ($shops as $shop)
                                <option value="{{ $shop->id }}">{{ $shop->name }} ({{ $shop->owner?->email ?? $shop->slug }})</option>
                            @endforeach
                        </select>
                        @error('shop_id') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="target_package" class="block text-sm font-medium text-stone-700">Pakiet</label>
                        <select id="target_package" wire:model.live="target_package"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            <option value="">— wybierz pakiet —</option>
                            @foreach ($packages as $slug => $package)
                                <option value="{{ $slug }}">{{ $package['name'] }}</option>
                            @endforeach
                        </select>
                        @error('target_package') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h3 class="font-semibold text-stone-900">Pieniądze</h3>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="amount" class="block text-sm font-medium text-stone-700">Kwota (brutto, zł)</label>
                        {{-- `.live`, bo te wartości powtarza podsumowanie obok. Pole
                             odroczone pokazywałoby tam liczbę sprzed edycji — czyli
                             mówiłoby nieprawdę o tym, co się zapisze. --}}
                        <input type="number" id="amount" wire:model.live.debounce.500ms="amount" min="0" step="0.01"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        <p class="mt-1.5 text-xs text-stone-400">Tyle, ile realnie wpłynęło — także gdy odbiega od cennika.</p>
                        @error('amount') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="method" class="block text-sm font-medium text-stone-700">Sposób wpłaty</label>
                        <select id="method" wire:model="method"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            @foreach ($methods as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('method') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="paid_at" class="block text-sm font-medium text-stone-700">Data wpłaty</label>
                        <input type="date" id="paid_at" wire:model="paid_at"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        <p class="mt-1.5 text-xs text-stone-400">Data kasowa — po niej wpłata trafia do właściwego roku.</p>
                        @error('paid_at') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="new_ends_at" class="block text-sm font-medium text-stone-700">Opłacony do</label>
                        <input type="date" id="new_ends_at" wire:model.live.debounce.500ms="new_ends_at"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        <p class="mt-1.5 text-xs text-stone-400">Ten termin zostanie ustawiony sklepowi. Musi być w przyszłości.</p>
                        @error('new_ends_at') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h3 class="font-semibold text-stone-900">Dokument i powiadomienie</h3>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="invoice_number" class="block text-sm font-medium text-stone-700">Numer faktury (opcjonalnie)</label>
                        <input type="text" id="invoice_number" wire:model="invoice_number" maxlength="64"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        <p class="mt-1.5 text-xs text-stone-400">Gdy dokument wystawiłeś poza systemem — wpłata przestanie się wtedy upominać o fakturę.</p>
                        @error('invoice_number') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="note" class="block text-sm font-medium text-stone-700">Notatka (opcjonalnie)</label>
                        <input type="text" id="note" wire:model="note" maxlength="255"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        <p class="mt-1.5 text-xs text-stone-400">Np. numer przelewu albo ustalenia z rozmowy.</p>
                        @error('note') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-stone-200 bg-white/60 p-4">
                        <input type="checkbox" wire:model="notify"
                            class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-emerald-600 focus:ring-4 focus:ring-amber-500/20">
                        <span>
                            <span class="block text-sm font-medium text-stone-800">Wyślij sprzedawcy potwierdzenie</span>
                            <span class="mt-0.5 block text-xs text-stone-500">Ta sama wiadomość co po wpłacie online: pakiet aktywny, termin, lista funkcji.</span>
                        </span>
                    </label>

                    {{-- Domyślnie WYŁĄCZONE i opisane wprost: Fakturownia nie ma
                         sandboxa, więc to kliknięcie tworzy realny dokument. --}}
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50/70 p-4">
                        <input type="checkbox" wire:model="issue_invoice"
                            class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20">
                        <span>
                            <span class="block text-sm font-medium text-stone-800">Wystaw fakturę w Fakturowni</span>
                            <span class="mt-0.5 block text-xs text-amber-800">Powstanie REALNY dokument — nie da się go cofnąć z panelu. Zostaw wyłączone, jeśli fakturę wystawiłeś już sam.</span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('administrator.packages.payments') }}"
                    class="rounded-2xl border border-stone-200 bg-white/70 px-5 py-3 text-sm font-medium text-stone-600 transition hover:bg-white">
                    Anuluj
                </a>
                <button type="submit"
                    class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                    Zapisz wpłatę i ustaw pakiet
                </button>
            </div>
        </div>

        <aside class="space-y-6 lg:col-span-4">
            {{-- Formularz mówi, CO wpisuję. Ta karta mówi, CO Z TEGO WYNIKNIE —
                 i to jest inna informacja: zejście niżej wyłącza funkcje droższego
                 pakietu, a przedłużenie zostawia cenę indywidualną. Obie rzeczy
                 widać dopiero w zestawieniu ze stanem obecnym. --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Co się zmieni</h2>

                @if ($summary === null)
                    <p class="mt-3 text-sm text-stone-500">
                        Wybierz sklep i pakiet — pokażę tu stan sprzed zapisu obok tego, co zapis ustawi.
                    </p>
                @else
                    <div class="mt-4 border-b border-stone-100 pb-4">
                        <p class="truncate font-medium text-stone-900">{{ $summary['shop']->name }}</p>
                        <p class="truncate text-xs text-stone-400">{{ $summary['shop']->owner?->email ?? $summary['shop']->slug }}</p>
                    </div>

                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-stone-400">Pakiet</dt>
                            <dd class="mt-0.5 text-stone-900">
                                @if ($summary['isRenewal'])
                                    {{ $summary['toPackage'] }} <span class="text-xs text-stone-400">— przedłużenie</span>
                                @else
                                    <span class="text-stone-500">{{ $summary['fromPackage'] }}</span>
                                    <span class="text-stone-400">→</span>
                                    <span class="font-medium">{{ $summary['toPackage'] }}</span>
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs text-stone-400">Opłacony do</dt>
                            <dd class="mt-0.5 tabular-nums text-stone-900">
                                <span class="text-stone-500">{{ $summary['fromEndsAt']?->format('d.m.Y') ?? 'bez terminu' }}</span>
                                <span class="text-stone-400">→</span>
                                <span class="font-medium">{{ $summary['toEndsAt']?->format('d.m.Y') ?? '—' }}</span>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs text-stone-400">Cena roczna po zapisie</dt>
                            <dd class="mt-0.5 tabular-nums text-stone-900">{{ \App\Support\Money::pln($summary['priceAfter']) }}</dd>
                        </div>
                    </dl>

                    @if ($summary['isDownsize'])
                        <p class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-xs text-rose-700">
                            <span class="font-medium">Zejście na tańszy pakiet.</span>
                            Funkcje droższego zostaną wyłączone, a produkty i banery ponad nowy limit — schowane
                            (wrócą, gdy sklep znów pójdzie wyżej).
                        </p>
                    @endif

                    @if ($summary['comped'])
                        <p class="mt-4 rounded-2xl bg-amber-50 px-4 py-3 text-xs text-amber-800">
                            <span class="font-medium">Sklep ma dostęp gratisowy.</span>
                            Termin zostanie zapisany, ale flaga „gratis" zostaje — pakiet i tak nie wygaśnie.
                        </p>
                    @endif
                @endif
            </div>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to działa</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">💰</span>
                        <span>Wpłata trafia do <span class="text-stone-700">tego samego rejestru co bramka</span> i od razu liczy się do przychodu platformy.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🗓️</span>
                        <span>Zapis <span class="text-stone-700">ustawia sklepowi pakiet i termin</span> — tak samo jak zrobiłaby to wpłata online. Nie trzeba nic klikać w konsoli sklepów.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🧾</span>
                        <span>Faktura <span class="text-stone-700">nie powstanie sama</span>. Zaznacz ją tylko wtedy, gdy chcesz realny dokument w Fakturowni — albo wpisz numer tej, którą już wystawiłeś.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </form>
</div>
