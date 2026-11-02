{{-- Przycisk „Przedłuż o rok" wstawiany do KAŻDEGO boxu, który mówi o terminie
     (przed terminem, w karencji, po wygaśnięciu). Jeden partial, bo trzy kopie
     tego samego zdania rozjechałyby się przy pierwszej zmianie treści.

     Renderuje się tylko wtedy, gdy przedłużenie faktycznie da się kupić: konto
     płatnicze platformy skonfigurowane, dane do faktury kompletne, pakiet płatny
     (darmowy i gratisowy nie mają czego przedłużać). Bez tego zostaje ścieżka
     kontaktowa — żadnych martwych przycisków. --}}
@if ($onlinePurchase && $renewal['kind'] === 'renewal')
    <form method="POST" action="{{ route('seller.package.purchase', ['package' => $shop->package]) }}" class="mt-3" novalidate data-validate>
        @csrf
        {{-- `inline-flex`, nie `w-full`: przycisk ma być przyciskiem, nie paskiem
             przez cały box. Świadomie bez `sm:w-auto` — tej klasy nie ma w
             zbudowanym CSS, więc cicho by nie zadziałała. --}}
        <button type="submit"
            class="inline-flex rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
            Przedłuż o rok — {{ \App\Support\Money::pln($renewal['amount']) }}
        </button>
        <p class="mt-1.5 text-xs text-stone-500">
            {{-- Data wprost: przy przedłużeniu przed terminem rok DOKLEJA SIĘ do
                 posiadanego, więc nikt nie traci dni za wczesną wpłatę. --}}
            Opłacone do {{ $renewal['new_ends_at']->format('d.m.Y') }} · BLIK, karta lub szybki przelew (Paynow)
        </p>
        @include('seller.package._immediate-start', ['purchasePackage' => $shop->package])
    </form>
@else
    <p class="mt-2 text-xs">Napisz do nas na <a href="mailto:{{ $contactEmail }}" class="font-medium underline underline-offset-2">{{ $contactEmail }}</a>, żeby przedłużyć — zdążymy bez przerwy w działaniu sklepu.</p>
@endif

{{-- Zejście na tańszy pakiet: pokazuje się TYLKO w oknie odnowienia, bo tylko tam
     resztówka jest na tyle mała, że zniżka nie zamienia się w żądanie zwrotu
     (warunek Rafała — szczegóły w PackageUpgrade::downsize). --}}
@if ($onlinePurchase && $downsizes !== [])
    <div class="mt-4 border-t border-stone-200 pt-3">
        <p class="text-xs text-stone-500">Albo przejdź na mniejszy pakiet na kolejny rok:</p>
        @foreach ($downsizes as $slug => $quote)
            <form method="POST" action="{{ route('seller.package.purchase', ['package' => $slug]) }}" class="mt-2" novalidate data-validate>
                @csrf
                <button type="submit" class="inline-flex rounded-2xl border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-400">
                    {{ config("shop.packages.{$slug}.name") }} — {{ \App\Support\Money::pln($quote['amount']) }}
                </button>
                <span class="ml-1 block text-xs text-stone-400">
                    @if ($quote['credit'] > 0)
                        {{-- Zniżka z RÓŻNICY kwot, żeby rachunek domykał się na ekranie. --}}
                        rok mniejszego pakietu minus {{ \App\Support\Money::pln(\App\Support\PackageUpgrade::discountShown($quote)) }} zniżki za resztę obecnego okresu ·
                    @endif
                    mniejsze limity wchodzą od razu, produkty i banery ponad limit zostaną ukryte
                </span>
                @include('seller.package._immediate-start', ['purchasePackage' => $slug])
            </form>
        @endforeach
    </div>
@endif
