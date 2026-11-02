{{-- Zakładka „Kontakt": po lewej dane firmy z profilu sklepu (sprzedawca niczego
     nie wpisuje drugi raz), po prawej formularz. Strona istnieje tylko przy
     włączonym formularzu (Shop::contactFormEnabled).

     Mapa jako LINK do Map Google, nie osadzenie: osadzona mapa ładuje skrypty
     i ciasteczka Google przy samym wejściu (zgoda na cookies) i spowalnia stronę.

     Pole-pułapka (ContactFormGuard::HONEYPOT) jest schowane za ekranem stylem
     inline i `aria-hidden`, NIE klasą `sr-only` — tę czytnik ekranu czyta, więc
     osoba niewidoma mogłaby pole wypełnić i jej wiadomość przepadłaby jako spam. --}}
<x-layouts.storefront :shop="$shop" title="Kontakt">
    @php
        $company = filled($shop->company_name) ? $shop->company_name : $shop->name;
        $street = trim(trim((string) $shop->street).' '.trim((string) $shop->building_number).(filled($shop->apartment_number) ? '/'.$shop->apartment_number : ''));
        $city = trim($shop->postal_code.' '.$shop->city);
        $mapUrl = $shop->mapUrl();
    @endphp

    <main class="mx-auto max-w-6xl px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Kontakt'],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">Kontakt</h1>

        <div class="st-border mt-8 grid gap-8 border-t pt-8 md:grid-cols-5">
            {{-- Lewa: dane firmy --}}
            <section class="md:col-span-2" aria-labelledby="dane-firmy">
                <h2 id="dane-firmy" class="font-semibold">{{ $company }}</h2>

                <address class="mt-3 space-y-1 text-sm not-italic leading-relaxed opacity-80">
                    @if ($street !== '')<div>{{ $street }}</div>@endif
                    @if ($city !== '')<div>{{ $city }}</div>@endif
                    @if (filled($shop->nip))<div>NIP {{ $shop->nip }}</div>@endif
                </address>

                <ul class="mt-5 space-y-3 text-sm">
                    @if (filled($shop->contact_email))
                        <li>
                            <span class="block text-xs opacity-60">E-mail</span>
                            <a href="mailto:{{ $shop->contact_email }}" class="st-brand break-all underline-offset-2 hover:underline">{{ $shop->contact_email }}</a>
                        </li>
                    @endif
                    @if (filled($shop->contact_phone))
                        <li>
                            <span class="block text-xs opacity-60">Telefon</span>
                            <a href="tel:{{ $shop->contactPhoneHref() }}" class="st-brand underline-offset-2 hover:underline">{{ $shop->formattedContactPhone() }}</a>
                        </li>
                    @endif
                    @if ($mapUrl)
                        <li>
                            <span class="block text-xs opacity-60">Jak do nas dojechać?</span>
                            <a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="st-brand underline-offset-2 hover:underline">Pokaż na mapie ↗</a>
                        </li>
                    @endif
                </ul>
            </section>

            {{-- Prawa: formularz --}}
            <section class="md:col-span-3" aria-labelledby="napisz">
                <div class="st-card st-border rounded-3xl border p-6">
                    <h2 id="napisz" class="font-semibold">Napisz do nas</h2>

                    @if (session('status'))
                        <div class="mt-5 text-center">
                            <p class="font-semibold text-emerald-600">✓ Wiadomość wysłana</p>
                            <p class="mt-1 text-sm opacity-70">{{ session('status') }}</p>
                            <a href="/kontakt" class="st-brand mt-4 inline-block text-sm underline-offset-2 hover:underline">Napisz kolejną wiadomość</a>
                        </div>
                    @else
                        <p class="mt-2 text-sm opacity-70">Odpowiemy na adres e-mail podany w formularzu.</p>

                        @if (session('error'))
                            <p class="mt-4 text-sm text-red-600" role="alert">{{ session('error') }}</p>
                        @endif

                        <form method="POST" action="/kontakt" class="mt-5 space-y-4">
                            @csrf
                            <input type="hidden" name="{{ \App\Services\ContactFormGuard::TIMESTAMP }}" value="{{ $timestamp }}">

                            <div aria-hidden="true" style="position:absolute; left:-10000px; width:1px; height:1px; overflow:hidden;">
                                <label for="{{ \App\Services\ContactFormGuard::HONEYPOT }}">Strona internetowa</label>
                                <input type="text" id="{{ \App\Services\ContactFormGuard::HONEYPOT }}" name="{{ \App\Services\ContactFormGuard::HONEYPOT }}" value="" tabindex="-1" autocomplete="off">
                            </div>

                            <div>
                                <label for="name" class="block text-sm opacity-80">Imię</label>
                                <input type="text" id="name" name="name" value="{{ $prefillName }}" required maxlength="100" autocomplete="name"
                                    class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                @error('name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="email" class="block text-sm opacity-80">Adres e-mail</label>
                                <input type="email" id="email" name="email" value="{{ $prefillEmail }}" required maxlength="190" autocomplete="email"
                                    class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                @error('email')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="message" class="block text-sm opacity-80">Treść wiadomości</label>
                                <textarea id="message" name="message" rows="6" required maxlength="5000"
                                    class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">{{ old('message') }}</textarea>
                                @error('message')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <p class="text-xs leading-relaxed opacity-60">
                                Administratorem danych jest {{ $company }}. Dane z formularza wykorzystamy wyłącznie do odpowiedzi
                                na wiadomość. Więcej w <a href="{{ $shop->privacyPath() }}" class="underline underline-offset-2">polityce prywatności</a>.
                            </p>

                            <button type="submit" class="st-btn inline-block rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">
                                Wyślij wiadomość
                            </button>
                        </form>
                    @endif
                </div>
            </section>
        </div>
    </main>
</x-layouts.storefront>
