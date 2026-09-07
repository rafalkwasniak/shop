<x-layouts.guest title="Zaproszenie do panelu sklepu">
    <div class="rounded-3xl border border-white/60 bg-white/70 p-8 shadow-xl shadow-amber-900/5 backdrop-blur-xl">
        @if ($employment === null)
            {{-- Zaproszenie wycofane albo już przyjęte. Mówimy to wprost, zamiast
                 pokazywać formularz, który i tak odmówi po wysłaniu. --}}
            <h1 class="text-3xl font-semibold tracking-tight text-stone-900">Zaproszenie nieaktualne</h1>
            <p class="mt-2 text-stone-500">
                To zaproszenie zostało już wykorzystane albo wycofane. Jeśli nadal masz pracować w tym sklepie,
                poproś swojego pracodawcę o nowe.
            </p>
            <a href="{{ route('login') }}"
                class="mt-8 inline-flex rounded-2xl border border-stone-200 bg-white px-5 py-3 text-sm font-semibold text-stone-700 transition hover:bg-stone-50">
                Przejdź do logowania
            </a>
        @else
            <h1 class="text-3xl font-semibold tracking-tight text-stone-900">Ustaw swoje hasło</h1>
            <p class="mt-2 text-stone-500">
                <span class="font-medium text-stone-700">{{ $employment->shop->name }}</span>
                zaprasza Cię do panelu sklepu. Hasło ustalasz sam — nikt inny go nie zobaczy.
            </p>

            <div class="mt-6 rounded-2xl bg-stone-50 p-4">
                <p class="text-xs font-medium text-stone-600">Twoje konto</p>
                <p class="mt-1 text-sm text-stone-700">{{ $user->name }} {{ $user->surname }} · {{ $user->email }}</p>

                @if ($employment->sections() !== [])
                    <p class="mt-3 text-xs font-medium text-stone-600">Twoje działy</p>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        @foreach ($employment->sections() as $section)
                            <span class="rounded-full bg-white px-2.5 py-0.5 text-xs font-medium text-stone-600">{{ $section->label() }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            <form method="POST" action="{{ route('employee.invitation.store') }}" class="mt-8 space-y-5" novalidate data-validate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="token_email" value="{{ $tokenEmail }}">

                @error('token')
                    <p class="rounded-2xl bg-rose-50 p-4 text-sm text-rose-700">{{ $message }}</p>
                @enderror

                <div>
                    <label for="password" class="block text-sm font-medium text-stone-700">Hasło</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    <p class="mt-1.5 text-xs text-stone-400">Min. 8 znaków, w tym duża i mała litera oraz cyfra.</p>
                    @error('password')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-stone-700">Powtórz hasło</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                        data-match="password" data-msg-match="Hasła muszą być takie same."
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                </div>

                <button type="submit"
                    class="w-full rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                    Ustaw hasło i wejdź do panelu
                </button>
            </form>
        @endif
    </div>
</x-layouts.guest>
