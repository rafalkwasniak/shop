<x-layouts.guest title="Kod logowania">
    <div class="rounded-3xl border border-white/60 bg-white/70 p-8 shadow-xl shadow-amber-900/5 backdrop-blur-xl">
        <h1 class="text-3xl font-semibold tracking-tight text-stone-900">Sprawdź skrzynkę</h1>
        <p class="mt-2 text-stone-500">
            Wysłaliśmy 6-cyfrowy kod na <span class="font-medium text-stone-700">{{ $email }}</span>.
            Wpisz go, aby się zalogować.
        </p>

        @if (session('status'))
            <div class="mt-6 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ session('status') }}
            </div>
        @endif

        {{-- `autocomplete="one-time-code"` to warunek, żeby Safari podpowiedziało
             kod z aplikacji Mail nad klawiaturą. `inputmode="numeric"` daje na
             telefonie klawiaturę z cyframi. --}}
        <form method="POST" action="{{ route('login.code.verify') }}" class="mt-8 space-y-5">
            @csrf

            <div>
                <label for="code" class="block text-sm font-medium text-stone-700">Kod z maila</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                    maxlength="12" required autofocus
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-center text-2xl font-semibold tracking-widest shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                @error('code')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="w-full rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                Zaloguj się
            </button>
        </form>

        <form method="POST" action="{{ route('login.code.resend') }}" class="mt-6 text-center text-sm text-stone-500">
            @csrf
            Kod nie dotarł? Sprawdź folder spam albo
            <button type="submit" class="font-medium text-amber-700 transition hover:text-amber-800">wyślij nowy kod</button>.
        </form>
    </div>

    <p class="mt-6 text-center text-sm text-stone-500">
        <a href="{{ route('login') }}" class="font-semibold text-amber-700 hover:text-amber-800">Wróć do logowania</a>
    </p>
</x-layouts.guest>
