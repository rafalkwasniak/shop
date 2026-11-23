<x-layouts.panel title="Integracje">
    <x-slot:heading>Integracje</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: formularz --}}
        <div class="lg:col-span-8">
            <form method="POST" action="{{ route('seller.integrations.update') }}" class="space-y-6" novalidate data-validate>
                @csrf

                @php($hasAnyIntegration = $shop->entitlement('online_payments') || $shop->entitlement('invoices') || $shop->entitlement('ga_analytics'))

                @unless ($hasAnyIntegration)
                    {{-- Bramkowane są trzy osobne uprawnienia; do zachęty bierzemy
                         `online_payments`, bo to ta integracja, po którą sprzedawcy
                         tu przychodzą. --}}
                    <x-seller.locked-feature feature="online_payments" icon="🔌" title="Integracje" :shop="$shop">
                        Płatności online, faktury i Google Analytics podłączysz na własne konta — pieniądze i dane idą
                        wprost do Ciebie, Kramio nie jest stroną transakcji.
                    </x-seller.locked-feature>
                @endunless

                {{-- Płatności online (Paynow / mBank) — najważniejsza integracja, na
                     samej górze, ale TYLKO gdy pakiet daje `online_payments` (Stragan+).
                     Klucze sprzedawcy trzymamy zaszyfrowane w bazie — pieniądze płyną
                     klient → Paynow → sprzedawca, wprost. --}}
                @if ($shop->entitlement('online_payments'))
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <div class="flex items-start gap-4">
                        <span class="mt-0.5 shrink-0 text-2xl">💳</span>
                        <div class="flex-1">
                            <h2 class="font-semibold text-stone-900">Płatności online (Paynow)</h2>
                            <p class="mt-1 text-sm text-stone-500">
                                Przyjmuj płatności BLIK, kartą i szybkim przelewem wprost w kasie sklepu. Potrzebujesz konta w
                                <a href="https://www.paynow.pl" target="_blank" rel="noopener" class="font-medium text-stone-600 underline decoration-amber-300 underline-offset-2">Paynow</a>
                                (bramka mBanku) oraz dwóch kluczy z jego panelu. Pieniądze trafiają prosto na Twoje konto.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-5">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="paynow_api_key" class="block text-sm font-medium text-stone-700">Klucz dostępu do API</label>
                                <input type="text" id="paynow_api_key" name="paynow_api_key"
                                    value="{{ old('paynow_api_key', $paynowApiKey) }}"
                                    placeholder="np. 14d59738-4b18-4c83-…"
                                    autocomplete="off" spellcheck="false"
                                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 font-mono text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @error('paynow_api_key')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-stone-400">Z panelu Paynow → <span class="text-stone-500">Ustawienia → Klucze API</span>. Wyczyść to pole, aby odłączyć płatności.</p>
                            </div>

                            <div>
                                <label for="paynow_signature_key" class="block text-sm font-medium text-stone-700">Klucz obliczania podpisu</label>
                                <input type="password" id="paynow_signature_key" name="paynow_signature_key"
                                    value=""
                                    placeholder="{{ $paynowConfigured ? '•••••••• (zapisany)' : 'Wklej klucz podpisu z Paynow' }}"
                                    autocomplete="off" spellcheck="false"
                                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 font-mono text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @error('paynow_signature_key')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-stone-400">
                                    Sekret — podpisuje płatności i weryfikuje powiadomienia.
                                    @if ($paynowConfigured)
                                        Klucz jest zapisany — zostaw pole puste, aby go nie zmieniać.
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 rounded-2xl border border-stone-200 bg-white/60 p-4">
                            {{-- hidden = wartość bazowa (odznaczone = produkcja); checkbox nadpisuje na sandbox --}}
                            <input type="hidden" name="paynow_sandbox" value="0">
                            <input type="checkbox" id="paynow_sandbox" name="paynow_sandbox" value="1"
                                @checked(old('paynow_sandbox', $paynowEnvironment === 'sandbox'))
                                class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20">
                            <label for="paynow_sandbox" class="flex-1 cursor-pointer">
                                <span class="block text-sm font-medium text-stone-800">Włącz środowisko testowe (sandbox)</span>
                                <span class="mt-0.5 block text-xs text-stone-400">Zaznacz do testów kluczami sandbox. Odznacz, gdy podłączasz klucze produkcyjne i chcesz przyjmować prawdziwe płatności.</span>
                            </label>
                        </div>

                        @if ($paynowConfigured)
                            <div class="flex items-start gap-3 rounded-2xl border border-stone-200 bg-white/60 p-4 text-sm">
                                <span class="mt-0.5 shrink-0 font-semibold {{ $paynowEnabled ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ $paynowEnabled ? '✓' : '✕' }}
                                </span>
                                <p class="text-stone-600">
                                    @if ($paynowEnabled)
                                        Płatności online są połączone i <span class="font-medium text-stone-800">aktywne</span> ({{ $paynowEnvironment === 'production' ? 'produkcja' : 'sandbox' }}) — klient może zapłacić w kasie.
                                    @else
                                        Klucze są zapisane, ale integracja jest <span class="font-medium text-stone-800">wyłączona</span>. Włącz ją w
                                        <a href="{{ route('seller.settings.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">Ustawieniach</a>, aby przyjmować płatności.
                                    @endif
                                </p>
                            </div>
                        @endif

                        {{-- Adres powiadomień: to sprzedawca musi wkleić w panelu Paynow.
                             Nie da się tego ustawić z naszej strony — dlatego pokazujemy
                             gotowy link do skopiowania, a nie „załatwiamy to za Ciebie". --}}
                        <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4">
                            <p class="text-sm font-medium text-stone-800">Adres powiadomień (webhook)</p>
                            <p class="mt-1 text-xs text-stone-500">
                                Wklej ten adres w panelu Paynow → <span class="text-stone-600">Ustawienia → Powiadomienia (URL)</span>. To przez niego Paynow informuje sklep o opłaceniu zamówienia — bez tego zamówienia nie oznaczą się jako opłacone. Musisz ustawić go samodzielnie po stronie Paynow.
                            </p>
                            <div class="mt-3 flex items-stretch gap-2">
                                <input type="text" id="paynow_webhook_url" value="{{ $paynowWebhookUrl }}" readonly
                                    onclick="this.select()"
                                    class="min-w-0 flex-1 rounded-xl border border-amber-200 bg-white px-3 py-2 font-mono text-xs text-stone-700 shadow-sm">
                                <button type="button"
                                    onclick="navigator.clipboard.writeText(document.getElementById('paynow_webhook_url').value); this.textContent='Skopiowano ✓'; setTimeout(() => this.textContent='Kopiuj', 1500);"
                                    class="shrink-0 rounded-xl border border-amber-300 bg-white px-4 py-2 text-xs font-medium text-amber-800 transition hover:bg-amber-100">Kopiuj</button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Przelewy24 — druga bramka, pod tym samym uprawnieniem. Sklep może
                     mieć obie naraz; kupujący wybiera w kasie. CRC i klucz API to
                     sekrety (pola `password`, nigdy nie wracają do przeglądarki).
                     Adresu powiadomień sprzedawca NIE wkleja — P24 przyjmuje go przy
                     każdej transakcji. Za to P24 wpuszcza API tylko z IP wpisanych w
                     panelu sprzedawcy, więc pokazujemy nasz adres do przeklejenia. --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <div class="flex items-start gap-4">
                        <span class="mt-0.5 shrink-0 text-2xl">💳</span>
                        <div class="flex-1">
                            <h2 class="font-semibold text-stone-900">Płatności online (Przelewy24)</h2>
                            <p class="mt-1 text-sm text-stone-500">
                                Przyjmuj płatności BLIK, kartą i szybkim przelewem przez
                                <a href="https://www.przelewy24.pl" target="_blank" rel="noopener" class="font-medium text-stone-600 underline decoration-amber-300 underline-offset-2">Przelewy24</a>.
                                Potrzebujesz konta w Przelewy24 i danych z zakładki <span class="text-stone-600">Moje dane → Dane API i konfiguracja</span>. Pieniądze trafiają prosto na Twoje konto. Możesz mieć jednocześnie Paynow i Przelewy24 — klient wybierze w kasie.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-5">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="przelewy24_merchant_id" class="block text-sm font-medium text-stone-700">ID sprzedawcy (Merchant ID)</label>
                                <input type="text" id="przelewy24_merchant_id" name="przelewy24_merchant_id" inputmode="numeric"
                                    value="{{ old('przelewy24_merchant_id', $przelewy24MerchantId) }}"
                                    placeholder="np. 123456"
                                    autocomplete="off" spellcheck="false"
                                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 font-mono text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @error('przelewy24_merchant_id')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-stone-400">W panelu Przelewy24 to pole <span class="text-stone-500">„Dane konta”</span> (sam numer). Wyczyść to pole, aby odłączyć Przelewy24.</p>
                            </div>

                            <div>
                                <label for="przelewy24_pos_id" class="block text-sm font-medium text-stone-700">ID punktu (POS ID)</label>
                                <input type="text" id="przelewy24_pos_id" name="przelewy24_pos_id" inputmode="numeric"
                                    value="{{ old('przelewy24_pos_id', $przelewy24PosId) }}"
                                    placeholder="taki sam jak ID sprzedawcy"
                                    autocomplete="off" spellcheck="false"
                                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 font-mono text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @error('przelewy24_pos_id')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-stone-400">Zwykle taki sam jak ID sprzedawcy — wtedy zostaw puste.</p>
                            </div>

                            <div>
                                <label for="przelewy24_crc_key" class="block text-sm font-medium text-stone-700">Klucz CRC</label>
                                <input type="password" id="przelewy24_crc_key" name="przelewy24_crc_key"
                                    value=""
                                    placeholder="{{ $przelewy24Configured ? '•••••••• (zapisany)' : 'Wklej klucz CRC z Przelewy24' }}"
                                    autocomplete="off" spellcheck="false"
                                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 font-mono text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @error('przelewy24_crc_key')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-stone-400">
                                    Sekret — w panelu Przelewy24 „Klucz do CRC”. Podpisuje płatności i weryfikuje powiadomienia.
                                    @if ($przelewy24Configured)
                                        Klucz jest zapisany — zostaw pole puste, aby go nie zmieniać.
                                    @endif
                                </p>
                            </div>

                            <div>
                                <label for="przelewy24_api_key" class="block text-sm font-medium text-stone-700">Klucz API (klucz do raportów)</label>
                                <input type="password" id="przelewy24_api_key" name="przelewy24_api_key"
                                    value=""
                                    placeholder="{{ $przelewy24Configured ? '•••••••• (zapisany)' : 'Wklej klucz do raportów z Przelewy24' }}"
                                    autocomplete="off" spellcheck="false"
                                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 font-mono text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @error('przelewy24_api_key')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-stone-400">
                                    Sekret — w panelu Przelewy24 nazywa się „Klucz do raportów”.
                                    @if ($przelewy24Configured)
                                        Klucz jest zapisany — zostaw pole puste, aby go nie zmieniać.
                                    @endif
                                </p>
                            </div>
                        </div>

                        <p class="text-xs text-stone-400">„Klucz do zamówień” z panelu Przelewy24 nie jest potrzebny — nie wpisuj go nigdzie. Po zapisaniu sprawdzimy połączenie z Przelewy24.</p>

                        <div class="flex items-start gap-3 rounded-2xl border border-stone-200 bg-white/60 p-4">
                            {{-- hidden = wartość bazowa (odznaczone = produkcja); checkbox nadpisuje na sandbox --}}
                            <input type="hidden" name="przelewy24_sandbox" value="0">
                            <input type="checkbox" id="przelewy24_sandbox" name="przelewy24_sandbox" value="1"
                                @checked(old('przelewy24_sandbox', $przelewy24Environment === 'sandbox'))
                                class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20">
                            <label for="przelewy24_sandbox" class="flex-1 cursor-pointer">
                                <span class="block text-sm font-medium text-stone-800">Włącz środowisko testowe (sandbox)</span>
                                <span class="mt-0.5 block text-xs text-stone-400">Zaznacz do testów danymi z konta sandbox Przelewy24. Odznacz, gdy podłączasz dane produkcyjne i chcesz przyjmować prawdziwe płatności.</span>
                            </label>
                        </div>

                        @if ($przelewy24Configured)
                            <div class="flex items-start gap-3 rounded-2xl border border-stone-200 bg-white/60 p-4 text-sm">
                                <span class="mt-0.5 shrink-0 font-semibold {{ $przelewy24Enabled ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ $przelewy24Enabled ? '✓' : '✕' }}
                                </span>
                                <p class="text-stone-600">
                                    @if ($przelewy24Enabled)
                                        Przelewy24 są połączone i <span class="font-medium text-stone-800">aktywne</span> ({{ $przelewy24Environment === 'production' ? 'produkcja' : 'sandbox' }}).
                                    @else
                                        Dane są zapisane, ale Przelewy24 są <span class="font-medium text-stone-800">wyłączone</span>. Włącz je w
                                        <a href="{{ route('seller.settings.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">Ustawieniach</a>, aby przyjmować płatności.
                                    @endif
                                </p>
                            </div>
                        @endif

                        {{-- Adres IP serwera: bez wpisu w panelu P24 każde wywołanie API
                             się odbije. Pokazujemy tylko, gdy serwer zna swój adres. --}}
                        @if (filled($serverIp))
                            <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4">
                                <p class="text-sm font-medium text-stone-800">Adres IP do wpisania w Przelewy24</p>
                                <p class="mt-1 text-xs text-stone-500">
                                    Wpisz ten adres w panelu Przelewy24 → <span class="text-stone-600">Moje dane → Dane API i konfiguracja → Adres IP</span>. Przelewy24 przyjmuje połączenia tylko z adresów z tej listy — bez wpisu płatności nie ruszą. Adresu powiadomień nie musisz nigdzie wklejać, przekazujemy go sami.
                                </p>
                                <div class="mt-3 flex items-stretch gap-2">
                                    <input type="text" id="przelewy24_server_ip" value="{{ $serverIp }}" readonly
                                        onclick="this.select()"
                                        class="min-w-0 flex-1 rounded-xl border border-amber-200 bg-white px-3 py-2 font-mono text-xs text-stone-700 shadow-sm">
                                    <button type="button"
                                        onclick="navigator.clipboard.writeText(document.getElementById('przelewy24_server_ip').value); this.textContent='Skopiowano ✓'; setTimeout(() => this.textContent='Kopiuj', 1500);"
                                        class="shrink-0 rounded-xl border border-amber-300 bg-white px-4 py-2 text-xs font-medium text-amber-800 transition hover:bg-amber-100">Kopiuj</button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Nadawanie przesyłek InPost (ShipX) — etykieta prosto z zamówienia.
                     Bramkowane tym samym uprawnieniem co płatna wysyłka.

                     UWAGA: token ShipX UMIE NADAWAĆ PACZKI na koszt sprzedawcy, więc
                     jest sekretem — pole `password`, nigdy nie wraca do przeglądarki
                     i nigdy nie trafia do HTML storefrontu. Mapa paczkomatów w kasie
                     działa na OSOBNYM tokenie platformy (tylko odczyt punktów). --}}
                @if ($shop->entitlement('courier_shipping'))
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <div class="flex items-start gap-4">
                        <span class="mt-0.5 shrink-0 text-2xl">📦</span>
                        <div class="flex-1">
                            <h2 class="font-semibold text-stone-900">Nadawanie przesyłek InPost</h2>
                            <p class="mt-1 text-sm text-stone-500">
                                Nadawaj paczki jednym kliknięciem z karty zamówienia i pobieraj gotową etykietę do druku. To połączenie odblokowuje też <span class="font-medium text-stone-600">dostawę za pobraniem</span> — bez niego nie ma kto zainkasować pieniędzy od klienta. Potrzebujesz konta firmowego w
                                <a href="https://inpost.pl" target="_blank" rel="noopener" class="font-medium text-stone-600 underline decoration-amber-300 underline-offset-2">InPost</a>
                                oraz dwóch wartości z jego panelu. Za przesyłki płacisz InPostowi ze swojego konta — Kramio niczego nie pośredniczy.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-5">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="shipx_token" class="block text-sm font-medium text-stone-700">Token ShipX</label>
                                <input type="password" id="shipx_token" name="shipx_token"
                                    value=""
                                    placeholder="{{ $shipxConfigured ? '•••••••• (zapisany)' : 'Wklej token ShipX z panelu InPost' }}"
                                    autocomplete="off" spellcheck="false"
                                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 font-mono text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @error('shipx_token')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-stone-400">
                                    @if ($shipxConfigured)
                                        Token jest zapisany. Zostaw pole puste, aby go nie zmieniać.
                                    @else
                                        Panel InPost → Moje konto → API.
                                    @endif
                                </p>
                            </div>

                            <div>
                                <label for="shipx_organization_id" class="block text-sm font-medium text-stone-700">Organization ID</label>
                                <input type="text" id="shipx_organization_id" name="shipx_organization_id"
                                    value="{{ old('shipx_organization_id', $shipxOrganizationId) }}"
                                    placeholder="np. 203242" inputmode="numeric"
                                    autocomplete="off" spellcheck="false"
                                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 font-mono text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @error('shipx_organization_id')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-stone-400">Numer obok tokenu, w tym samym miejscu panelu. Wyczyszczenie tego pola rozłącza konto.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 rounded-2xl border border-stone-200 bg-white/60 p-4">
                            <input type="hidden" name="shipx_sandbox" value="0">
                            <input type="checkbox" id="shipx_sandbox" name="shipx_sandbox" value="1"
                                @checked(old('shipx_sandbox', $shipxEnvironment === 'sandbox'))
                                class="mt-0.5 h-4 w-4 shrink-0 rounded border-stone-300 text-amber-500 focus:ring-amber-400/30">
                            <label for="shipx_sandbox" class="flex-1 cursor-pointer">
                                <span class="block text-sm font-medium text-stone-800">Konto testowe (sandbox)</span>
                                <span class="mt-0.5 block text-xs text-stone-500">
                                    Zaznaczone — nadania są próbne i nic nie kosztują (wymaga osobnego konta na sandbox-manager.paczkomaty.pl).
                                    Odznaczone — <span class="font-medium text-stone-700">nadajesz prawdziwe paczki i płacisz za nie</span>.
                                </span>
                            </label>
                        </div>

                        @if ($shipxConfigured)
                            <div class="flex items-start gap-2 rounded-2xl border border-stone-200 bg-white/60 p-4 text-sm">
                                <span class="mt-0.5 shrink-0 font-semibold {{ $shipxEnabled ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ $shipxEnabled ? '✓' : '✕' }}
                                </span>
                                <p class="text-stone-500">
                                    @if ($shipxEnabled)
                                        Nadawanie przesyłek jest połączone i <span class="font-medium text-stone-800">aktywne</span> ({{ $shipxEnvironment === 'production' ? 'produkcja' : 'sandbox' }}) — na karcie zamówienia z wysyłką pojawi się przycisk nadania.
                                    @else
                                        Dane są zapisane, ale nadawanie jest <span class="font-medium text-stone-800">wyłączone</span>. Włącz je w Ustawieniach sklepu.
                                    @endif
                                </p>
                            </div>
                        @endif

                        {{-- Instrukcja jest tu równie ważna co pola: sprzedawca odbija się
                             od panelu InPostu, nie od naszego formularza (patrz pamięć
                             „plan-shipping" — instrukcja krok po kroku WYMAGANA). --}}
                        <details class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4">
                            <summary class="cursor-pointer text-sm font-medium text-stone-800">Skąd wziąć token i Organization ID?</summary>
                            <ol class="mt-3 space-y-2 text-xs leading-relaxed text-stone-600">
                                <li><span class="font-semibold">1.</span> Załóż konto firmowe w InPost i podpisz umowę (dla kont testowych: <span class="font-mono">sandbox-manager.paczkomaty.pl</span> — to osobna rejestracja).</li>
                                <li><span class="font-semibold">2.</span> W panelu InPost wejdź w <span class="text-stone-800">Moje konto → Dane</span> i uzupełnij dane firmy <em>oraz</em> dane do faktury. Bez kompletu panel nie pozwoli wygenerować tokenu.</li>
                                <li><span class="font-semibold">3.</span> Przejdź na zakładkę <span class="text-stone-800">Moje konto → API</span> i wygeneruj token. Obok znajdziesz Organization ID.</li>
                                <li><span class="font-semibold">4.</span> Zasil konto InPost — nadanie pobiera opłatę z Twojego salda. Bez środków paczka nie zostanie nadana.</li>
                                <li><span class="font-semibold">5.</span> Wklej obie wartości powyżej i zapisz.</li>
                            </ol>
                        </details>
                    </div>
                </div>
                @endif

                {{-- Fakturownia (faktury VAT) — tylko gdy pakiet daje to uprawnienie.
                     Ważniejsze integracje idą na górę; Google Analytics zostaje na dole. --}}
                @if ($shop->entitlement('invoices'))
                    <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                        <div class="flex items-start gap-4">
                            <span class="mt-0.5 shrink-0 text-2xl">🧾</span>
                            <div class="flex-1">
                                <h2 class="font-semibold text-stone-900">Fakturownia</h2>
                                <p class="mt-1 text-sm text-stone-500">
                                    Wystawiaj klientom faktury VAT wprost z karty zamówienia. Potrzebujesz konta w
                                    <a href="https://fakturownia.pl" target="_blank" rel="noopener" class="font-medium text-stone-600 underline decoration-amber-300 underline-offset-2">Fakturowni</a>
                                    oraz tokenu API z tego konta.
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 space-y-5">
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="fakturownia_url" class="block text-sm font-medium text-stone-700">Adres konta</label>
                                    <input type="text" id="fakturownia_url" name="fakturownia_url"
                                        value="{{ old('fakturownia_url', $fakturowniaUrl) }}"
                                        placeholder="https://twojadomena.fakturownia.pl"
                                        autocomplete="off" spellcheck="false"
                                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 font-mono text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                    @error('fakturownia_url')
                                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1.5 text-xs text-stone-400">Adres Twojego konta w Fakturowni. Wyczyść to pole, aby odłączyć integrację.</p>
                                </div>

                                <div>
                                    <label for="fakturownia_token" class="block text-sm font-medium text-stone-700">Token API</label>
                                    <input type="password" id="fakturownia_token" name="fakturownia_token"
                                        value=""
                                        placeholder="{{ $fakturowniaConfigured ? '•••••••• (zapisany)' : 'Wklej token z Fakturowni' }}"
                                        autocomplete="off" spellcheck="false"
                                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 font-mono text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                    @error('fakturownia_token')
                                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1.5 text-xs text-stone-400">
                                        Znajdziesz go w Fakturowni → <span class="text-stone-500">Ustawienia → Ustawienia konta → Integracja / API</span>.
                                        @if ($fakturowniaConfigured)
                                            Token jest zapisany — zostaw pole puste, aby go nie zmieniać.
                                        @endif
                                    </p>
                                </div>
                            </div>

                            @if ($fakturowniaConfigured)
                                <div class="flex items-start gap-3 rounded-2xl border border-stone-200 bg-white/60 p-4 text-sm">
                                    <span class="mt-0.5 shrink-0 font-semibold {{ $fakturowniaEnabled ? 'text-emerald-600' : 'text-rose-600' }}">
                                        {{ $fakturowniaEnabled ? '✓' : '✕' }}
                                    </span>
                                    <p class="text-stone-600">
                                        @if ($fakturowniaEnabled)
                                            Fakturownia jest połączona i <span class="font-medium text-stone-800">aktywna</span> — na karcie zamówienia możesz wystawić fakturę VAT.
                                        @else
                                            Dane są zapisane, ale integracja jest <span class="font-medium text-stone-800">wyłączona</span>. Włącz ją w
                                            <a href="{{ route('seller.settings.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">Ustawieniach</a>, aby wystawiać faktury.
                                        @endif
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Google Analytics — funkcja płatna (ga_analytics, Stragan+). NASZA
                     analityka jest osobna i dla wszystkich; to jest zewnętrzne GA/GTM. --}}
                @if ($shop->entitlement('ga_analytics'))
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <div class="flex items-start gap-4">
                        <span class="mt-0.5 shrink-0 text-2xl">📈</span>
                        <div class="flex-1">
                            <h2 class="font-semibold text-stone-900">Google Analytics</h2>
                            <p class="mt-1 text-sm text-stone-500">Podłącz statystyki ruchu w swoim sklepie. Obsługujemy Google Analytics&nbsp;4 oraz Google Tag Managera.</p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <label for="google_analytics_id" class="block text-sm font-medium text-stone-700">Identyfikator śledzenia</label>
                        <input type="text" id="google_analytics_id" name="google_analytics_id"
                            value="{{ old('google_analytics_id', $googleAnalyticsId) }}"
                            placeholder="G-XXXXXXXXXX"
                            autocomplete="off" spellcheck="false"
                            class="mt-1.5 block w-full max-w-sm rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 font-mono text-sm uppercase tracking-wide shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        @error('google_analytics_id')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1.5 text-xs text-stone-400">
                            GA4: <span class="font-mono">G-XXXXXXXXXX</span> · Tag Manager: <span class="font-mono">GTM-XXXXXXX</span>.
                            Znajdziesz go w panelu Google Analytics → <span class="text-stone-500">Administracja → Strumienie danych</span>. Zostaw puste, aby odłączyć.
                        </p>

                        @if(filled($googleAnalyticsId))
                            <div class="mt-4 flex items-start gap-3 rounded-2xl border border-stone-200 bg-white/60 p-4 text-sm">
                                <span class="mt-0.5 shrink-0 font-semibold {{ $shop->tracksWithGoogleAnalytics() ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ $shop->tracksWithGoogleAnalytics() ? '✓' : '✕' }}
                                </span>
                                <p class="text-stone-600">
                                    @if($shop->tracksWithGoogleAnalytics())
                                        Integracja jest skonfigurowana i <span class="font-medium text-stone-800">aktywna</span> — kod zbiera dane w Twoim sklepie.
                                    @else
                                        Identyfikator zapisany, ale integracja jest <span class="font-medium text-stone-800">wyłączona</span>. Włącz ją w
                                        <a href="{{ route('seller.settings.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">Ustawieniach</a>, aby zaczęła zbierać dane.
                                    @endif
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Google Search Console — CELOWO bez bramki pakietu, jako jedyna
                     karta dostępna w każdym pakiecie. Mapa strony powstaje sama dla
                     wszystkich sklepów, a to jest jedyna droga, żeby sprzedawca mógł
                     potwierdzić Google własność subdomeny: pliku na serwer nie wgra,
                     rekordu DNS w *.kramio.pl nie doda, a weryfikacja przez GA jest
                     płatna od Straganu. --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <div class="flex items-start gap-4">
                        <span class="mt-0.5 shrink-0 text-2xl">🔎</span>
                        <div class="flex-1">
                            <h2 class="font-semibold text-stone-900">Google Search Console</h2>
                            <p class="mt-1 text-sm text-stone-500">
                                Mapa Twojego sklepu powstaje automatycznie i Google znajduje ją sam — <span class="font-medium text-stone-600">nie musisz nic robić</span>.
                                Zgłoś ją tutaj tylko, jeśli chcesz widzieć, co dokładnie zostało zaindeksowane.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <span class="block text-sm font-medium text-stone-700">Adres Twojej mapy strony</span>
                        @if ($shop->isVisible())
                            <input type="text" value="{{ $sitemapUrl }}" readonly onclick="this.select()"
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 font-mono text-sm text-stone-600 shadow-sm">
                        @else
                            <p class="mt-1.5 rounded-2xl border border-dashed border-stone-300 bg-white/40 px-4 py-3 text-sm text-stone-500">
                                Mapa pojawi się, gdy opublikujesz pierwszy produkt — dopóki sklep jest szkicem, nie ma czego zgłaszać.
                            </p>
                        @endif
                    </div>

                    <div class="mt-6">
                        <label for="google_site_verification" class="block text-sm font-medium text-stone-700">Kod weryfikacyjny</label>
                        <input type="text" id="google_site_verification" name="google_site_verification"
                            value="{{ old('google_site_verification', $siteVerification) }}"
                            placeholder="np. 9xT2k_pQ…"
                            autocomplete="off" spellcheck="false"
                            class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 font-mono text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        @error('google_site_verification')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1.5 text-xs text-stone-400">
                            Możesz wkleić cały znacznik <span class="font-mono">&lt;meta name="google-site-verification" …&gt;</span> — sami wyciągniemy z niego kod. Zostaw puste, aby odłączyć.
                        </p>

                        @if (filled($siteVerification))
                            <div class="mt-4 flex items-start gap-3 rounded-2xl border border-stone-200 bg-white/60 p-4 text-sm">
                                <span class="mt-0.5 shrink-0 font-semibold text-emerald-600">✓</span>
                                <p class="text-stone-600">
                                    Kod jest wpisany na stronach Twojego sklepu — wróć do Google i kliknij <span class="font-medium text-stone-800">Zweryfikuj</span>.
                                </p>
                            </div>
                        @endif
                    </div>

                    <ol class="mt-6 space-y-2 border-t border-stone-200/70 pt-5 text-sm text-stone-500">
                        <li><span class="font-medium text-stone-700">1.</span> Wejdź na <span class="font-medium text-stone-600">search.google.com/search-console</span> i dodaj zasób typu „Prefiks adresu URL", wpisując adres swojego sklepu.</li>
                        <li><span class="font-medium text-stone-700">2.</span> Wybierz weryfikację przez <span class="font-medium text-stone-600">znacznik HTML</span>, skopiuj kod i wklej go w polu powyżej, a potem zapisz.</li>
                        <li><span class="font-medium text-stone-700">3.</span> Wróć do Google, kliknij „Zweryfikuj", a następnie w sekcji „Mapy witryny" wklej adres mapy z góry tej karty.</li>
                    </ol>
                </div>

                {{-- Przycisk bez warunku: karta Search Console jest w każdym pakiecie,
                     więc zawsze jest co zapisać. --}}
                <div class="flex justify-end">
                    <button type="submit"
                        class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                        Zapisz integracje
                    </button>
                </div>
            </form>
        </div>

        {{-- Kolumna pomocnicza: wskazówki --}}
        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to działa</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🔧</span>
                        <span>Tutaj <span class="text-stone-700">konfigurujesz</span> usługi (wpisujesz identyfikatory), a <span class="text-stone-700">włączasz i wyłączasz</span> je w <a href="{{ route('seller.settings.edit') }}" class="font-medium text-stone-700 underline decoration-amber-300 underline-offset-2">Ustawieniach</a>.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">📈</span>
                        <span>Po podaniu identyfikatora integracja włącza się od razu — statystyki zaczynają spływać do Twojego konta Google.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🔒</span>
                        <span>Kod śledzenia działa tylko na Twoim sklepie. Kolejne integracje (m.in. wysyłki) dojdą tutaj z czasem.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
