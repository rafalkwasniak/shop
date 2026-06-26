<x-layouts.guest title="Dokończ rejestrację">
    <div class="rounded-3xl border border-white/60 bg-white/70 p-8 shadow-xl shadow-amber-900/5 backdrop-blur-xl">
        <h1 class="text-3xl font-semibold tracking-tight text-stone-900">Dokończ rejestrację</h1>
        <p class="mt-2 text-stone-500">Sprawdź swoje dane, ustaw hasło i wejdź do panelu sprzedawcy.</p>

        <form method="POST" action="{{ route('activation.store') }}" class="mt-8 space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="token_email" value="{{ $tokenEmail }}">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-stone-700">Imię</label>
                    <input id="name" name="name" type="text" autocomplete="given-name" required
                        value="{{ old('name', $user?->name) }}"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    @error('name')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="surname" class="block text-sm font-medium text-stone-700">Nazwisko</label>
                    <input id="surname" name="surname" type="text" autocomplete="family-name" required
                        value="{{ old('surname', $user?->surname) }}"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                    @error('surname')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-stone-700">Adres e-mail</label>
                <input id="email" name="email" type="email" autocomplete="email" required
                    value="{{ old('email', $user?->email ?? $tokenEmail) }}"
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                @error('email')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-stone-700">Telefon <span class="text-stone-400">(opcjonalnie)</span></label>
                <input id="phone" name="phone" type="tel" autocomplete="tel"
                    value="{{ old('phone', $user?->phone) }}"
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                @error('phone')
                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

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
                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
            </div>

            <div class="space-y-3 pt-1">
                <label class="flex items-center gap-3 text-sm text-stone-600">
                    <input type="checkbox" name="terms" value="1" @checked(old('terms', $errors->any() ? null : '1')) class="shrink-0">
                    <span>
                        Akceptuję
                        <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener"
                            class="font-semibold text-amber-700 hover:text-amber-800">Regulamin</a>.
                    </span>
                </label>
                @error('terms')
                    <p class="text-sm text-rose-600">{{ $message }}</p>
                @enderror

                <label class="flex items-center gap-3 text-sm text-stone-600">
                    <input type="checkbox" name="privacy" value="1" @checked(old('privacy', $errors->any() ? null : '1')) class="shrink-0">
                    <span>
                        Akceptuję
                        <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener"
                            class="font-semibold text-amber-700 hover:text-amber-800">Politykę Prywatności</a>.
                    </span>
                </label>
                @error('privacy')
                    <p class="text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="w-full rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                Aktywuj konto i zaloguj
            </button>
        </form>
    </div>
</x-layouts.guest>
