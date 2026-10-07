<x-layouts.panel :title="$page->exists ? 'Edytuj stronę' : 'Nowa strona'">
    <x-slot:heading>{{ $page->exists ? 'Edytuj stronę' : 'Nowa strona' }}</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8">
            <form method="POST"
                action="{{ $page->exists ? route('seller.pages.update', $page) : route('seller.pages.store') }}"
                class="space-y-6" novalidate data-validate>
                @csrf

                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Treść strony</h2>
                    <p class="mt-1 text-sm text-stone-500">Tytuł i treść widoczne dla klientów w menu i stopce sklepu.</p>

                    <div class="mt-6 space-y-5">
                        <div>
                            <label for="title" class="block text-sm font-medium text-stone-700">Tytuł</label>
                            @if ($page->isLocked())
                                {{-- Regulamin i Zasady punktów: tytuł jest stały (strona systemowa). --}}
                                <input id="title" type="text" value="{{ $page->title }}" disabled
                                    class="mt-1.5 block w-full cursor-not-allowed rounded-2xl border border-stone-200 bg-stone-100 px-4 py-3 text-sm text-stone-500 shadow-sm">
                                <p class="mt-1.5 text-xs text-stone-400">Tytuł strony systemowej jest stały — możesz zmienić tylko treść i kolejność.</p>
                            @else
                                <input id="title" name="title" type="text" required
                                    value="{{ old('title', $page->title) }}"
                                    data-msg-required="Podaj tytuł strony."
                                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @error('title')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            @endif
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-stone-700">Treść</label>
                            <x-rich-editor name="content" :value="old('content', $page->content)" ai-field="page_content" :max="config('pages.content_max')">Napisz treść strony — np. zasady dostawy, zwrotów albo słowo o sklepie.</x-rich-editor>
                            @if ($page->is_system)
                                {{-- Wersja wzoru jedzie razem z treścią, a nie osobnym zapisem:
                                     wstawienie do edytora niczego nie utrwala, więc wersję da się
                                     zapisać dopiero wtedy, gdy sprzedawca faktycznie kliknie
                                     „Zapisz". Wartość podstawia `insertTerms()` przez `withInput()`,
                                     a przy zwykłej edycji wraca ta już zapisana. --}}
                                <input type="hidden" name="terms_template_version"
                                    value="{{ old('terms_template_version', $page->terms_template_version) }}">
                            @endif
                            @error('content')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Widoczność</h2>
                    @if ($page->is_system)
                        <p class="mt-3 text-sm text-stone-500">Regulamin jest zawsze widoczny w sklepie — nie można go ukryć.</p>
                    @elseif ($page->isLoyaltyRules())
                        <p class="mt-3 text-sm text-stone-500">Zasady punktów są zawsze widoczne w sklepie, dopóki punkty są włączone — klient ma prawo wiedzieć, na jakich warunkach je zbiera.</p>
                    @else
                        <div class="mt-4 space-y-4">
                            <label class="flex items-start gap-3 text-sm text-stone-600">
                                <input type="checkbox" name="published" value="1" class="mt-0.5 shrink-0"
                                    @checked(old('published', $page->exists ? $page->published : true))>
                                <span>
                                    <span class="font-medium text-stone-800">Opublikowana</span> — widoczna w menu i stopce sklepu. Odznacz, aby ukryć stronę w przygotowaniu.
                                </span>
                            </label>
                            <label class="flex items-start gap-3 text-sm text-stone-600">
                                <input type="checkbox" name="show_on_homepage" value="1" class="mt-0.5 shrink-0"
                                    @checked(old('show_on_homepage', $page->show_on_homepage))>
                                <span>
                                    <span class="font-medium text-stone-800">Wyróżnij na stronie głównej</span> — pokaż zajawkę tej strony pod ofertą, obok innych wyróżnionych treści.
                                    @isset($homepage)
                                        @php($slotsFull = $homepage['count'] >= $homepage['limit'] && ! old('show_on_homepage', $page->show_on_homepage))
                                        <span class="mt-1 block text-xs {{ $slotsFull ? 'text-rose-600' : 'text-stone-400' }}">
                                            Zajęte {{ $homepage['count'] }} z {{ $homepage['limit'] }} miejsc na stronie głównej.@if ($slotsFull) Odznacz inną stronę, aby zwolnić miejsce.@endif
                                        </span>
                                    @endisset
                                </span>
                            </label>
                            @error('show_on_homepage')
                                <p class="text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
                </div>

                {{-- SEO: pole WYŁĄCZNIE ręczne — bez przycisku AI (brak `source-field`).
                     Powód w migracji: to głównie Regulamin i Polityka prywatności,
                     długie i niewyszukiwane, więc generowanie byłoby przepalaniem
                     tokenów. Podpowiedź pokazuje, co wystawimy automatycznie. --}}
                <x-seller.seo-box
                    :value="$page->meta_description"
                    :preview="$page->exists ? \App\Support\Seo::pageDescription($page, $page->shop) : null"
                    hint="Zostaw puste, a opis weźmiemy z początku treści strony." />

                <div class="flex items-center justify-between gap-3">
                    <a href="{{ route('seller.pages.index') }}" class="text-sm font-medium text-stone-500 transition hover:text-stone-800">← Wróć do listy</a>
                    <button type="submit"
                        class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                        {{ $page->exists ? 'Zapisz zmiany' : 'Dodaj stronę' }}
                    </button>
                </div>
            </form>
        </div>

        <aside class="lg:col-span-4 space-y-6">
            {{-- Zasady punktów: świeży wzór z bieżących ustawień, wstawiany do
                 edytora BEZ zapisu (jak wzór regulaminu). Aktualność treści po
                 zmianie ustawień to obowiązek sprzedawcy — dajemy mu narzędzie. --}}
            @if ($page->exists && $page->isLoyaltyRules())
                <div class="rounded-3xl border border-amber-200 bg-amber-50 p-6">
                    <h2 class="font-semibold text-stone-900">Treść domyślna</h2>
                    <p class="mt-2 text-sm leading-relaxed text-stone-600">
                        Po zmianie ustawień punktów wstaw do edytora zasady opisane na nowo z tego, co jest teraz ustawione.
                        <span class="font-medium text-stone-700">Nic nie zostanie zapisane</span>, dopóki nie klikniesz „Zapisz zmiany".
                    </p>
                    <form method="POST" action="{{ route('seller.pages.loyalty.insert', $page) }}" class="mt-4">
                        @csrf
                        <button type="submit"
                            class="rounded-2xl border border-amber-300 bg-white px-4 py-2.5 text-sm font-semibold text-amber-900 transition hover:bg-amber-100">
                            Wstaw treść domyślną
                        </button>
                    </form>
                    <p class="mt-3 text-xs text-stone-500">Ustawienia punktów znajdziesz w <a href="{{ route('seller.settings.edit') }}#punkty" class="font-medium underline decoration-amber-300 underline-offset-2">Ustawieniach</a>.</p>
                </div>
            @endif

            {{-- Kreator regulaminu — OSOBNY formularz, bo celuje w inną trasę niż
                 zapis strony. Tylko przy stronie systemowej: pozostałe podstrony
                 sprzedawca pisze sam.

                 GRANICA: pola są WYPEŁNIONE podpowiedziami z profilu, ale zmiana
                 ich tutaj NIE zapisuje się do sklepu — trafia wyłącznie do
                 regulaminu. Adres rejestrowy, zwrotów i kontaktowy bywają trzema
                 różnymi rzeczami i sprzedawca ma prawo je rozróżnić. --}}
            @if ($page->exists && $page->is_system)
                @php($kreator = session('terms_wizard'))
                <div class="rounded-3xl border border-amber-200 bg-amber-50 p-6">
                    @if ($kreator || $errors->any())
                        @php($w = fn (string $pole) => old($pole, $kreator[$pole] ?? ($page->terms_answers[$pole] ?? '')))
                        <h2 class="font-semibold text-stone-900">Wzór regulaminu</h2>

                        @unless (\App\Support\SellerTerms::holdsPlaceholder($page->content))
                            <p class="mt-2 rounded-2xl bg-white px-4 py-3 text-sm text-amber-900">
                                W edytorze jest już Twój tekst — wzór go zastąpi. <span class="font-medium">Nic nie zostanie zapisane</span>,
                                dopóki nie klikniesz „Zapisz zmiany".
                            </p>
                        @endunless

                        <p class="mt-3 text-sm leading-relaxed text-stone-600">
                            Pola wypełniliśmy tym, co wiemy o Twoim sklepie. Popraw, jeśli w regulaminie ma być co innego —
                            <span class="font-medium text-stone-700">te zmiany trafią tylko do regulaminu</span>, a danych sklepu
                            (stopka, faktury) nie ruszą.
                        </p>

                        <form method="POST" action="{{ route('seller.pages.terms.insert', $page) }}" class="mt-4 space-y-4" novalidate data-validate>
                            @csrf

                            @foreach ([
                                ['seller_name', 'Kto prowadzi sklep', 'Nazwa firmy albo imię i nazwisko.', true],
                                ['nip', 'NIP', 'Zostaw puste przy działalności nierejestrowanej.', false],
                                ['address', 'Adres', 'Wchodzi do §1 i do formularza odstąpienia.', true],
                                ['email', 'E-mail', 'Kanał reklamacji i odstąpienia od umowy.', true],
                                ['phone', 'Telefon', 'Nieobowiązkowy.', false],
                                ['return_address', 'Adres do zwrotów', 'Tylko jeśli inny niż powyżej.', false],
                            ] as [$pole, $etykieta, $podpowiedz, $wymagane])
                                <div>
                                    <label for="t-{{ $pole }}" class="block text-sm font-medium text-stone-700">{{ $etykieta }}</label>
                                    <input id="t-{{ $pole }}" name="{{ $pole }}" type="text" value="{{ $w($pole) }}"
                                        @if ($wymagane) required data-msg-required="To pole wchodzi wprost do regulaminu — bez niego dokument miałby lukę." @endif
                                        class="mt-1 block w-full rounded-2xl border border-amber-200 bg-white px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                    <p class="mt-1 text-xs text-stone-500">{{ $podpowiedz }}</p>
                                    @error($pole)
                                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach

                            <div>
                                <label for="t-shipping_days" class="block text-sm font-medium text-stone-700">Wysyłka w ciągu (dni roboczych)</label>
                                <input id="t-shipping_days" name="shipping_days" type="number" min="1" max="60" required
                                    value="{{ $w('shipping_days') }}"
                                    data-msg-required="Podaj, w ile dni roboczych wysyłasz zamówienia — to obowiązek informacyjny."
                                    class="mt-1 block w-full rounded-2xl border border-amber-200 bg-white px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @error('shipping_days')
                                    <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="t-withdrawal_exclusions" class="block text-sm font-medium text-stone-700">Towary bez prawa zwrotu</label>
                                <textarea id="t-withdrawal_exclusions" name="withdrawal_exclusions" rows="2"
                                    placeholder="np. torty na zamówienie, produkty personalizowane"
                                    class="mt-1 block w-full rounded-2xl border border-amber-200 bg-white px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-500/15">{{ $w('withdrawal_exclusions') }}</textarea>
                                <p class="mt-1 text-xs text-stone-500">Zostaw puste, jeśli nie masz takich towarów — ustawowe wyjątki i tak zostaną opisane.</p>
                                @error('withdrawal_exclusions')
                                    <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            @if ($page->shop->availableDeliveryMethods() === [] || $page->shop->availablePaymentMethods() === [])
                                {{-- Nie blokujemy — ale mówimy prawdę: bez metod w kasie regulamin
                                     nie ma czego wyliczyć i zostaną zdania ogólne. --}}
                                <p class="rounded-2xl bg-white px-4 py-3 text-xs leading-relaxed text-stone-600">
                                    Twój sklep nie ma jeszcze włączonej dostawy albo płatności, więc te paragrafy wyjdą ogólnie.
                                    Możesz je uzupełnić w <a href="{{ route('seller.settings.edit') }}" class="font-medium text-stone-800 underline decoration-amber-300 underline-offset-2">Ustawieniach sklepu</a> i wstawić wzór ponownie.
                                </p>
                            @endif

                            <div class="flex flex-wrap gap-2">
                                <button type="submit"
                                    class="inline-flex items-center rounded-full bg-amber-600 px-4 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-amber-700">
                                    Wstaw wzór do edytora
                                </button>
                                <a href="{{ route('seller.pages.edit', $page) }}"
                                    class="inline-flex items-center rounded-full border border-stone-200 bg-white px-4 py-1.5 text-sm font-medium text-stone-600 transition hover:bg-stone-50">
                                    Nie, wróć
                                </a>
                            </div>
                        </form>
                    @else
                        <h2 class="font-semibold text-stone-900">Nie masz regulaminu?</h2>
                        <p class="mt-2 text-sm leading-relaxed text-stone-600">
                            Przygotujemy wzór wypełniony danymi Twojego sklepu — adresem, sposobami dostawy i płatności.
                            Zapytamy tylko o to, czego nie wiemy.
                        </p>
                        <form method="POST" action="{{ route('seller.pages.terms', $page) }}" class="mt-4">
                            @csrf
                            <button type="submit"
                                class="inline-flex rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                                Wstaw wzór regulaminu
                            </button>
                        </form>
                    @endif

                    <p class="mt-3 text-xs leading-relaxed text-stone-500">
                        To wzór do uzupełnienia i sprawdzenia, a nie gotowy dokument. Publikujesz go jako własny regulamin i odpowiadasz za jego treść.
                    </p>
                </div>
            @endif

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Wskazówki</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">✍️</span>
                        <span>Pisz prosto i konkretnie — klient szuka odpowiedzi, nie eseju.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">✨</span>
                        <span>Napisz szkic i użyj <span class="font-medium text-stone-700">Popraw przez AI</span> — poprawi styl i interpunkcję.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🔗</span>
                        <span>Adres strony powstaje z tytułu automatycznie — nie musisz się nim zajmować.</span>
                    </li>
                    {{-- Stron systemowych nie da się wyróżnić, więc nie kuśmy ich tą wskazówką. --}}
                    @unless ($page->isLocked())
                        <li class="flex gap-3">
                            <span class="mt-0.5 shrink-0 text-amber-500">⭐</span>
                            <span>Stronę, która opowiada o Tobie — wywiad, spotkanie autorskie, słowo o sobie — <span class="font-medium text-stone-700">wyróżnij na stronie głównej</span>. Zajawka stanie pod ofertą.</span>
                        </li>
                    @endunless
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
