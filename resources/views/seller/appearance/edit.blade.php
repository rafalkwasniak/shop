<x-layouts.panel title="Wygląd">
    <x-slot:heading>Wygląd sklepu</x-slot:heading>

    @php
        // Kolor własny sklepu (kanoniczny #RRGGBB lub null). Napędza box „Kolor
        // przewodni" oraz wirtualną próbkę „custom" przy każdym szablonie.
        $brandColor = $shop->brandColor();

        // Karta „Banery na stronie głównej" (osobny ekran Wygląd → Banery).
        $bannerCount = $shop->banners()->count();

        // Charakter sklepu — krój nagłówków i stopień zaokrągleń (config/themes.php).
        // Te dwie osie są niezależne od szablonu, więc jeden zestaw zmiennych CSS
        // idzie na WSZYSTKIE kafle podglądu naraz. Wypisujemy je serwerowo, żeby
        // podgląd był zgodny z zapisem od pierwszej klatki (JS tylko go zmienia).
        $currentFont = $shop->themeFont();
        $currentRadius = $shop->themeRadius();
        $characterStyle = collect($shop->themeRadiusVars())
            ->map(fn ($size, $step) => "--radius-{$step}: {$size};")
            ->push($currentFont === 'plain' ? '--font-serif: var(--font-sans);' : '')
            ->filter()
            ->implode(' ');
    @endphp

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: formularz --}}
        <div class="lg:col-span-8 space-y-6">
            {{-- Banery mają osobny ekran (własne formularze nie zmieszczą się
                 w tym — formularzy HTML nie da się zagnieżdżać). --}}
            <div id="banery" class="scroll-mt-24 flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div>
                    <h2 class="font-semibold text-stone-900">Banery na stronie głównej</h2>
                    <p class="mt-1 text-sm text-stone-500">
                        {{ $bannerCount === 0 ? 'Duży obrazek albo pokaz kilku obrazków pod nazwą sklepu.' : 'Dodane banery: '.$bannerCount.' z '.(int) $shop->entitlement('max_banners').'.' }}
                    </p>
                </div>
                <a href="{{ route('seller.banners.index') }}"
                    class="shrink-0 rounded-xl border border-stone-200 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-100">
                    {{ $bannerCount === 0 ? 'Dodaj baner' : 'Zarządzaj banerami' }}
                </a>
            </div>

            <form method="POST" action="{{ route('seller.appearance.update') }}" class="space-y-6" enctype="multipart/form-data" novalidate data-validate>
                @csrf

                {{-- Logo (grafika i elementy wizualne; docelowo też kolory/szablony) --}}
                <div id="logo" class="scroll-mt-24 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Logo sklepu</h2>
                    <p class="mt-1 text-sm text-stone-500">Wizytówka Twojej marki — pojawi się w sklepie i przy jego prezentacji.</p>

                    <div class="mt-6">
                        <label for="logo" class="block text-sm font-medium text-stone-700">Plik logo <span class="text-stone-400">(opcjonalnie)</span></label>
                        <div class="mt-1.5 flex items-center gap-4">
                            <div class="flex h-20 shrink-0 items-center">
                                <img id="logo-preview"
                                    src="{{ $shop->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($shop->logo_path) : '' }}"
                                    alt="Logo sklepu"
                                    class="h-20 w-auto max-w-[14rem] object-contain {{ $shop->logo_path ? '' : 'hidden' }}">
                                <span id="logo-placeholder" class="flex h-20 w-20 items-center justify-center rounded-2xl border border-dashed border-stone-300 text-2xl text-stone-400 {{ $shop->logo_path ? 'hidden' : '' }}">🛍️</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"
                                    class="block w-full text-sm text-stone-500 file:mr-4 file:rounded-xl file:border-0 file:bg-amber-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-amber-800 file:transition hover:file:bg-amber-200">
                                <p class="mt-1.5 text-xs text-stone-400">PNG, JPG lub WebP, do 2 MB. Najlepiej kwadratowe.</p>
                                @if ($shop->logo_path)
                                    <label class="mt-2 inline-flex items-center gap-2 text-sm text-stone-600">
                                        <input type="checkbox" name="remove_logo" value="1" class="shrink-0">
                                        <span>Usuń obecne logo</span>
                                    </label>
                                @endif
                            </div>
                        </div>
                        @error('logo')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Kolor przewodni (własny akcent sklepu) --}}
                <div id="kolor" class="scroll-mt-24 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Kolor przewodni</h2>
                    <p class="mt-1 text-sm text-stone-500">Twój własny kolor marki. Gdy go ustawisz, przy każdym szablonie pojawi się dodatkowa próbka „Twój kolor" — reszta odcieni dobierze się sama, żeby sklep pozostał czytelny.</p>

                    <div class="mt-6 flex flex-wrap items-center gap-4">
                        <input type="color" id="brand-color-input" value="{{ $brandColor ?? '#3B82F6' }}"
                            aria-label="Wybierz kolor przewodni"
                            class="h-12 w-12 shrink-0 cursor-pointer rounded-xl border border-stone-200 bg-white p-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-stone-500">HEX</span>
                            {{-- To pole niesie wartość na serwer; color input jest tylko pomocą. --}}
                            <input type="text" id="brand-color-hex" name="brand_color" value="{{ $brandColor ?? '' }}"
                                placeholder="#RRGGBB" maxlength="7" autocomplete="off" spellcheck="false"
                                class="w-32 rounded-xl border border-stone-200 px-3 py-2 font-mono text-sm uppercase text-stone-700 focus:border-amber-400 focus:ring-amber-400/30">
                        </div>
                        <button type="button" id="brand-color-clear"
                            class="text-sm text-stone-500 underline-offset-2 hover:text-stone-700 hover:underline {{ $brandColor ? '' : 'hidden' }}">
                            Wyczyść
                        </button>
                    </div>
                    @error('brand_color')
                        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Charakter: czcionka nagłówków + zaokrąglenia. Świadomie OSOBNY
                     box między kolorem a szablonami: to nie jest wybór z siatki
                     gotowców, tylko dwa przełączniki, które działają na każdy
                     szablon tak samo. --}}
                <div id="charakter" class="scroll-mt-24 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Charakter</h2>
                    <p class="mt-1 text-sm text-stone-500">Te dwa ustawienia działają niezależnie od szablonu — zmiana szablonu ich nie kasuje. Pozwalają ściszyć ozdobność, gdy sprzedajesz rzeczy konkretne, a nie artystyczne.</p>

                    <div class="mt-6 grid gap-6 sm:grid-cols-2">
                        <fieldset>
                            <legend class="text-sm font-medium text-stone-700">Czcionka nagłówków</legend>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach (config('themes.fonts') as $key => $option)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="font" value="{{ $key }}" class="peer sr-only"
                                            data-character-input data-character-axis="font"
                                            data-description="{{ $option['description'] }}"
                                            @checked($key === $currentFont)>
                                        <span class="block rounded-xl border border-stone-200 bg-white px-4 py-2 text-sm text-stone-600 transition peer-checked:ring-2 peer-checked:ring-stone-800 peer-checked:ring-offset-1">{{ $option['name'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                            {{-- Opis wybranej opcji; JS podmienia go przy zmianie. --}}
                            <p class="mt-2 text-xs text-stone-400" data-character-hint="font">{{ config("themes.fonts.{$currentFont}.description") }}</p>
                            @error('font')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        <fieldset>
                            <legend class="text-sm font-medium text-stone-700">Zaokrąglenia</legend>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach (config('themes.radii') as $key => $option)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="radius" value="{{ $key }}" class="peer sr-only"
                                            data-character-input data-character-axis="radius"
                                            data-description="{{ $option['description'] }}"
                                            data-vars="{{ json_encode($option['vars']) }}"
                                            @checked($key === $currentRadius)>
                                        {{-- Sam kafelek pokazuje swój stopień: kwadracik
                                             zaokrąglony dokładnie tak, jak zaokrągli sklep. --}}
                                        <span class="flex items-center gap-2 rounded-xl border border-stone-200 bg-white px-4 py-2 text-sm text-stone-600 transition peer-checked:ring-2 peer-checked:ring-stone-800 peer-checked:ring-offset-1">
                                            <span class="block h-6 w-6 shrink-0 bg-stone-300" style="border-radius: {{ $option['vars']['lg'] }};"></span>
                                            {{ $option['name'] }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="mt-2 text-xs text-stone-400" data-character-hint="radius">{{ config("themes.radii.{$currentRadius}.description") }}</p>
                            @error('radius')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </fieldset>
                    </div>
                </div>

                {{-- Kolory i szablon --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Kolory i szablon</h2>
                    <p class="mt-1 text-sm text-stone-500">Wybierz wygląd swojego sklepu, a potem dobierz kolory. Zmiany zobaczysz w podglądzie od razu.</p>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach (collect(config('themes.templates'))->sortBy('order') as $slug => $template)
                            @php
                                $isActive = $slug === $shop->templateSlug();
                                $activePalette = $isActive ? $shop->themePalette() : $template['default_palette'];
                                // Baza custom = domyślna paleta szablonu (surface/ink dziedziczy).
                                $defaultTokens = $template['palettes'][$template['default_palette']]['tokens'];
                                // Paleta „custom" nie żyje w configu — jej tokeny liczy model.
                                $previewTokens = $activePalette === 'custom'
                                    ? $shop->themeTokens()
                                    : $template['palettes'][$activePalette]['tokens'];

                                // Pasek i stopka mini-witryny — te same reguły co w layoucie
                                // storefrontu (patrz „CHROME" w config/themes.php). Bez tego
                                // wszystkie karty wyglądały tak samo, bo różnił je tylko guzik.
                                $chrome = $template['chrome'] ?? 'neutral';
                                $chromeMix = (int) config('themes.chrome_brand_mix');
                                $chromeBg = match ($chrome) {
                                    'brand' => "color-mix(in srgb, {$previewTokens['brand']} {$chromeMix}%, {$previewTokens['surface']})",
                                    'brand_tint' => "color-mix(in srgb, {$previewTokens['brand']} 12%, {$previewTokens['surface']})",
                                    default => "color-mix(in srgb, {$previewTokens['ink']} 8%, {$previewTokens['surface']})",
                                };
                                $chromeInk = $previewTokens['ink'];

                                // Faktura chrome — te same wzory co w layoucie storefrontu
                                // (i co funkcja chromeTexture() w JS niżej).
                                $texture = $template['chrome_texture'] ?? 'none';
                                $patternColor = "color-mix(in srgb, {$previewTokens['surface']} 55%, transparent)";
                                $chromePattern = match ($texture) {
                                    'awning' => "background-image: repeating-linear-gradient(135deg, {$patternColor} 0 5px, transparent 5px 16px);",
                                    'dots' => "background-image: radial-gradient(circle, {$patternColor} 1.6px, transparent 1.7px), radial-gradient(circle, {$patternColor} 1.6px, transparent 1.7px); background-size: 18px 18px; background-position: 0 0, 9px 9px;",
                                    'pinpoint' => "background-image: radial-gradient(circle, {$patternColor} 1.1px, transparent 1.2px), radial-gradient(circle, {$patternColor} 1.1px, transparent 1.2px); background-size: 10px 10px; background-position: 0 0, 5px 5px;",
                                    'stripes' => "background-image: repeating-linear-gradient(45deg, {$patternColor} 0 3px, transparent 3px 13px);",
                                    default => '',
                                };
                            @endphp
                            <div data-template-card="{{ $slug }}" data-chrome="{{ $chrome }}" data-texture="{{ $texture }}"
                                class="tpl-card flex flex-col overflow-hidden rounded-2xl border bg-white transition {{ $isActive ? 'border-amber-400 ring-2 ring-amber-400/60' : 'border-stone-200' }}">
                                <input type="radio" name="template" id="template-{{ $slug }}" value="{{ $slug }}" class="sr-only" data-template-input @checked($isActive)>

                                {{-- Klik w podgląd/nazwę = wybór szablonu --}}
                                <label for="template-{{ $slug }}" class="cursor-pointer">
                                    {{-- Mini-witryna produktu (żywy podgląd palety) --}}
                                    {{-- `$characterStyle` niesie --radius-* i (dla kroju
                                         prostego) --font-serif. Zmienne siedzą NA kontenerze
                                         podglądu, nie w :root, więc dziedziczą je tylko
                                         wnętrzności mini-witryny — panel wokół zostaje
                                         nietknięty. Klasy `rounded-*` w środku czytają
                                         dokładnie te zmienne, tak samo jak storefront. --}}
                                    <div data-preview="{{ $slug }}"
                                        style="background: {{ $previewTokens['surface'] }}; color: {{ $previewTokens['ink'] }}; {{ $characterStyle }}">
                                        {{-- Miniatura paska sklepu (chrome) — nazwa + kreska „menu" --}}
                                        <div data-preview-bar class="flex items-center justify-between px-4 py-2 text-[10px] font-semibold"
                                            style="background: {{ $chromeBg }}; color: {{ $chromeInk }}; {{ $chromePattern }}">
                                            <span class="truncate">{{ $shop->name }}</span>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3 w-3 shrink-0 opacity-80" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                                        </div>
                                        <div class="p-4 pt-3">
                                        @if (! empty($previewImageUrl))
                                            {{-- Realne zdjęcie produktu sklepu — „to Twój sklep". --}}
                                            <img src="{{ $previewImageUrl }}" alt="Podgląd produktu"
                                                class="aspect-square w-full rounded-lg object-cover">
                                        @else
                                            {{-- Brak zdjęć — neutralny placeholder w kolorze palety (żywy). --}}
                                            <div data-preview-img class="flex aspect-square w-full items-center justify-center rounded-lg"
                                                style="background: {{ $previewTokens['brand'] }}; color: {{ $previewTokens['brand_ink'] }};">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-9 w-9 opacity-70" aria-hidden="true">
                                                    <rect x="3" y="4" width="18" height="16" rx="2" />
                                                    <circle cx="8.5" cy="9.5" r="1.5" fill="currentColor" stroke="none" />
                                                    <path d="M4 17l4.5-4.5 3 3L15 12l5 5" />
                                                </svg>
                                            </div>
                                        @endif
                                        {{-- Realny produkt, gdy sklep ma zdjęcie; inaczej przykład. --}}
                                        {{-- `font-serif` jak na prawdziwej karcie produktu
                                             (product-card: nazwa serifem, cena sansem) —
                                             dzięki temu przełącznik kroju widać w podglądzie. --}}
                                        <p class="mt-2.5 truncate font-serif text-sm opacity-70">{{ $previewProduct?->name ?? 'Nazwa produktu' }}</p>
                                        <p class="mt-0.5 text-sm font-semibold">{{ $previewProduct ? \App\Support\Money::pln($previewProduct->price_gross) : '49,00 zł' }}</p>
                                        <span data-preview-btn class="mt-2 block rounded-lg py-1 text-center text-[11px] font-semibold"
                                            style="background: {{ $previewTokens['brand'] }}; color: {{ $previewTokens['brand_ink'] }};">Zobacz produkt</span>
                                        </div>
                                    </div>

                                    <div class="border-t border-stone-100 px-4 pt-4">
                                        <div class="flex items-center justify-between gap-2">
                                            <h3 class="text-sm font-semibold text-stone-900">{{ $template['name'] }}</h3>
                                            <span data-check class="text-amber-500 transition {{ $isActive ? '' : 'opacity-0' }}">✓</span>
                                        </div>
                                        <p class="mt-1 text-xs leading-relaxed text-stone-500">{{ $template['description'] }}</p>
                                    </div>
                                </label>

                                {{-- Palety w ramach szablonu.
                                     `mt-auto` dokleja rząd kropek do DOŁU karty: opisy
                                     szablonów mają różną długość, więc bez tego kropki
                                     wisiały na czterech różnych wysokościach i rząd
                                     czytał się jak przypadek, a nie jak jeden wybór.
                                     Karta jest `flex flex-col`, więc wystarczy ta jedna
                                     klasa — bez sztywnych wysokości opisów. --}}
                                <div class="mt-auto flex flex-wrap items-center gap-2 px-4 pb-4 pt-3">
                                    @foreach ($template['palettes'] as $key => $palette)
                                        <label class="cursor-pointer" title="{{ $palette['name'] }}">
                                            <input type="radio" name="palettes[{{ $slug }}]" value="{{ $key }}" class="peer sr-only"
                                                data-palette-input
                                                data-brand="{{ $palette['tokens']['brand'] }}"
                                                data-brand-ink="{{ $palette['tokens']['brand_ink'] }}"
                                                data-surface="{{ $palette['tokens']['surface'] }}"
                                                data-ink="{{ $palette['tokens']['ink'] }}"
                                                @checked($key === $activePalette)>
                                            <span class="block h-6 w-6 rounded-full border border-black/10 transition peer-checked:ring-2 peer-checked:ring-stone-800 peer-checked:ring-offset-1"
                                                style="background: {{ $palette['tokens']['brand'] }};"></span>
                                        </label>
                                    @endforeach

                                    {{-- Próbka „Twój kolor" (custom) — widoczna dopiero, gdy ustawiono
                                         kolor przewodni. Dashed outline = „to Twój własny akcent". --}}
                                    <label data-custom-swatch="{{ $slug }}" title="Twój kolor"
                                        class="cursor-pointer {{ $brandColor ? '' : 'hidden' }}">
                                        <input type="radio" name="palettes[{{ $slug }}]" value="custom" class="peer sr-only"
                                            data-palette-input data-custom-input
                                            data-brand="{{ $brandColor ?? '' }}"
                                            data-brand-ink="{{ $brandColor ? \App\Support\Color::readableInkOn($brandColor) : '#FFFFFF' }}"
                                            data-surface="{{ $defaultTokens['surface'] }}"
                                            data-ink="{{ $defaultTokens['ink'] }}"
                                            @checked($isActive && $activePalette === 'custom')>
                                        <span data-custom-dot class="block h-6 w-6 rounded-full outline-dashed outline-1 outline-offset-2 outline-stone-400 transition peer-checked:ring-2 peer-checked:ring-stone-800 peer-checked:ring-offset-1"
                                            style="background: {{ $brandColor ?? 'transparent' }};"></span>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                        class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                        Zapisz wygląd
                    </button>
                </div>
            </form>
        </div>

        {{-- Kolumna pomocnicza: wskazówki --}}
        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Wskazówki</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🖼️</span>
                        <span>Najlepsze logo to kwadrat (np. 512×512 px) na jednolitym tle.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">✨</span>
                        <span>Logo wzmacnia wizerunek marki — buduje rozpoznawalność i zaufanie klientów.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🎨</span>
                        <span>Najpierw wybierz szablon, potem dobierz paletę kolorów — podgląd zmienia się od razu.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🏬</span>
                        <span>Wygląd dotyczy Twojego sklepu widocznego dla klientów — panel sprzedawcy zostaje bez zmian.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>

    {{-- Podgląd logo na żywo (zero zależności). --}}
    <script>
        (function () {
            const input = document.getElementById('logo');
            const preview = document.getElementById('logo-preview');
            const placeholder = document.getElementById('logo-placeholder');
            if (!input || !preview) return;

            input.addEventListener('change', function () {
                const file = input.files && input.files[0];
                if (!file) return;
                preview.src = URL.createObjectURL(file);
                preview.classList.remove('hidden');
                if (placeholder) placeholder.classList.add('hidden');
            });
        })();
    </script>

    {{-- Wybór szablonu i palety: ring zaznaczenia + żywy podgląd kolorów (zero zależności). --}}
    <script>
        (function () {
            const cards = document.querySelectorAll('[data-template-card]');
            if (!cards.length) return;

            function selectTemplate(slug) {
                cards.forEach(function (card) {
                    const active = card.getAttribute('data-template-card') === slug;
                    card.classList.toggle('border-amber-400', active);
                    card.classList.toggle('ring-2', active);
                    card.classList.toggle('ring-amber-400/60', active);
                    card.classList.toggle('border-stone-200', !active);
                    const check = card.querySelector('[data-check]');
                    if (check) check.classList.toggle('opacity-0', !active);
                });
            }

            document.querySelectorAll('[data-template-input]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    if (radio.checked) selectTemplate(radio.value);
                });
            });

            document.querySelectorAll('[data-palette-input]').forEach(function (inp) {
                inp.addEventListener('change', function () {
                    const card = inp.closest('[data-template-card]');
                    if (!card) return;
                    const slug = card.getAttribute('data-template-card');
                    const preview = card.querySelector('[data-preview]');
                    const img = card.querySelector('[data-preview-img]');
                    const btn = card.querySelector('[data-preview-btn]');
                    const bar = card.querySelector('[data-preview-bar]');
                    if (preview) {
                        preview.style.background = inp.dataset.surface;
                        preview.style.color = inp.dataset.ink;
                    }
                    // Pasek chrome — te same reguły co PHP wyżej (i layout storefrontu).
                    if (bar) {
                        const chrome = card.getAttribute('data-chrome');
                        if (chrome === 'brand') {
                            bar.style.background = 'color-mix(in srgb, ' + inp.dataset.brand + ' {{ (int) config('themes.chrome_brand_mix') }}%, ' + inp.dataset.surface + ')';
                            bar.style.color = inp.dataset.ink;
                        } else if (chrome === 'brand_tint') {
                            bar.style.background = 'color-mix(in srgb, ' + inp.dataset.brand + ' 12%, ' + inp.dataset.surface + ')';
                            bar.style.color = inp.dataset.ink;
                        } else {
                            bar.style.background = 'color-mix(in srgb, ' + inp.dataset.ink + ' 8%, ' + inp.dataset.surface + ')';
                            bar.style.color = inp.dataset.ink;
                        }
                        // Skrót `background` zdjął wzór — nałóż fakturę na nowo
                        // (te same wzory co PHP wyżej i layout storefrontu).
                        const p = 'color-mix(in srgb, ' + inp.dataset.surface + ' 55%, transparent)';
                        const texture = card.getAttribute('data-texture');
                        if (texture === 'awning') {
                            bar.style.backgroundImage = 'repeating-linear-gradient(135deg, ' + p + ' 0 5px, transparent 5px 16px)';
                        } else if (texture === 'dots' || texture === 'pinpoint') {
                            const r = texture === 'dots' ? [1.6, 1.7, 18, 9] : [1.1, 1.2, 10, 5];
                            const dot = 'radial-gradient(circle, ' + p + ' ' + r[0] + 'px, transparent ' + r[1] + 'px)';
                            bar.style.backgroundImage = dot + ', ' + dot;
                            bar.style.backgroundSize = r[2] + 'px ' + r[2] + 'px';
                            bar.style.backgroundPosition = '0 0, ' + r[3] + 'px ' + r[3] + 'px';
                        } else if (texture === 'stripes') {
                            bar.style.backgroundImage = 'repeating-linear-gradient(45deg, ' + p + ' 0 3px, transparent 3px 13px)';
                        }
                    }
                    if (img) {
                        img.style.background = inp.dataset.brand;
                        img.style.color = inp.dataset.brandInk;
                    }
                    if (btn) {
                        btn.style.background = inp.dataset.brand;
                        btn.style.color = inp.dataset.brandInk;
                    }
                    // Wybór palety oznacza też wybór jej szablonu.
                    const tpl = document.getElementById('template-' + slug);
                    if (tpl && !tpl.checked) {
                        tpl.checked = true;
                        selectTemplate(slug);
                    }
                });
            });
        })();
    </script>

    {{-- Charakter: żywy podgląd kroju i zaokrągleń na wszystkich kaflach naraz.
         Tanie, bo nie dotykamy pojedynczych elementów — przestawiamy zmienne CSS
         na kontenerze podglądu, a `.font-serif` i `.rounded-*` w środku same się
         do nich stosują. Te same zmienne co w :root storefrontu, więc podgląd
         i sklep nie mogą się rozjechać. --}}
    <script>
        (function () {
            const inputs = document.querySelectorAll('[data-character-input]');
            const previews = document.querySelectorAll('[data-preview]');
            if (! inputs.length || ! previews.length) return;

            function apply(input) {
                const axis = input.dataset.characterAxis;

                previews.forEach(function (preview) {
                    if (axis === 'font') {
                        // Krój prosty = nagłówki tym samym krojem co treść (jak
                        // w storefroncie). Dekoracyjny nie nadpisuje nic — wraca
                        // serif z arkusza, stąd removeProperty zamiast wartości.
                        if (input.value === 'plain') {
                            preview.style.setProperty('--font-serif', 'var(--font-sans)');
                        } else {
                            preview.style.removeProperty('--font-serif');
                        }
                        return;
                    }

                    const vars = JSON.parse(input.dataset.vars || '{}');
                    Object.keys(vars).forEach(function (step) {
                        preview.style.setProperty('--radius-' + step, vars[step]);
                    });
                });

                // Podpis pod przełącznikiem opisuje AKTUALNY wybór.
                const hint = document.querySelector('[data-character-hint="' + axis + '"]');
                if (hint) hint.textContent = input.dataset.description || '';
            }

            inputs.forEach(function (input) {
                input.addEventListener('change', function () {
                    if (input.checked) apply(input);
                });
            });
        })();
    </script>

    {{-- Kolor przewodni: picker ↔ HEX, propagacja na próbki „custom", Wyczyść. --}}
    <script>
        (function () {
            const colorInput = document.getElementById('brand-color-input');
            const hexInput = document.getElementById('brand-color-hex');
            const clearBtn = document.getElementById('brand-color-clear');
            if (!hexInput) return;

            const swatches = document.querySelectorAll('[data-custom-swatch]');
            const HEX_RE = /^#[0-9A-Fa-f]{6}$/;

            // Mirror App\Support\Color::readableInkOn — trzymaj zgodne z PHP.
            function inkOn(hex) {
                const h = hex.replace('#', '');
                const r = parseInt(h.slice(0, 2), 16);
                const g = parseInt(h.slice(2, 4), 16);
                const b = parseInt(h.slice(4, 6), 16);
                const lum = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                return lum > 0.6 ? '#1A1A1A' : '#FFFFFF';
            }

            // Rozpropaguj kolor na wszystkie próbki custom (dane + kropka), odsłoń
            // je i „Wyczyść". Gdy custom jest gdzieś aktywny — odśwież podgląd karty.
            function applyColor(hex) {
                const ink = inkOn(hex);
                swatches.forEach(function (label) {
                    label.classList.remove('hidden');
                    const input = label.querySelector('[data-custom-input]');
                    const dot = label.querySelector('[data-custom-dot]');
                    if (input) {
                        input.dataset.brand = hex;
                        input.dataset.brandInk = ink;
                    }
                    if (dot) dot.style.background = hex;
                    if (input && input.checked) input.dispatchEvent(new Event('change'));
                });
                if (clearBtn) clearBtn.classList.remove('hidden');
            }

            // Wyczyść kolor: ukryj próbki i przycisk; a jeśli custom był gdzieś
            // zaznaczony — przeskocz na 1. (domyślną) paletę tego szablonu.
            function clearColor() {
                hexInput.value = '';
                swatches.forEach(function (label) {
                    const input = label.querySelector('[data-custom-input]');
                    if (input && input.checked) {
                        const card = label.closest('[data-template-card]');
                        const first = card && card.querySelector('[data-palette-input]:not([data-custom-input])');
                        if (first) {
                            first.checked = true;
                            first.dispatchEvent(new Event('change'));
                        }
                    }
                    label.classList.add('hidden');
                });
                if (clearBtn) clearBtn.classList.add('hidden');
            }

            if (colorInput) {
                colorInput.addEventListener('input', function () {
                    hexInput.value = colorInput.value.toUpperCase();
                    applyColor(hexInput.value);
                });
            }

            // Ręczny wpis HEX: sanityzuj do „#" + max 6 znaków hex; zastosuj gdy pełny.
            hexInput.addEventListener('input', function () {
                const digits = hexInput.value.replace(/[^0-9A-Fa-f]/g, '').slice(0, 6);
                hexInput.value = digits ? '#' + digits.toUpperCase() : '';
                if (HEX_RE.test(hexInput.value)) {
                    if (colorInput) colorInput.value = hexInput.value;
                    applyColor(hexInput.value);
                }
            });

            if (clearBtn) clearBtn.addEventListener('click', clearColor);
        })();
    </script>
</x-layouts.panel>
