<x-layouts.guest title="Logowanie">
    <div class="rounded-3xl border border-white/60 bg-white/70 p-8 shadow-xl shadow-amber-900/5 backdrop-blur-xl">
        <h1 class="text-3xl font-semibold tracking-tight text-stone-900">Witaj ponownie</h1>
        <p class="mt-2 text-stone-500">Zaloguj się, aby przejść do swojego panelu.</p>

        @if (session('status'))
            <div class="mt-6 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}" class="mt-8 space-y-5">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-stone-700">Adres e-mail</label>
                <input id="email" name="email" type="email" autocomplete="username" required autofocus
                    value="{{ old('email') }}"
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                @error('email')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-stone-700">Hasło</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                @error('password')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2 text-stone-600">
                    <input type="checkbox" name="remember" value="1"
                        class="rounded-md border-stone-300 text-amber-600 focus:ring-amber-500">
                    Zapamiętaj mnie na {{ intdiv((int) config('auth.guards.web.remember'), 60 * 24) }} dni
                </label>

                <a href="{{ route('password.request') }}" class="font-medium text-amber-700 transition hover:text-amber-800">
                    Nie pamiętam hasła
                </a>
            </div>

            <button type="submit"
                class="w-full rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                Zaloguj się
            </button>
        </form>
    </div>

    {{-- Zachęta do założenia sklepu tylko w Kramio. W sklepie dedykowanym trasa
         `register` w ogóle nie istnieje (routes/web.php), więc `route()` rzuciłby
         wyjątek i wywrócił ekran logowania właściciela — czyli jedyne wejście do
         panelu. Sam tekst też nie miałby sensu: to jest jego sklep. --}}
    @if (\App\Support\Mode::saas())
        <p class="mt-6 text-center text-sm text-stone-500">
            Nie masz jeszcze sklepu?
            <a href="{{ route('register') }}" class="font-semibold text-amber-700 hover:text-amber-800">Załóż za darmo</a>
        </p>
    @endif
</x-layouts.guest>
