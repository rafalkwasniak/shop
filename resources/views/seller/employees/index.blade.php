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
                    @if ($allowed)
                        <span class="shrink-0 rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-600">
                            {{ $slots - $slotsLeft }} z {{ $slots }} miejsc
                        </span>
                    @endif
                </div>

                @unless ($allowed)
                    <div class="mt-6">
                        <x-seller.locked-feature feature="max_employees" icon="👤" title="Konta pracowników" :shop="$shop">
                            Każda osoba dostaje własne konto i własne hasło, a Ty decydujesz, które działy widzi.
                            Dostęp odbierasz jednym kliknięciem — bez zmiany swojego hasła.
                        </x-seller.locked-feature>
                    </div>
                @elseif ($employees->isEmpty())
                    <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">👤</span>
                        <p class="mt-4 font-medium text-stone-700">Pracujesz sam</p>
                        <p class="mt-1 text-sm text-stone-500">Dodaj pierwszą osobę — dostanie link do ustawienia własnego hasła.</p>
                    </div>
                @else
                    <div class="mt-6 space-y-4">
                        @foreach ($employees as $employee)
                            @php($person = $employee->user)
                            @php($revoked = $employee->revoked_at !== null)
                            <div @class([
                                'rounded-2xl border p-5',
                                'border-stone-200 bg-white/60' => ! $revoked,
                                'border-stone-200 bg-stone-50/80 opacity-75' => $revoked,
                            ])>
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium text-stone-900">{{ $person->name }} {{ $person->surname }}</p>
                                        <p class="text-sm text-stone-500">{{ $person->email }}</p>
                                    </div>

                                    {{-- Trzy stany, trzy różne komunikaty. „Zaproszony" to NIE
                                         to samo co „aktywny": konto istnieje, ale hasła nie zna
                                         jeszcze nikt, więc ta osoba do panelu nie wejdzie. --}}
                                    @if ($revoked)
                                        <span class="rounded-full bg-stone-200 px-2.5 py-0.5 text-[11px] font-medium text-stone-600">Dostęp odebrany</span>
                                    @elseif ($employee->accepted_at === null)
                                        <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-medium text-amber-700">Zaproszony — nie ustawił hasła</span>
                                    @else
                                        <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-medium text-emerald-700">Aktywny</span>
                                    @endif
                                </div>

                                @unless ($revoked)
                                    <form method="POST" action="{{ route('seller.employees.update', $employee) }}" class="mt-4">
                                        @csrf
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            @foreach ($sections as $section)
                                                <label class="flex items-start gap-2 rounded-xl border border-stone-200 bg-white/70 p-3 text-sm">
                                                    <input type="checkbox" name="permissions[]" value="{{ $section->value }}"
                                                        @checked(in_array($section->value, $employee->permissions ?? [], true))
                                                        class="mt-0.5 rounded border-stone-300 text-amber-600 focus:ring-amber-500">
                                                    <span>
                                                        <span class="font-medium text-stone-700">{{ $section->label() }}</span>
                                                        <span class="mt-0.5 block text-xs text-stone-500">{{ $section->description() }}</span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>

                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                            <button type="submit" class="rounded-2xl border border-stone-200 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-50">
                                                Zapisz działy
                                            </button>
                                        </div>
                                    </form>
                                @endunless

                                <form method="POST"
                                    action="{{ $revoked ? route('seller.employees.restore', $employee) : route('seller.employees.revoke', $employee) }}"
                                    class="mt-3">
                                    @csrf
                                    @if ($revoked)
                                        <button type="submit" class="text-sm font-medium text-stone-600 underline decoration-stone-300 underline-offset-2 transition hover:text-stone-800">
                                            Przywróć dostęp
                                        </button>
                                    @else
                                        <button type="submit"
                                            onclick="return confirm('Odebrać dostęp? Ta osoba przestanie wchodzić do panelu przy najbliższym kliknięciu.')"
                                            class="text-sm font-medium text-rose-600 underline decoration-rose-300 underline-offset-2 transition hover:text-rose-700">
                                            Odbierz dostęp
                                        </button>
                                    @endif
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endunless
            </div>
        </div>

        {{-- Boczna kolumna. Panel „Jak to działa" stoi POZA warunkiem uprawnienia —
             ten sam wzorzec co na Kodach rabatowych. Sprzedawca na darmowym
             pakiecie ma się dowiedzieć, co ta funkcja robi i czego nie oddaje,
             ZANIM zdecyduje, czy warto za nią dopłacić. Sama zachęta bez
             wyjaśnienia mówi tylko „nie masz". --}}
        <aside class="space-y-6 lg:col-span-4">
            @if ($allowed)
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Dodaj pracownika</h2>

                    @if ($slotsLeft < 1)
                        <p class="mt-3 rounded-2xl bg-stone-100 p-4 text-sm text-stone-600">
                            Wykorzystałeś wszystkie {{ $slots }} miejsc. Odbierz komuś dostęp albo napisz do nas,
                            jeśli potrzebujesz większego zespołu.
                        </p>
                    @else
                        <form method="POST" action="{{ route('seller.employees.store') }}" class="mt-4 space-y-3">
                            @csrf

                            <div>
                                <label for="name" class="block text-sm font-medium text-stone-700">Imię</label>
                                <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="60"
                                    class="mt-1 w-full rounded-2xl border-stone-200 bg-white/80 text-sm focus:border-amber-400 focus:ring-amber-400">
                                @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="surname" class="block text-sm font-medium text-stone-700">Nazwisko</label>
                                <input id="surname" name="surname" type="text" value="{{ old('surname') }}" required maxlength="60"
                                    class="mt-1 w-full rounded-2xl border-stone-200 bg-white/80 text-sm focus:border-amber-400 focus:ring-amber-400">
                                @error('surname') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-stone-700">Adres e-mail</label>
                                <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255"
                                    class="mt-1 w-full rounded-2xl border-stone-200 bg-white/80 text-sm focus:border-amber-400 focus:ring-amber-400">
                                <p class="mt-1 text-xs text-stone-500">Na ten adres pójdzie link do ustawienia hasła. Hasła nie ustalasz Ty.</p>
                                @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <span class="block text-sm font-medium text-stone-700">Działy</span>
                                <div class="mt-2 space-y-2">
                                    @foreach ($sections as $section)
                                        <label class="flex items-start gap-2 rounded-xl border border-stone-200 bg-white/70 p-3 text-sm">
                                            <input type="checkbox" name="permissions[]" value="{{ $section->value }}"
                                                @checked(in_array($section->value, old('permissions', []), true))
                                                class="mt-0.5 rounded border-stone-300 text-amber-600 focus:ring-amber-500">
                                            <span>
                                                <span class="font-medium text-stone-700">{{ $section->label() }}</span>
                                                <span class="mt-0.5 block text-xs text-stone-500">{{ $section->description() }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('permissions') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <button type="submit"
                                class="w-full rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                                Dodaj pracownika
                            </button>
                        </form>
                    @endif
                </div>
            @endif

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to działa</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">✉️</span>
                        <span>Podajesz imię i adres e-mail. Pracownik dostaje link i <span class="font-medium text-stone-700">sam ustawia swoje hasło</span> — Ty go nie znasz i nie musisz.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🗂️</span>
                        <span>Zaznaczasz <span class="font-medium text-stone-700">działy</span>, nie pojedyncze przyciski. Czego nie zaznaczysz, tego nie ma nawet w jego menu.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🔒</span>
                        <span>Pakiet, płatności, dane firmy, ustawienia sklepu, klucze do faktur i ta lista <span class="font-medium text-stone-700">zostają tylko u Ciebie</span>. Nie da się ich nikomu oddać.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">↩️</span>
                        <span>Dostęp odbierasz jednym kliknięciem — działa przy najbliższym kliknięciu pracownika. <span class="font-medium text-stone-700">Swojego hasła nie zmieniasz.</span></span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">📜</span>
                        <span>Odebranie dostępu <span class="font-medium text-stone-700">nie kasuje historii</span> — zostaje ślad, kto i kiedy pracował w sklepie.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
