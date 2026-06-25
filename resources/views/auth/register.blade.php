<x-layouts.guest title="Załóż sklep">
    <div class="rounded-3xl border border-white/60 bg-white/70 p-8 shadow-xl shadow-amber-900/5 backdrop-blur-xl">
        <h1 class="text-3xl font-semibold tracking-tight text-stone-900">Załóż sklep w 5 minut</h1>
        <p class="mt-2 text-stone-500">Utwórz konto sprzedawcy i zacznij sprzedawać jeszcze dziś.</p>

        <form method="POST" action="{{ route('register.store') }}" class="mt-8 space-y-5">
            @csrf

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-stone-700">Imię</label>
                    <input id="name" name="name" type="text" autocomplete="given-name" required autofocus
                        value="{{ old('name') }}"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    @error('name')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="surname" class="block text-sm font-medium text-stone-700">Nazwisko</label>
                    <input id="surname" name="surname" type="text" autocomplete="family-name" required
                        value="{{ old('surname') }}"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    @error('surname')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-stone-700">Adres e-mail</label>
                <input id="email" name="email" type="email" autocomplete="username" required
                    value="{{ old('email') }}"
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                @error('email')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-stone-700">Hasło</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                @error('password')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-stone-700">Powtórz hasło</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
            </div>

            <button type="submit"
                class="w-full rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                Załóż konto
            </button>
        </form>
    </div>

    <p class="mt-6 text-center text-sm text-stone-500">
        Masz już konto?
        <a href="{{ route('login') }}" class="font-semibold text-amber-700 hover:text-amber-800">Zaloguj się</a>
    </p>
</x-layouts.guest>
