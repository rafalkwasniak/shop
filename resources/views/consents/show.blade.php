<x-layouts.guest title="Aktualizacja dokumentów">
    <div class="rounded-3xl border border-white/60 bg-white/70 p-8 shadow-xl shadow-amber-900/5 backdrop-blur-xl">
        <h1 class="text-2xl font-semibold tracking-tight text-stone-900">Zaktualizowaliśmy dokumenty</h1>
        <p class="mt-2 text-stone-500">
            Zanim przejdziesz dalej, prosimy o akceptację aktualnej wersji poniższych dokumentów.
        </p>

        <form method="POST" action="{{ route('consents.store') }}" class="mt-8 space-y-4">
            @csrf

            @foreach ($documents as $document)
                <label class="flex items-start gap-3 text-sm text-stone-600">
                    <input type="checkbox" name="documents[{{ $document->id }}]" value="1"
                        @checked(old('documents.'.$document->id)) class="mt-0.5 shrink-0">
                    <span>
                        Akceptuję
                        <a href="{{ route($document->type->routeName()) }}" target="_blank" rel="noopener"
                            class="font-semibold text-amber-700 hover:text-amber-800">{{ $document->type->label() }}</a>.
                    </span>
                </label>
                @error('documents.'.$document->id)
                    <p class="text-sm text-rose-600">{{ $message }}</p>
                @enderror
            @endforeach

            <button type="submit"
                class="w-full rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                Akceptuję i kontynuuję
            </button>
        </form>
    </div>

    <p class="mt-6 text-center text-sm text-stone-500">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="font-semibold text-amber-700 hover:text-amber-800">Wyloguj się</button>
        </form>
    </p>
</x-layouts.guest>
