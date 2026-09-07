<x-layouts.panel title="Pracownicy">
    <x-slot:heading>Pracownicy</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: kto już pracuje --}}
        <div class="lg:col-span-8">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-stone-900">Twój zespół</h2>
                        <p class="mt-1 text-sm text-stone-500">Wpuść zaufaną osobę do wybranych działów panelu — bez oddawania jej hasła do swojego konta.</p>
                    </div>
                    @if ($allowed && $slotsLeft > 0)
                        <a href="{{ route('seller.employees.create') }}"
                            class="shrink-0 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                            Dodaj pracownika
                        </a>
                    @endif
                </div>

                @if ($allowed)
                    <p class="mt-3 text-xs text-stone-400">
                        Zajęte miejsca: {{ $slots - $slotsLeft }} z {{ $slots }}.
                        @if ($slotsLeft < 1)
                            Odbierz komuś dostęp albo napisz do nas, jeśli potrzebujesz większego zespołu.
                        @endif
                    </p>
                @endif

                @unless ($allowed)
                    <div class="mt-6">
                        <x-seller.locked-feature feature="max_employees" icon="👤" title="Konta pracowników" :shop="$shop">
                            Każda osoba dostaje własne konto i własne hasło, a Ty decydujesz, które działy widzi.
                            Dostęp odbierasz jednym kliknięciem — bez zmiany swojego hasła.
                        </x-seller.locked-feature>
                    </div>
                @endunless

                {{-- Lista pokazuje się TAKŻE przy zablokowanej funkcji. Po zejściu
                     z pakietu dostępy są wygaszone, ale ludzie zostają — właściciel
                     musi widzieć, kogo to dotyczy. Ekran z samą zachętą i bez ani
                     jednego nazwiska wyglądałby, jakby konta zniknęły. --}}
                @if ($allowed && $employees->isEmpty())
                    <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">👤</span>
                        <p class="mt-4 font-medium text-stone-700">Pracujesz sam</p>
                        <p class="mt-1 text-sm text-stone-500">Dodaj pierwszą osobę — dostanie link do ustawienia własnego hasła.</p>
                    </div>
                @elseif ($employees->isNotEmpty())
                    <ul class="mt-6 space-y-3">
                        @foreach ($employees as $employee)
                            @php($person = $employee->user)
                            @php($revoked = $employee->revoked_at !== null)
                            <li @class([
                                'rounded-2xl border p-4',
                                'border-stone-200 bg-white/70' => ! $revoked,
                                'border-stone-200 bg-stone-50/80 opacity-75' => $revoked,
                            ])>
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        @if ($revoked)
                                            <p class="break-words font-semibold text-stone-900">{{ $person->name }} {{ $person->surname }}</p>
                                        @else
                                            <a href="{{ route('seller.employees.edit', $employee) }}"
                                                class="break-words font-semibold text-stone-900 transition hover:text-amber-700">
                                                {{ $person->name }} {{ $person->surname }}
                                            </a>
                                        @endif
                                        <p class="text-sm text-stone-500">{{ $person->email }}</p>
                                    </div>

                                    {{-- Trzy stany, trzy różne komunikaty. „Zaproszony" to NIE
                                         to samo co „aktywny": konto istnieje, ale hasła nie zna
                                         jeszcze nikt, więc ta osoba do panelu nie wejdzie. --}}
                                    @if ($revoked)
                                        <span class="rounded-full bg-stone-200 px-2.5 py-0.5 text-[11px] font-medium text-stone-600">Dostęp odebrany</span>
                                    @elseif ($employee->accepted_at === null)
                                        <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-medium text-amber-700">Zaproszony — nie ustawił hasła</span>
                                    @elseif (! $allowed)
                                        {{-- Pakiet przestał dawać konta pracownicze. „Aktywny" byłoby
                                             tu nieprawdą, którą właściciel odkryłby dopiero telefonem
                                             od pracownika. Wiersz zostaje — po odnowieniu wraca sam. --}}
                                        <span class="rounded-full bg-stone-200 px-2.5 py-0.5 text-[11px] font-medium text-stone-600">Wygaszony — pakiet</span>
                                    @else
                                        <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-medium text-emerald-700">Aktywny</span>
                                    @endif
                                </div>

                                @if ($employee->sections() !== [])
                                    <div class="mt-3 flex flex-wrap gap-1.5">
                                        @foreach ($employee->sections() as $section)
                                            <span class="rounded-full bg-stone-100 px-2.5 py-0.5 text-xs font-medium text-stone-600">{{ $section->label() }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="mt-3 flex flex-wrap items-center gap-4 text-sm">
                                    @unless ($revoked)
                                        <a href="{{ route('seller.employees.edit', $employee) }}"
                                            class="font-medium text-stone-600 underline decoration-stone-300 underline-offset-2 transition hover:text-stone-800">
                                            Zmień działy
                                        </a>
                                    @endunless

                                    @if (! $revoked && $employee->accepted_at === null)
                                        {{-- Link żyje 7 dni, a maile bywają przeoczone. Przycisk
                                             pokazujemy WYŁĄCZNIE przy zaproszeniu czekającym:
                                             osobie, która już ustawiła hasło, nowy link byłby
                                             drogą do przejęcia konta z jej skrzynki. --}}
                                        <form method="POST" action="{{ route('seller.employees.resend', $employee) }}">
                                            @csrf
                                            <button type="submit" class="font-medium text-stone-600 underline decoration-stone-300 underline-offset-2 transition hover:text-stone-800">
                                                Wyślij zaproszenie ponownie
                                            </button>
                                        </form>
                                    @endif

                                    <form method="POST"
                                        action="{{ $revoked ? route('seller.employees.restore', $employee) : route('seller.employees.revoke', $employee) }}">
                                        @csrf
                                        @if ($revoked)
                                            <button type="submit" class="font-medium text-stone-600 underline decoration-stone-300 underline-offset-2 transition hover:text-stone-800">
                                                Przywróć dostęp
                                            </button>
                                        @else
                                            <button type="submit"
                                                onclick="return confirm('Odebrać dostęp? Ta osoba przestanie wchodzić do panelu przy najbliższym kliknięciu.')"
                                                class="font-medium text-rose-600 underline decoration-rose-300 underline-offset-2 transition hover:text-rose-700">
                                                Odbierz dostęp
                                            </button>
                                        @endif
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- Objaśnienie stoi POZA bramą pakietu — ten sam wzorzec co na Kodach
             rabatowych. Sprzedawca na darmowym pakiecie ma się dowiedzieć, co ta
             funkcja robi i czego NIE oddaje, zanim zdecyduje o dopłacie. --}}
        <aside class="space-y-6 lg:col-span-4">
            <x-seller.employees-help />
        </aside>
    </div>
</x-layouts.panel>
