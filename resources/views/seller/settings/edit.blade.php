<x-layouts.panel title="Ustawienia">
    <x-slot:heading>Ustawienia sklepu</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: formularz --}}
        <div class="lg:col-span-8">
            <form id="ustawienia" method="POST" action="{{ route('seller.settings.update') }}" class="space-y-6" novalidate data-validate>
                @csrf

                {{-- Sprzedaż --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Sprzedaż</h2>
                    <p class="mt-1 text-sm text-stone-500">Domyślne wartości podpowiadane przy dodawaniu produktów.</p>

                    <div class="mt-6 grid grid-cols-12 gap-5">
                        <div class="col-span-6 sm:col-span-3">
                            <label for="default_vat_rate" class="block text-sm font-medium text-stone-700">Domyślna stawka VAT</label>
                            @php($selectedVat = old('default_vat_rate', $shop->default_vat_rate?->value ?? '23'))
                            <select id="default_vat_rate" name="default_vat_rate" required
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @foreach ($vatRates as $rate)
                                    <option value="{{ $rate->value }}" @selected($selectedVat === $rate->value)>{{ $rate->label() }}</option>
                                @endforeach
                            </select>
                            @error('default_vat_rate')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1.5 text-xs text-stone-400">Wstępnie ustawiana przy każdym nowym produkcie — przy produkcie nadal możesz ją zmienić.</p>
                        </div>

                        <div class="col-span-6 sm:col-span-4">
                            <label for="default_sale_unit" class="block text-sm font-medium text-stone-700">Domyślna jednostka sprzedaży</label>
                            @php($selectedUnit = old('default_sale_unit', $shop->default_sale_unit?->value ?? 'piece'))
                            <select id="default_sale_unit" name="default_sale_unit" required
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @foreach ($saleUnits as $unit)
                                    <option value="{{ $unit->value }}" @selected($selectedUnit === $unit->value)>{{ $unit->label() }}</option>
                                @endforeach
                            </select>
                            @error('default_sale_unit')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1.5 text-xs text-stone-400">Sprzedajesz głównie na wagę (warzywa, wędliny)? Ustaw „kg" — przy każdym produkcie i tak możesz zmienić.</p>
                        </div>
                    </div>
                </div>

                {{-- Dostawa --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Dostawa</h2>
                    <p class="mt-1 text-sm text-stone-500">Sposoby dostawy widoczne dla klientów w kasie.</p>

                    <div class="mt-6 space-y-4">
                        @php($hasAddress = $shop->addressComplete())
                        <div class="flex items-start gap-4 rounded-2xl border border-stone-200 bg-white/60 p-5 sm:p-6 {{ $hasAddress ? '' : 'opacity-60' }}">
                            {{-- hidden = wartość bazowa; bez adresu checkbox jest disabled i nic nie wysyła --}}
                            <input type="hidden" name="pickup_enabled" value="{{ $hasAddress ? '0' : ($shop->pickup_enabled ? '1' : '0') }}">
                            <input type="checkbox" id="pickup_enabled" name="pickup_enabled" value="1"
                                @checked(old('pickup_enabled', $shop->pickup_enabled)) @disabled(! $hasAddress)
                                class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20 disabled:cursor-not-allowed">
                            <label for="pickup_enabled" class="flex-1 {{ $hasAddress ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                                <span class="block text-sm font-medium text-stone-800">Odbiór osobisty</span>
                                <span class="mt-0.5 block text-sm text-stone-500">Klient odbiera zamówienie pod adresem Twojego sklepu. Bez kosztów dostawy.</span>
                                @unless($hasAddress)
                                    @if($shop->pickup_enabled)
                                        <span class="mt-1.5 block text-xs text-amber-700">Odbiór jest włączony, ale zacznie działać w kasie, gdy uzupełnisz adres sklepu w <a href="{{ route('seller.shop.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">Mój sklep</a>.</span>
                                    @else
                                        <span class="mt-1.5 block text-xs text-amber-700">Aby móc włączyć, najpierw uzupełnij adres sklepu w <a href="{{ route('seller.shop.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">Mój sklep</a>.</span>
                                    @endif
                                @endunless
                            </label>
                        </div>

                        {{-- Kurier + Paczkomat = wysyłka InPost, funkcja płatna
                             (courier_shipping, Stragan+). Kram ma tylko odbiór osobisty. --}}
                        @if ($shop->entitlement('courier_shipping'))
                        {{-- Kurier — Poziom 1: bez integracji, bez mapy. Włącznik + koszt + opcjonalny próg. --}}
                        @php($courierEnabled = old('courier_enabled', $shop->courier_enabled))
                        <div class="rounded-2xl border border-stone-200 bg-white/60 p-5 sm:p-6">
                            <div class="flex items-start gap-4">
                                <input type="hidden" name="courier_enabled" value="0">
                                <input type="checkbox" id="courier_enabled" name="courier_enabled" value="1"
                                    @checked($courierEnabled)
                                    class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20">
                                <label for="courier_enabled" class="flex-1 cursor-pointer">
                                    <span class="block text-sm font-medium text-stone-800">Dostawa kurierem</span>
                                    <span class="mt-0.5 block text-sm text-stone-500">Klient podaje adres, Ty wysyłasz paczkę wybranym przewoźnikiem. Działa od razu — bez zakładania konta u kuriera.</span>
                                    @unless($shop->bankTransferAvailable())
                                        <span class="mt-1.5 block text-xs text-amber-700">Kurier łączy się z płatnością <a href="#" onclick="document.getElementById('bank_transfer_enabled').scrollIntoView({behavior:'smooth',block:'center'});return false;" class="font-medium underline decoration-amber-300 underline-offset-2">przelewem</a> — włącz ją, aby klient mógł opłacić przesyłkę.</span>
                                    @endunless
                                </label>
                            </div>

                            <div class="mt-5 grid grid-cols-12 gap-5">
                                <div class="col-span-6 sm:col-span-4">
                                    <label for="courier_cost" class="block text-sm font-medium text-stone-700">Koszt dostawy</label>
                                    <div class="relative mt-1.5">
                                        <input id="courier_cost" name="courier_cost" type="text" inputmode="decimal" placeholder="0,00"
                                            value="{{ old('courier_cost', $shop->courier_cost) }}"
                                            class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 pr-10 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-400">zł</span>
                                    </div>
                                    @error('courier_cost')
                                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1.5 text-xs text-stone-400">Doliczany do zamówienia. Wpisz 0, jeśli wysyłasz na swój koszt.</p>
                                </div>

                                <div class="col-span-6 sm:col-span-4">
                                    <label for="courier_free_from" class="block text-sm font-medium text-stone-700">Darmowa dostawa od <span class="font-normal text-stone-400">(opcjonalnie)</span></label>
                                    <div class="relative mt-1.5">
                                        <input id="courier_free_from" name="courier_free_from" type="text" inputmode="decimal" placeholder="np. 200"
                                            value="{{ old('courier_free_from', $shop->courier_free_from) }}"
                                            class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 pr-10 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-400">zł</span>
                                    </div>
                                    @error('courier_free_from')
                                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1.5 text-xs text-stone-400">Powyżej tej wartości koszyka dostawa jest gratis. Puste = darmowej dostawy nie ma.</p>
                                </div>
                            </div>
                        </div>

                        {{-- Paczkomat InPost — Poziom 1: bez konta sprzedawcy w InPoście.
                             Bliźniak kuriera. Mapa (geowidget) dojdzie jako nakładka na
                             pole kodu w kasie; do działania metody NIE jest potrzebna. --}}
                        <div class="rounded-2xl border border-stone-200 bg-white/60 p-5 sm:p-6">
                            <div class="flex items-start gap-4">
                                <input type="hidden" name="parcel_locker_enabled" value="0">
                                <input type="checkbox" id="parcel_locker_enabled" name="parcel_locker_enabled" value="1"
                                    @checked(old('parcel_locker_enabled', $shop->parcel_locker_enabled))
                                    class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20">
                                <label for="parcel_locker_enabled" class="flex-1 cursor-pointer">
                                    <span class="block text-sm font-medium text-stone-800">Paczkomat InPost</span>
                                    <span class="mt-0.5 block text-sm text-stone-500">Klient wybiera paczkomat, Ty nadajesz paczkę. Działa od razu — bez zakładania konta w InPoście.</span>
                                    @unless($shop->bankTransferAvailable())
                                        <span class="mt-1.5 block text-xs text-amber-700">Paczkomat łączy się z płatnością <a href="#" onclick="document.getElementById('bank_transfer_enabled').scrollIntoView({behavior:'smooth',block:'center'});return false;" class="font-medium underline decoration-amber-300 underline-offset-2">przelewem</a> — włącz ją, aby klient mógł opłacić przesyłkę.</span>
                                    @endunless
                                </label>
                            </div>

                            <div class="mt-5 grid grid-cols-12 gap-5">
                                <div class="col-span-6 sm:col-span-4">
                                    <label for="parcel_locker_cost" class="block text-sm font-medium text-stone-700">Koszt dostawy</label>
                                    <div class="relative mt-1.5">
                                        <input id="parcel_locker_cost" name="parcel_locker_cost" type="text" inputmode="decimal" placeholder="0,00"
                                            value="{{ old('parcel_locker_cost', $shop->parcel_locker_cost) }}"
                                            class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 pr-10 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-400">zł</span>
                                    </div>
                                    @error('parcel_locker_cost')
                                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1.5 text-xs text-stone-400">Doliczany do zamówienia. Wpisz 0, jeśli wysyłasz na swój koszt.</p>
                                </div>

                                <div class="col-span-6 sm:col-span-4">
                                    <label for="parcel_locker_free_from" class="block text-sm font-medium text-stone-700">Darmowa dostawa od <span class="font-normal text-stone-400">(opcjonalnie)</span></label>
                                    <div class="relative mt-1.5">
                                        <input id="parcel_locker_free_from" name="parcel_locker_free_from" type="text" inputmode="decimal" placeholder="np. 150"
                                            value="{{ old('parcel_locker_free_from', $shop->parcel_locker_free_from) }}"
                                            class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 pr-10 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-400">zł</span>
                                    </div>
                                    @error('parcel_locker_free_from')
                                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1.5 text-xs text-stone-400">Powyżej tej wartości koszyka dostawa jest gratis. Puste = darmowej dostawy nie ma.</p>
                                </div>
                            </div>
                        </div>

                        {{-- Pobraniowe warianty obu wysyłek. Osobne fiszki i osobne ceny,
                             NIEZALEŻNE od metod przedpłaconych: sprzedawca może chcieć np.
                             sam paczkomat i sam paczkomat pobraniowy (decyzja Rafała 17.08).
                             Jedyny dodatkowy warunek: skonfigurowany InPost — to on inkasuje
                             pieniądze i przelewa je sprzedawcy, więc bez integracji ta metoda
                             nie ma jak zadziałać. --}}
                        @php($inpostReady = $shop->shipxEnabled())

                        <div class="rounded-2xl border border-stone-200 bg-white/60 p-5 sm:p-6">
                            <div class="flex items-start gap-4">
                                <input type="hidden" name="courier_cod_enabled" value="0">
                                <input type="checkbox" id="courier_cod_enabled" name="courier_cod_enabled" value="1"
                                    @checked(old('courier_cod_enabled', $shop->courier_cod_enabled))
                                    @disabled(! $inpostReady)
                                    class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20 disabled:cursor-not-allowed">
                                <label for="courier_cod_enabled" class="flex-1 {{ $inpostReady ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                                    <span class="block text-sm font-medium text-stone-800">Dostawa kurierem za pobraniem</span>
                                    <span class="mt-0.5 block text-sm text-stone-500">Klient płaci kurierowi przy odbiorze — gotówką lub kartą. Pieniądze zbiera InPost i przelewa je na Twoje konto.</span>
                                    @unless($inpostReady)
                                        <span class="mt-1.5 block text-xs text-amber-700">Pobranie wymaga podłączonego konta InPost — ustawisz je w <a href="{{ route('seller.integrations.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">Integracjach</a>.</span>
                                    @endunless
                                </label>
                            </div>

                            <div class="mt-5 grid grid-cols-12 gap-5">
                                <div class="col-span-6 sm:col-span-4">
                                    <label for="courier_cod_cost" class="block text-sm font-medium text-stone-700">Koszt dostawy</label>
                                    <div class="relative mt-1.5">
                                        <input id="courier_cod_cost" name="courier_cod_cost" type="text" inputmode="decimal" placeholder="0,00"
                                            value="{{ old('courier_cod_cost', $shop->courier_cod_cost) }}"
                                            class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 pr-10 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-400">zł</span>
                                    </div>
                                    @error('courier_cod_cost')
                                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1.5 text-xs text-stone-400">Cena własna, niezależna od zwykłego kuriera — InPost liczy za obsługę pobrania osobno.</p>
                                </div>

                                <div class="col-span-6 sm:col-span-4">
                                    <label for="courier_cod_free_from" class="block text-sm font-medium text-stone-700">Darmowa dostawa od <span class="font-normal text-stone-400">(opcjonalnie)</span></label>
                                    <div class="relative mt-1.5">
                                        <input id="courier_cod_free_from" name="courier_cod_free_from" type="text" inputmode="decimal" placeholder="np. 250"
                                            value="{{ old('courier_cod_free_from', $shop->courier_cod_free_from) }}"
                                            class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 pr-10 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-400">zł</span>
                                    </div>
                                    @error('courier_cod_free_from')
                                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1.5 text-xs text-stone-400">Powyżej tej wartości koszyka dostawa jest gratis. Puste = darmowej dostawy nie ma.</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-stone-200 bg-white/60 p-5 sm:p-6">
                            <div class="flex items-start gap-4">
                                <input type="hidden" name="parcel_locker_cod_enabled" value="0">
                                <input type="checkbox" id="parcel_locker_cod_enabled" name="parcel_locker_cod_enabled" value="1"
                                    @checked(old('parcel_locker_cod_enabled', $shop->parcel_locker_cod_enabled))
                                    @disabled(! $inpostReady)
                                    class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20 disabled:cursor-not-allowed">
                                <label for="parcel_locker_cod_enabled" class="flex-1 {{ $inpostReady ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                                    <span class="block text-sm font-medium text-stone-800">Paczkomat InPost za pobraniem</span>
                                    {{-- Paczkomat NIE przyjmuje gotówki (tylko oddział InPostu, gdy paczka
                                         tam trafi po 48 h). Sprzedawca musi to wiedzieć, zanim obieca
                                         klientowi „płatność gotówką przy odbiorze". --}}
                                    <span class="mt-0.5 block text-sm text-stone-500">Klient płaci przy skrytce — BLIK, kartą lub w aplikacji InPost. Gotówki paczkomat nie przyjmie.</span>
                                    @unless($inpostReady)
                                        <span class="mt-1.5 block text-xs text-amber-700">Pobranie wymaga podłączonego konta InPost — ustawisz je w <a href="{{ route('seller.integrations.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">Integracjach</a>.</span>
                                    @endunless
                                </label>
                            </div>

                            <div class="mt-5 grid grid-cols-12 gap-5">
                                <div class="col-span-6 sm:col-span-4">
                                    <label for="parcel_locker_cod_cost" class="block text-sm font-medium text-stone-700">Koszt dostawy</label>
                                    <div class="relative mt-1.5">
                                        <input id="parcel_locker_cod_cost" name="parcel_locker_cod_cost" type="text" inputmode="decimal" placeholder="0,00"
                                            value="{{ old('parcel_locker_cod_cost', $shop->parcel_locker_cod_cost) }}"
                                            class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 pr-10 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-400">zł</span>
                                    </div>
                                    @error('parcel_locker_cod_cost')
                                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1.5 text-xs text-stone-400">Cena własna, niezależna od zwykłego paczkomatu — InPost liczy za obsługę pobrania osobno.</p>
                                </div>

                                <div class="col-span-6 sm:col-span-4">
                                    <label for="parcel_locker_cod_free_from" class="block text-sm font-medium text-stone-700">Darmowa dostawa od <span class="font-normal text-stone-400">(opcjonalnie)</span></label>
                                    <div class="relative mt-1.5">
                                        <input id="parcel_locker_cod_free_from" name="parcel_locker_cod_free_from" type="text" inputmode="decimal" placeholder="np. 200"
                                            value="{{ old('parcel_locker_cod_free_from', $shop->parcel_locker_cod_free_from) }}"
                                            class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 pr-10 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-400">zł</span>
                                    </div>
                                    @error('parcel_locker_cod_free_from')
                                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1.5 text-xs text-stone-400">Powyżej tej wartości koszyka dostawa jest gratis. Puste = darmowej dostawy nie ma.</p>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Płatności --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Płatności</h2>
                    <p class="mt-1 text-sm text-stone-500">Metody płatności widoczne dla klientów w kasie.</p>

                    <div class="mt-6 space-y-4">
                        @php($hasAccount = filled($shop->bank_account_number))
                        <div class="flex items-start gap-4 rounded-2xl border border-stone-200 bg-white/60 p-5 sm:p-6 {{ $hasAccount ? '' : 'opacity-60' }}">
                            {{-- hidden = wartość bazowa; przy braku numeru zachowujemy stan (checkbox jest disabled i nic nie wysyła) --}}
                            <input type="hidden" name="bank_transfer_enabled" value="{{ $hasAccount ? '0' : ($shop->bank_transfer_enabled ? '1' : '0') }}">
                            <input type="checkbox" id="bank_transfer_enabled" name="bank_transfer_enabled" value="1"
                                @checked(old('bank_transfer_enabled', $shop->bank_transfer_enabled)) @disabled(! $hasAccount)
                                class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20 disabled:cursor-not-allowed">
                            <label for="bank_transfer_enabled" class="flex-1 {{ $hasAccount ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                                <span class="block text-sm font-medium text-stone-800">Przelew na konto</span>
                                <span class="mt-0.5 block text-sm text-stone-500">Klient otrzymuje Twój numer konta i tytuł przelewu. Bez operatora i prowizji.</span>
                                @unless($hasAccount)
                                    @if($shop->bank_transfer_enabled)
                                        <span class="mt-1.5 block text-xs text-amber-700">Ta metoda jest włączona, ale zacznie działać w kasie dopiero, gdy podasz numer konta w <a href="{{ route('seller.shop.edit') }}#dane-do-przelewu" class="font-medium underline decoration-amber-300 underline-offset-2">Mój sklep</a>.</span>
                                    @else
                                        <span class="mt-1.5 block text-xs text-amber-700">Aby móc włączyć tę metodę, najpierw podaj numer konta w <a href="{{ route('seller.shop.edit') }}#dane-do-przelewu" class="font-medium underline decoration-amber-300 underline-offset-2">Mój sklep</a>.</span>
                                    @endif
                                @endunless
                            </label>
                        </div>

                        {{-- Płatność przy odbiorze — zależna od włączonego odbioru osobistego. --}}
                        @php($pickupReady = $shop->pickupAvailable())
                        <div class="flex items-start gap-4 rounded-2xl border border-stone-200 bg-white/60 p-5 sm:p-6 {{ $pickupReady ? '' : 'opacity-60' }}">
                            <input type="hidden" name="pay_on_pickup_enabled" value="{{ $pickupReady ? '0' : ($shop->pay_on_pickup_enabled ? '1' : '0') }}">
                            <input type="checkbox" id="pay_on_pickup_enabled" name="pay_on_pickup_enabled" value="1"
                                @checked(old('pay_on_pickup_enabled', $shop->pay_on_pickup_enabled)) @disabled(! $pickupReady)
                                class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20 disabled:cursor-not-allowed">
                            <label for="pay_on_pickup_enabled" class="flex-1 {{ $pickupReady ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                                <span class="block text-sm font-medium text-stone-800">Płatność przy odbiorze</span>
                                <span class="mt-0.5 block text-sm text-stone-500">Klient płaci na miejscu przy odbiorze zamówienia. Bez operatora i prowizji.</span>
                                @unless($pickupReady)
                                    <span class="mt-1.5 block text-xs text-amber-700">Ta metoda wymaga włączonego <a href="#" onclick="document.getElementById('pickup_enabled').scrollIntoView({behavior:'smooth',block:'center'});return false;" class="font-medium underline decoration-amber-300 underline-offset-2">odbioru osobistego</a> (z uzupełnionym adresem sklepu).</span>
                                @endunless
                            </label>
                        </div>

                        <p class="text-xs text-stone-400">Płatności online (szybkie przelewy, BLIK, karty) dojdą później jako integracja w wyższych pakietach.</p>
                    </div>
                </div>

                {{-- Integracje (włączniki; konfiguracja w zakładce Integracje) --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Integracje</h2>
                    @php($hasAnyIntegration = $shop->entitlement('online_payments') || $shop->entitlement('invoices') || $shop->entitlement('ga_analytics'))
                    @if ($hasAnyIntegration)
                        <p class="mt-1 text-sm text-stone-500">Włączasz i wyłączasz usługi skonfigurowane w zakładce <a href="{{ route('seller.integrations.edit') }}" class="font-medium text-stone-700 underline decoration-amber-300 underline-offset-2">Integracje</a>.</p>
                    @else
                        <p class="mt-1 text-sm text-stone-500">Płatności online, faktury i Google Analytics dołączają od pakietu <span class="font-medium text-stone-700">Stragan</span>.</p>
                    @endif

                    <div class="mt-6 space-y-4">
                        {{-- Ważniejsze integracje na górze; Google Analytics zawsze na dole.
                             Płatności online tylko gdy pakiet daje `online_payments` (Stragan+). --}}
                        @if ($shop->entitlement('online_payments'))
                        <div class="rounded-2xl border border-stone-200 bg-white/60 p-5 sm:p-6 {{ $paynowConfigured ? '' : 'opacity-60' }}">
                            <div class="flex items-start gap-4">
                                {{-- hidden = wartość bazowa; bez konfiguracji checkbox jest disabled i nic nie wysyła --}}
                                <input type="hidden" name="paynow_enabled" value="{{ $paynowConfigured ? '0' : ($paynowEnabled ? '1' : '0') }}">
                                <input type="checkbox" id="paynow_enabled" name="paynow_enabled" value="1"
                                    @checked(old('paynow_enabled', $paynowEnabled)) @disabled(! $paynowConfigured)
                                    class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20 disabled:cursor-not-allowed">
                                <label for="paynow_enabled" class="flex-1 {{ $paynowConfigured ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                                    <span class="block text-sm font-medium text-stone-800">Płatności online (Paynow)</span>
                                    <span class="mt-0.5 block text-sm text-stone-500">Udostępnia w kasie płatność BLIK, kartą i szybkim przelewem — pieniądze trafiają prosto na Twoje konto Paynow.</span>
                                    @unless($paynowConfigured)
                                        <span class="mt-1.5 block text-xs text-amber-700">Aby móc włączyć, najpierw podaj klucze Paynow w <a href="{{ route('seller.integrations.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">Integracjach</a>.</span>
                                    @endunless
                                </label>
                            </div>

                            {{-- Auto-FV po opłaceniu: decyzja ZALEŻNA od Paynow (po tej płatności
                                 leci FV), więc wcięta pod nim, nie luzem przy Fakturowni — inaczej
                                 sugerowałaby, że i przelew na konto wystawia FV (a nie wystawia).
                                 Tylko sklepy z pakietem faktur; aktywna, gdy WŁĄCZONE są oba:
                                 Paynow i Fakturownia. hidden zachowuje zapisaną wartość, gdy pole
                                 disabled — zapis ustawień jej nie kasuje. --}}
                            @if ($shop->entitlement('invoices'))
                                @php($autoInvoiceReady = $paynowEnabled && $fakturowniaEnabled)
                                <div class="mt-4 border-t border-stone-100 pt-4">
                                    <div class="ml-4 flex items-start gap-3 {{ $autoInvoiceReady ? '' : 'opacity-60' }}">
                                        <input type="hidden" name="paynow_auto_invoice" value="{{ $autoInvoiceReady ? '0' : ($paynowAutoInvoice ? '1' : '0') }}">
                                        <input type="checkbox" id="paynow_auto_invoice" name="paynow_auto_invoice" value="1"
                                            @checked(old('paynow_auto_invoice', $paynowAutoInvoice)) @disabled(! $autoInvoiceReady)
                                            class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20 disabled:cursor-not-allowed">
                                        <label for="paynow_auto_invoice" class="flex-1 {{ $autoInvoiceReady ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                                            <span class="block text-sm font-medium text-stone-800">Wystaw fakturę VAT automatycznie po opłaceniu</span>
                                            <span class="mt-0.5 block text-xs text-stone-400">Gdy klient opłaci zamówienie online przez Paynow, faktura wystawi się sama w Fakturowni — bez klikania.</span>
                                            @unless($autoInvoiceReady)
                                                <span class="mt-1.5 block text-xs text-amber-700">Zadziała, gdy włączone są oba: płatności online (wyżej) oraz Fakturownia.</span>
                                            @endunless
                                        </label>
                                    </div>
                                </div>
                            @endif
                        </div>
                        @endif

                        @if ($shop->entitlement('invoices'))
                            <div class="flex items-start gap-4 rounded-2xl border border-stone-200 bg-white/60 p-5 sm:p-6 {{ $fakturowniaConfigured ? '' : 'opacity-60' }}">
                                {{-- hidden = wartość bazowa; bez konfiguracji checkbox jest disabled i nic nie wysyła --}}
                                <input type="hidden" name="fakturownia_enabled" value="{{ $fakturowniaConfigured ? '0' : ($fakturowniaEnabled ? '1' : '0') }}">
                                <input type="checkbox" id="fakturownia_enabled" name="fakturownia_enabled" value="1"
                                    @checked(old('fakturownia_enabled', $fakturowniaEnabled)) @disabled(! $fakturowniaConfigured)
                                    class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20 disabled:cursor-not-allowed">
                                <label for="fakturownia_enabled" class="flex-1 {{ $fakturowniaConfigured ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                                    <span class="block text-sm font-medium text-stone-800">Fakturownia (faktury VAT)</span>
                                    <span class="mt-0.5 block text-sm text-stone-500">Włącza przycisk „Stwórz fakturę VAT" na karcie zamówienia — faktury wystawiasz przez swoje konto w Fakturowni.</span>
                                    <span class="mt-1.5 block text-xs text-stone-400">Fakturownia to usługa zewnętrzna, która może być płatna — sprawdź limity i warunki swojego konta w <a href="https://fakturownia.pl" target="_blank" rel="noopener" class="font-medium text-stone-500 underline decoration-amber-300 underline-offset-2">Fakturowni</a>.</span>
                                    @unless($fakturowniaConfigured)
                                        <span class="mt-1.5 block text-xs text-amber-700">Aby móc włączyć, najpierw podaj adres konta i token w <a href="{{ route('seller.integrations.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">Integracjach</a>.</span>
                                    @endunless
                                </label>
                            </div>
                        @endif

                        {{-- Nadawanie przesyłek InPost — bliźniak włącznika Paynow.
                             Bramka `courier_shipping` (to samo uprawnienie co płatna wysyłka). --}}
                        @if ($shop->entitlement('courier_shipping'))
                            <div class="rounded-2xl border border-stone-200 bg-white/60 p-5 sm:p-6 {{ $shipxConfigured ? '' : 'opacity-60' }}">
                                <div class="flex items-start gap-4">
                                    {{-- hidden = wartość bazowa; bez konfiguracji checkbox jest disabled i nic nie wysyła --}}
                                    <input type="hidden" name="shipx_enabled" value="{{ $shipxConfigured ? '0' : ($shipxEnabled ? '1' : '0') }}">
                                    <input type="checkbox" id="shipx_enabled" name="shipx_enabled" value="1"
                                        @checked(old('shipx_enabled', $shipxEnabled)) @disabled(! $shipxConfigured)
                                        class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20 disabled:cursor-not-allowed">
                                    <label for="shipx_enabled" class="flex-1 {{ $shipxConfigured ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                                        <span class="block text-sm font-medium text-stone-800">Nadawanie przesyłek InPost</span>
                                        <span class="mt-0.5 block text-sm text-stone-500">Włącza przycisk „Nadaj przesyłkę" na karcie zamówienia — paczkę nadajesz i etykietę drukujesz bez wchodzenia do panelu InPostu.</span>
                                        <span class="mt-1.5 block text-xs text-stone-400">Za każdą przesyłkę płacisz InPostowi ze swojego salda — Kramio nie pobiera żadnej opłaty.</span>
                                        @unless($shipxConfigured)
                                            <span class="mt-1.5 block text-xs text-amber-700">Aby móc włączyć, najpierw podaj token ShipX i Organization ID w <a href="{{ route('seller.integrations.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">Integracjach</a>.</span>
                                        @endunless
                                    </label>
                                </div>

                                {{-- Jak sprzedawca ODDAJE paczki InPostowi — wcięte pod nadawaniem,
                                     bo dotyczy każdej nadanej przesyłki i nie ma sensu bez niego.
                                     To NIE jest metoda dostawy: klient wybiera, jak ma DOSTAĆ
                                     paczkę, a to mówi, jak sprzedawca się jej POZBĘDZIE.

                                     Deklaracja jest WIĄŻĄCA po stronie InPostu (zapisuje się przy
                                     nadaniu i nie da się jej potem zmienić), więc pytamy raz tutaj,
                                     a nie przy każdej paczce — i domyślnie wskazujemy DARMOWĄ.

                                     Pola świadomie NIE są disabled mimo braku konfiguracji: to same
                                     domyślne wartości, nic nie uruchamiają i nie kosztują, a
                                     wypełnienie ich przed podłączeniem InPostu jest naturalne. --}}
                                <div class="mt-4 border-t border-stone-100 pt-4">
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-stone-800">Jak oddajesz paczki InPostowi?</p>
                                        <div class="mt-2 space-y-2">
                                            @foreach ($sendingMethods as $case)
                                                <div class="flex items-start gap-3">
                                                    <input type="radio" id="sending_method_{{ $case->value }}" name="shipment_sending_method" value="{{ $case->value }}"
                                                        @checked(old('shipment_sending_method', $shop->sendingMethod()->value) === $case->value)
                                                        class="mt-0.5 h-5 w-5 shrink-0 border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20">
                                                    <label for="sending_method_{{ $case->value }}" class="flex-1 cursor-pointer">
                                                        <span class="block text-sm text-stone-800">
                                                            {{ $case->label() }}
                                                            @if ($case->isPaid())
                                                                <span class="font-medium text-amber-700">— usługa dodatkowo płatna</span>
                                                            @endif
                                                        </span>
                                                        <span class="mt-0.5 block text-xs text-stone-400">{{ $case->hint() }}</span>
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                        @error('shipment_sending_method')
                                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                        @enderror

                                        {{-- Domyślna paczka kurierska: paczkomat opisuje się gabarytem
                                             skrytki, kurier WYMIARAMI I WAGĄ. U rękodzielnika paczka
                                             bywa powtarzalna, więc podpowiadamy — inaczej sprzedawca
                                             wpisuje to samo przy każdym zamówieniu. Tak samo robi
                                             panel InPostu („Domyślny rozmiar przesyłki”). --}}
                                        <div class="mt-5">
                                            <p class="text-sm font-medium text-stone-800">Domyślna paczka kurierska <span class="font-normal text-stone-400">(opcjonalnie)</span></p>
                                            <p class="mt-0.5 text-xs text-stone-400">Podpowiadamy te wartości przy nadawaniu — przy każdej paczce możesz je zmienić.</p>

                                            {{-- Cztery pola w JEDNEJ linii. Świadomie flex, nie grid:
                                                 `grid-cols-4` nie istnieje w buildzie, a klasa spoza
                                                 buildu cicho nic nie robi. Jednostka siedzi w etykiecie,
                                                 nie jako sufiks w polu — przy czterech polach obok
                                                 siebie sufiks zjadałby miejsce na cyfry na telefonie. --}}
                                            <div class="mt-2 flex gap-2">
                                                @foreach ([['courier_parcel_length_cm', 'Długość (cm)'], ['courier_parcel_width_cm', 'Szerokość (cm)'], ['courier_parcel_height_cm', 'Wysokość (cm)'], ['courier_parcel_weight_kg', 'Waga (kg)']] as [$parcelField, $parcelLabel])
                                                    <div class="min-w-0 flex-1">
                                                        <label for="{{ $parcelField }}" class="block text-xs text-stone-500">{{ $parcelLabel }}</label>
                                                        <input id="{{ $parcelField }}" name="{{ $parcelField }}" type="text" inputmode="decimal"
                                                            value="{{ old($parcelField, $shop->$parcelField) }}"
                                                            class="mt-1 block w-full rounded-2xl border border-stone-200 bg-white/80 px-3 py-3 text-center text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                                    </div>
                                                @endforeach
                                            </div>

                                            {{-- Błędy pod całą czwórką, nie pod pojedynczym polem:
                                                 przy tak wąskich kolumnach komunikat i tak by się nie
                                                 zmieścił, a rozpychałby jedną z nich. --}}
                                            @foreach (['courier_parcel_length_cm', 'courier_parcel_width_cm', 'courier_parcel_height_cm', 'courier_parcel_weight_kg'] as $parcelField)
                                                @error($parcelField)
                                                    <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                                                @enderror
                                            @endforeach

                                            @unless($shop->courier_enabled)
                                                <p class="mt-1.5 text-xs text-amber-700">Przyda się, gdy włączysz <a href="#courier_enabled" onclick="document.getElementById('courier_enabled').scrollIntoView({behavior:'smooth',block:'center'});return false;" class="font-medium underline decoration-amber-300 underline-offset-2">dostawę kurierem</a> — do paczkomatu wybierasz gabaryt, nie wymiary.</p>
                                            @endunless
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($shop->entitlement('ga_analytics'))
                        @php($gaConfigured = filled($googleAnalyticsId))
                        <div class="flex items-start gap-4 rounded-2xl border border-stone-200 bg-white/60 p-5 sm:p-6 {{ $gaConfigured ? '' : 'opacity-60' }}">
                            {{-- hidden = wartość bazowa; bez ID checkbox jest disabled i nic nie wysyła --}}
                            <input type="hidden" name="google_analytics_enabled" value="{{ $gaConfigured ? '0' : ($googleAnalyticsEnabled ? '1' : '0') }}">
                            <input type="checkbox" id="google_analytics_enabled" name="google_analytics_enabled" value="1"
                                @checked(old('google_analytics_enabled', $googleAnalyticsEnabled)) @disabled(! $gaConfigured)
                                class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-stone-300 text-amber-600 focus:ring-4 focus:ring-amber-500/20 disabled:cursor-not-allowed">
                            <label for="google_analytics_enabled" class="flex-1 {{ $gaConfigured ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                                <span class="block text-sm font-medium text-stone-800">Google Analytics</span>
                                <span class="mt-0.5 block text-sm text-stone-500">Zbiera statystyki ruchu w Twoim sklepie i wysyła je na Twoje konto Google.</span>
                                @unless($gaConfigured)
                                    <span class="mt-1.5 block text-xs text-amber-700">Aby móc włączyć, najpierw wpisz identyfikator w <a href="{{ route('seller.integrations.edit') }}" class="font-medium underline decoration-amber-300 underline-offset-2">Integracjach</a>.</span>
                                @endunless
                            </label>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                        class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                        Zapisz ustawienia
                    </button>
                </div>
            </form>

            {{-- Punkty za zakupy — OSOBNY formularz (inna trasa, własna walidacja),
                 widoczny tylko z uprawnieniem `loyalty_points`. Włącznika celowo
                 nie ma: punkty włącza na razie zespół Kramio (decyzja Rafała 07.10). --}}
            @if ($shop->entitlement('loyalty_points'))
                @php($inputClass = 'block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15')
                @php($percentValue = old('loyalty_earn_percent', $shop->loyalty_earn_percent !== null ? \App\Support\LoyaltyRules::percent((float) $shop->loyalty_earn_percent) : ''))
                @php($pointValue = old('loyalty_point_value', number_format($shop->loyaltyPointValue(), 2, ',', '')))
                @php($previewPoints = app(\App\Services\LoyaltyLedger::class)->pointsFromBase($shop, 100))
                <form id="punkty" method="POST" action="{{ route('seller.settings.loyalty') }}" class="mt-6 space-y-6" novalidate data-validate
                    x-data="loyaltyPreview(@js(['percent' => rtrim((string) $percentValue, '%'), 'value' => (string) $pointValue]))">
                    @csrf

                    <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                        <h2 class="font-semibold text-stone-900">Punkty za zakupy</h2>
                        <p class="mt-1 text-sm text-stone-500">Klienci zbierają punkty za zrealizowane zamówienia i wymieniają je na rabat przy kolejnych zakupach.</p>

                        @if ($shop->loyalty_enabled)
                            <p class="mt-4 rounded-2xl bg-white px-4 py-3 text-sm text-stone-700">
                                <span class="font-medium text-stone-900">Punkty są włączone.</span>
                                @if ($loyaltyRulesPage)
                                    Klienci widzą je opisane na stronie <a href="{{ route('seller.pages.edit', $loyaltyRulesPage) }}" class="font-medium underline decoration-amber-300 underline-offset-2">Zasady punktów</a>.
                                @endif
                            </p>
                        @else
                            <p class="mt-4 rounded-2xl bg-white px-4 py-3 text-sm text-stone-700">Punkty są wyłączone. Ustawienia możesz przygotować już teraz.</p>
                        @endif

                        {{-- Przelicznik na żywo (Alpine): to samo liczenie co LoyaltyLedger::pointsFromBase —
                             grosze na liczbach całkowitych, ułamek punktu przepada. Bez JS zostaje
                             wartość policzona na serwerze dla 100 zł. --}}
                        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-stone-700">
                            <span class="font-medium text-stone-900">Podgląd:</span>
                            za zakupy za
                            <input type="text" inputmode="decimal" x-model="amount" value="100" aria-label="Przykładowa kwota zakupów"
                                class="inline-block w-24 rounded-xl border border-stone-200 bg-white px-2 py-1 text-right tabular-nums">
                            zł klient dostanie
                            <strong class="text-stone-900" x-text="points() + ' pkt'">{{ $previewPoints }} pkt</strong>,
                            czyli <strong class="text-stone-900" x-text="money(points() * grosze(value))">{{ \App\Support\Money::pln($previewPoints * $shop->loyaltyPointValue()) }}</strong> na kolejne zakupy.
                            <span x-show="ready() && points() === 0" style="display: none" class="mt-1 block text-xs text-amber-700">Przy takiej kwocie klient nie dostanie ani jednego punktu — obniż wartość punktu albo podnieś zwrot.</span>
                        </div>

                        <div class="mt-6 grid grid-cols-12 gap-5">
                            <div class="col-span-6 sm:col-span-4">
                                <label for="loyalty_earn_percent" class="block text-sm font-medium text-stone-700">Zwrot w punktach</label>
                                <div class="relative mt-1.5">
                                    <input id="loyalty_earn_percent" name="loyalty_earn_percent" type="text" inputmode="decimal" placeholder="5" required
                                        value="{{ rtrim((string) $percentValue, '%') }}" x-model="percent"
                                        data-msg-required="Podaj, ile procent wraca w punktach, np. 5."
                                        class="{{ $inputClass }} pr-10">
                                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-400">%</span>
                                </div>
                                @error('loyalty_earn_percent')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-stone-400">Tyle procent zapłaconej wartości produktów (bez dostawy) wraca do klienta.</p>
                            </div>

                            <div class="col-span-6 sm:col-span-4">
                                <label for="loyalty_point_value" class="block text-sm font-medium text-stone-700">Wartość punktu</label>
                                <div class="relative mt-1.5">
                                    <input id="loyalty_point_value" name="loyalty_point_value" type="text" inputmode="decimal" placeholder="0,01" required
                                        value="{{ $pointValue }}" x-model="value"
                                        data-msg-required="Podaj, ile wart jest jeden punkt, np. 0,01."
                                        class="{{ $inputClass }} pr-10">
                                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-400">zł</span>
                                </div>
                                @error('loyalty_point_value')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-stone-400">Ile złotych rabatu daje jeden punkt — od 0,01 do 10,00 zł.</p>
                            </div>

                            <div class="col-span-6 sm:col-span-4">
                                <label for="loyalty_delay_days" class="block text-sm font-medium text-stone-700">Karencja</label>
                                <div class="relative mt-1.5">
                                    <input id="loyalty_delay_days" name="loyalty_delay_days" type="text" inputmode="numeric"
                                        placeholder="{{ (int) config('legal.withdrawal.days') + (int) config('legal.withdrawal.delivery_buffer_days') }}"
                                        value="{{ old('loyalty_delay_days', $shop->loyalty_delay_days) }}"
                                        class="{{ $inputClass }} pr-12">
                                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-400">dni</span>
                                </div>
                                @error('loyalty_delay_days')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-stone-400">Po tylu dniach od realizacji punkty są do wykorzystania. Puste = tyle, ile trwa okno zwrotu.</p>
                            </div>

                            <div class="col-span-6 sm:col-span-4">
                                <label for="loyalty_validity_months" class="block text-sm font-medium text-stone-700">Ważność <span class="font-normal text-stone-400">(opcjonalnie)</span></label>
                                <div class="relative mt-1.5">
                                    <input id="loyalty_validity_months" name="loyalty_validity_months" type="text" inputmode="numeric" placeholder="bez terminu"
                                        value="{{ old('loyalty_validity_months', $shop->loyalty_validity_months) }}"
                                        class="{{ $inputClass }} pr-12">
                                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-400">mies.</span>
                                </div>
                                @error('loyalty_validity_months')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-stone-400">Liczona od dnia, w którym punkty są dostępne. Puste = punkty nie wygasają.</p>
                            </div>

                            <div class="col-span-6 sm:col-span-4">
                                <label for="loyalty_max_redeem_percent" class="block text-sm font-medium text-stone-700">Limit płatności punktami <span class="font-normal text-stone-400">(opcjonalnie)</span></label>
                                <div class="relative mt-1.5">
                                    <input id="loyalty_max_redeem_percent" name="loyalty_max_redeem_percent" type="text" inputmode="numeric" placeholder="bez limitu"
                                        value="{{ old('loyalty_max_redeem_percent', $shop->loyalty_max_redeem_percent) }}"
                                        class="{{ $inputClass }} pr-10">
                                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-400">%</span>
                                </div>
                                @error('loyalty_max_redeem_percent')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-stone-400">Najwyższa część wartości produktów, którą klient zapłaci punktami.</p>
                            </div>

                            <div class="col-span-6 sm:col-span-4">
                                <label for="loyalty_min_redeem_points" class="block text-sm font-medium text-stone-700">Minimum do wydania <span class="font-normal text-stone-400">(opcjonalnie)</span></label>
                                <div class="relative mt-1.5">
                                    <input id="loyalty_min_redeem_points" name="loyalty_min_redeem_points" type="text" inputmode="numeric" placeholder="bez minimum"
                                        value="{{ old('loyalty_min_redeem_points', $shop->loyalty_min_redeem_points) }}"
                                        class="{{ $inputClass }} pr-12">
                                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-400">pkt</span>
                                </div>
                                @error('loyalty_min_redeem_points')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1.5 text-xs text-stone-400">Od tylu punktów klient może z nich skorzystać.</p>
                            </div>
                        </div>

                        @if ($loyaltyHasBalances)
                            <label class="mt-6 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-stone-700">
                                <input type="checkbox" name="confirm_revaluation" value="1" class="mt-0.5 shrink-0"
                                    @checked(old('confirm_revaluation'))>
                                <span>
                                    <span class="font-medium text-stone-900">Zmieniam wartość punktu i rozumiem, że salda klientów zostaną przeliczone.</span>
                                    Wartość w złotych zostanie ta sama, zmieni się liczba punktów — np. 300 pkt po 0,01 zł to 30 pkt po 0,10 zł. Zaznacz tylko przy zmianie wartości punktu, najlepiej po uprzedzeniu klientów.
                                </span>
                            </label>
                            @error('confirm_revaluation')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        @endif
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                            class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                            Zapisz ustawienia punktów
                        </button>
                    </div>
                </form>
            @endif
        </div>

        {{-- Kolumna pomocnicza: wskazówki --}}
        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Wskazówki</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🧮</span>
                        <span>Ustaw stawkę, której używasz najczęściej — zaoszczędzisz klik przy każdym produkcie.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🏦</span>
                        <span>Numer konta do przelewu podajesz w <a href="{{ route('seller.shop.edit') }}#dane-do-przelewu" class="font-medium text-stone-700 underline decoration-amber-300 underline-offset-2">Mój sklep</a>; tutaj tylko włączasz metodę.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🚚</span>
                        <span>Odbiór osobisty i płatność przy odbiorze wymagają uzupełnionego adresu sklepu w <a href="{{ route('seller.shop.edit') }}#adres" class="font-medium text-stone-700 underline decoration-amber-300 underline-offset-2">Mój sklep</a>.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🔧</span>
                        <span>Płatności online (BLIK, karta, szybki przelew) włączysz w <a href="{{ route('seller.integrations.edit') }}" class="font-medium text-stone-700 underline decoration-amber-300 underline-offset-2">Integracjach</a> — wymagają pakietu Stragan lub Pawilon.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>

    <script>
        // Przelicznik punktów: odbicie LoyaltyLedger::pointsFromBase() — grosze na
        // liczbach całkowitych, ułamek punktu przepada. Zmieniasz silnik, zmieniaj i to.
        window.loyaltyPreview = (initial) => ({
            percent: initial.percent,
            value: initial.value,
            amount: '100',
            number(raw) {
                return parseFloat(String(raw ?? '').replace(/\s/g, '').replace(',', '.')) || 0;
            },
            grosze(raw) {
                return Math.round(this.number(raw) * 100);
            },
            ready() {
                return this.grosze(this.amount) > 0 && this.number(this.percent) > 0 && this.grosze(this.value) > 0;
            },
            points() {
                if (! this.ready()) {
                    return 0;
                }

                return Math.floor(this.grosze(this.amount) * Math.round(this.number(this.percent) * 100) / (10000 * this.grosze(this.value)));
            },
            money(grosze) {
                return (grosze / 100).toFixed(2).replace('.', ',') + ' zł';
            },
        });

        // Dwa formularze na jednej stronie: zapis jednego przeładowuje stronę i gubi
        // niezapisane zmiany w drugim. Bez okienka przeglądarki (zasada panelu):
        // pierwsze kliknięcie zatrzymuje zapis i pokazuje ostrzeżenie nad
        // przyciskiem, drugie świadomie zapisuje. Przed wyjściem ze strony pyta
        // sama przeglądarka (beforeunload).
        (() => {
            const forms = [
                [document.getElementById('ustawienia'), 'Ustawienia sklepu'],
                [document.getElementById('punkty'), 'Punkty za zakupy'],
            ].filter(([form]) => form);
            const dirty = new Set();
            const warned = new Set();
            let submitting = false;

            const warn = (form, otherName) => {
                let notice = form.querySelector('[data-unsaved-notice]');

                if (! notice) {
                    notice = document.createElement('p');
                    notice.dataset.unsavedNotice = '';
                    notice.className = 'rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900';
                    form.lastElementChild.before(notice);
                }

                notice.textContent = 'Masz niezapisane zmiany w sekcji „' + otherName + '”. Zapisz najpierw tamtą sekcję albo kliknij ten przycisk jeszcze raz — wtedy tamte zmiany przepadną.';
            };

            forms.forEach(([form]) => {
                const mark = (event) => {
                    if (event.target.name) {
                        dirty.add(form);
                    }
                };
                form.addEventListener('input', mark);
                form.addEventListener('change', mark);
                form.addEventListener('submit', (event) => {
                    const other = forms.find(([candidate]) => candidate !== form && dirty.has(candidate));

                    if (other && ! warned.has(form)) {
                        event.preventDefault();
                        warned.add(form);
                        warn(form, other[1]);
                        return;
                    }

                    submitting = true;
                    // Walidacja w przeglądarce (forms.js) może jeszcze zatrzymać wysyłkę.
                    setTimeout(() => {
                        if (event.defaultPrevented) {
                            submitting = false;
                        }
                    });
                });
            });

            window.addEventListener('beforeunload', (event) => {
                if (dirty.size > 0 && ! submitting) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        })();
    </script>
</x-layouts.panel>
