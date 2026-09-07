<x-layouts.panel :title="$employee ? 'Pracownik' : 'Nowy pracownik'">
    <x-slot:heading>{{ $employee ? 'Działy pracownika' : 'Nowy pracownik' }}</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="space-y-6 lg:col-span-8">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <form method="POST"
                    action="{{ $employee ? route('seller.employees.update', $employee) : route('seller.employees.store') }}"
                    class="space-y-5" novalidate data-validate>
                    @csrf

                    @if ($employee)
                        {{-- Tożsamości nie edytujemy: pracownik dostał na ten adres
                             zaproszenie i pod tym nazwiskiem widnieje na liście.
                             Zmiana tutaj rozjechałaby jedno z drugim. Swoje dane
                             zmienia sam w „Profilu", już zalogowany. --}}
                        <div class="rounded-2xl bg-stone-50 p-4">
                            <p class="font-medium text-stone-900">{{ $employee->user->name }} {{ $employee->user->surname }}</p>
                            <p class="text-sm text-stone-500">{{ $employee->user->email }}</p>
                            @if ($employee->accepted_at === null)
                                <p class="mt-2 text-xs text-amber-700">Zaproszenie wysłane {{ $employee->invited_at?->format('d.m.Y') }} — ta osoba nie ustawiła jeszcze hasła.</p>
                            @endif
                        </div>
                    @else
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="name" class="block text-sm font-medium text-stone-700">Imię</label>
                                <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="60"
                                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @error('name') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="surname" class="block text-sm font-medium text-stone-700">Nazwisko</label>
                                <input id="surname" name="surname" type="text" value="{{ old('surname') }}" required maxlength="60"
                                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @error('surname') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-stone-700">Adres e-mail</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255"
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            <p class="mt-1.5 text-xs text-stone-400">Na ten adres pójdzie link do ustawienia hasła. Hasła nie ustalasz Ty.</p>
                            @error('email') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <span class="block text-sm font-medium text-stone-700">Działy</span>
                        <p class="mt-1 text-xs text-stone-400">Czego nie zaznaczysz, tego nie ma nawet w jego menu.</p>

                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            @foreach ($sections as $section)
                                <label class="flex items-start gap-2 rounded-xl border border-stone-200 bg-white/70 p-3 text-sm">
                                    <input type="checkbox" name="permissions[]" value="{{ $section->value }}"
                                        @checked(in_array($section->value, old('permissions', $employee?->permissions ?? []), true))
                                        class="mt-0.5 rounded border-stone-300 text-amber-600 focus:ring-amber-500">
                                    <span>
                                        <span class="font-medium text-stone-700">{{ $section->label() }}</span>
                                        <span class="mt-0.5 block text-xs text-stone-500">{{ $section->description() }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('permissions') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-wrap items-center gap-3 border-t border-stone-100 pt-5">
                        <button type="submit"
                            class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                            {{ $employee ? 'Zapisz działy' : 'Wyślij zaproszenie' }}
                        </button>
                        <a href="{{ route('seller.employees.index') }}"
                            class="rounded-2xl border border-stone-200 bg-white px-5 py-2.5 text-sm font-semibold text-stone-700 transition hover:bg-stone-50">
                            Wróć do listy
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <aside class="space-y-6 lg:col-span-4">
            @unless ($employee)
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Wolne miejsca</h2>
                    <p class="mt-2 text-sm text-stone-500">Po tym zaproszeniu zostanie ich {{ max(0, $slotsLeft - 1) }}.</p>
                </div>
            @endunless

            <x-seller.employees-help />
        </aside>
    </div>
</x-layouts.panel>
