<x-layouts.panel :title="$banner->exists ? 'Edytuj baner' : 'Nowy baner'">
    <x-slot:heading>{{ $banner->exists ? 'Edytuj baner' : 'Nowy baner' }}</x-slot:heading>

    @php
        $input = 'mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15';
    @endphp

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8">
            <form method="POST"
                action="{{ $banner->exists ? route('seller.banners.update', $banner) : route('seller.banners.store') }}"
                enctype="multipart/form-data" class="space-y-6" novalidate data-validate>
                @csrf

                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Obrazek</h2>
                    <p class="mt-1 text-sm text-stone-500">
                        JPG, PNG lub WebP, co najmniej {{ config('shop.banners.min_width') }} px szerokości. Zalecany rozmiar: 1920 × 540 px.
                    </p>

                    @if ($banner->exists)
                        <img src="{{ $banner->url() }}" alt="" class="mt-4 w-full rounded-2xl border border-stone-200">
                    @endif

                    <div class="mt-4">
                        <label for="image" class="block text-sm font-medium text-stone-700">{{ $banner->exists ? 'Podmień obrazek' : 'Plik' }}</label>
                        <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp"
                            @unless ($banner->exists) required data-msg-required="Wybierz obrazek banera." @endunless
                            class="mt-1.5 block w-full text-sm text-stone-600 file:mr-4 file:rounded-xl file:border-0 file:bg-amber-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-amber-800 hover:file:bg-amber-200">
                        @error('image')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Opis i link</h2>

                    <div class="mt-5 space-y-5">
                        <div>
                            <label for="alt" class="block text-sm font-medium text-stone-700">Opis banera</label>
                            <input id="alt" name="alt" type="text" required maxlength="200"
                                value="{{ old('alt', $banner->alt) }}"
                                data-msg-required="Opisz krótko, co jest na banerze."
                                class="{{ $input }}">
                            <p class="mt-1.5 text-xs text-stone-400">Np. „Kawa Pallada Espresso — nowość w ofercie”. Czytają to wyszukiwarki i czytniki ekranu; klient zobaczy go, gdy obrazek się nie wczyta.</p>
                            @error('alt')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="link_url" class="block text-sm font-medium text-stone-700">Link <span class="font-normal text-stone-400">— opcjonalnie</span></label>
                            <input id="link_url" name="link_url" type="text" maxlength="500"
                                value="{{ old('link_url', $banner->link_url) }}"
                                placeholder="/produkty"
                                class="{{ $input }}">
                            <p class="mt-1.5 text-xs text-stone-400">Dokąd prowadzi kliknięcie. Strona w Twoim sklepie otworzy się w tym samym oknie, adres spoza sklepu — w nowym.</p>
                            @error('link_url')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Widoczność</h2>

                    <div class="mt-5 space-y-5">
                        <label class="flex items-start gap-3 text-sm text-stone-600">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="mt-0.5 shrink-0"
                                @checked(old('is_active', $banner->is_active))>
                            <span><span class="font-medium text-stone-800">Pokazuj w sklepie</span> — odznaczony baner zostaje na liście, ale klienci go nie widzą.</span>
                        </label>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="starts_on" class="block text-sm font-medium text-stone-700">Pokazuj od <span class="font-normal text-stone-400">— opcjonalnie</span></label>
                                <input id="starts_on" name="starts_on" type="date"
                                    value="{{ old('starts_on', $banner->starts_on?->format('Y-m-d')) }}"
                                    class="{{ $input }}">
                                @error('starts_on')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="ends_on" class="block text-sm font-medium text-stone-700">Pokazuj do <span class="font-normal text-stone-400">— opcjonalnie</span></label>
                                <input id="ends_on" name="ends_on" type="date"
                                    value="{{ old('ends_on', $banner->ends_on?->format('Y-m-d')) }}"
                                    class="{{ $input }}">
                                @error('ends_on')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <p class="text-xs text-stone-400">Obie daty włącznie. Bez dat baner jest widoczny bez przerwy.</p>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <a href="{{ route('seller.banners.index') }}" class="text-sm font-medium text-stone-500 transition hover:text-stone-800">← Wróć do listy</a>
                    <button type="submit"
                        class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                        {{ $banner->exists ? 'Zapisz zmiany' : 'Dodaj baner' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.panel>
