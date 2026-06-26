<x-layouts.guest title="Sprawdź skrzynkę">
    <div class="rounded-3xl border border-white/60 bg-white/70 p-8 text-center shadow-xl shadow-amber-900/5 backdrop-blur-xl">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-rose-500 text-2xl text-white">✉</div>
        <h1 class="mt-5 text-2xl font-semibold tracking-tight text-stone-900">Sprawdź skrzynkę</h1>
        <p class="mt-3 text-stone-500">
            Wysłaliśmy na Twój adres e-mail link do dokończenia rejestracji. Kliknij go,
            aby uzupełnić dane, ustawić hasło i zalogować się do panelu sprzedawcy.
        </p>

        <div class="mt-6 border-t border-stone-200/70 pt-5 text-left">
            <p class="text-sm text-stone-500">Link nie dotarł? Sprawdź folder spam — albo wyślij go ponownie:</p>
            <form method="POST" action="{{ route('register.resend') }}" class="mt-3 space-y-3">
                @csrf
                <input type="email" name="email" required placeholder="Twój adres e-mail"
                    value="{{ old('email', session('registered_email')) }}"
                    class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                @error('email')
                    <x-alert type="error">{{ $message }}</x-alert>
                @enderror
                <button type="submit"
                    class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-700 transition hover:border-stone-400 hover:bg-stone-50">Wyślij ponownie</button>
            </form>
        </div>
    </div>

    <p class="mt-6 text-center text-sm text-stone-500">
        <a href="{{ route('login') }}" class="font-semibold text-amber-700 hover:text-amber-800">Wróć do logowania</a>
    </p>
</x-layouts.guest>
