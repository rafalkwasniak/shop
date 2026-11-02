<x-layouts.panel title="Mój pakiet">
    <x-slot:heading>Mój pakiet</x-slot:heading>

    @php($active = $shop->subscriptionActive())
    @php($grace = $shop->inSubscriptionGrace())
    @php($endsAt = $shop->subscription_ends_at)
    @php($daysLeft = $endsAt !== null && $active && ! $grace && ! $shop->comped ? (int) now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false) : null)
    {{-- Czy któryś box już mówi o terminie (a więc niesie przycisk przedłużenia).
         Jeśli tak, karta „Przedłużenie" w kolumnie obok się nie pokazuje — dwa
         identyczne przyciski na jednym ekranie tylko rozmywają akcję. --}}
    @php($termNoticeShown = ! $active || $grace || ($daysLeft !== null && $daysLeft <= (int) config('shop.subscription.notice_days')))

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="space-y-6 lg:col-span-8">
            @if (session('error'))
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
            @endif

            {{-- Stan ostatniej płatności. O zakupie rozstrzyga webhook, więc:
                 pending = „sprawdzamy", failed = „spróbuj ponownie" (ponowienie
                 to nowy wiersz, który przejmuje ten baner). Opłaconej nie
                 ogłaszamy tutaj — widać ją w stanie pakietu wyżej i w mailu. --}}
            @if ($latestPayment?->status === \App\Models\PackagePayment::STATUS_PENDING)
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <p class="font-medium">Płatność za pakiet {{ config('shop.packages.'.$latestPayment->target_package.'.name') }} czeka na potwierdzenie</p>
                    <p class="mt-1 text-xs text-amber-800">
                        Gdy operator potwierdzi wpłatę ({{ \App\Support\Money::pln($latestPayment->amount) }}), pakiet włączy się sam —
                        zwykle w ciągu minuty. Odśwież stronę za chwilę.
                    </p>
                </div>
            @elseif ($latestPayment?->status === 'failed' && ! $latestPayment->isApplied())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <p class="font-medium">Płatność za pakiet {{ config('shop.packages.'.$latestPayment->target_package.'.name') }} nie doszła do skutku</p>
                    <p class="mt-1 text-xs">
                        Nic nie zostało pobrane. Możesz spróbować ponownie przyciskiem „Kup" poniżej — wycena policzy się od nowa.
                    </p>
                </div>
            @endif
            {{-- Stan abonamentu na wierzchu: nazwa pakietu, termin, cena. --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm text-stone-500">Twój pakiet</p>
                        {{-- Nazwa EFEKTYWNA (decyzja Rafała): po wygaśnięciu sklep
                             działa na zasadach Kramu i tak ma być nazwany. Co
                             klient kupił, mówi plakietka i Historia opłat — w
                             bazie snapshot zostaje nietknięty, więc odnowienie to
                             wciąż zmiana jednej daty. --}}
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-stone-900">{{ $shop->effectivePackageName() }}</p>
                    </div>
                    @if (! $active)
                        <span class="shrink-0 rounded-full bg-rose-50 px-3 py-1 text-xs font-medium text-rose-700">Pakiet {{ $shop->packageName() }} wygasł</span>
                    @elseif ($grace)
                        <span class="shrink-0 rounded-full bg-sky-50 px-3 py-1 text-xs font-medium text-sky-700">Po terminie — czeka na opłatę</span>
                    @elseif ($shop->comped)
                        <span class="shrink-0 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">Dostęp bezpłatny</span>
                    @elseif ($shop->priceYearly() <= 0)
                        <span class="shrink-0 rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-600">Za darmo, bez limitu czasu</span>
                    @else
                        <span class="shrink-0 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">Aktywny</span>
                    @endif
                </div>

                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    @if ($shop->priceYearly() > 0)
                        <div class="rounded-2xl border border-stone-200 bg-white/60 px-4 py-3">
                            <dt class="text-xs text-stone-400">Opłata roczna</dt>
                            <dd class="mt-0.5 font-semibold text-stone-900">{{ \App\Support\Money::pln($shop->priceYearly()) }}</dd>
                        </div>
                    @endif

                    <div class="rounded-2xl border border-stone-200 bg-white/60 px-4 py-3">
                        <dt class="text-xs text-stone-400">{{ $grace ? 'Termin minął' : ($active ? 'Opłacony do' : 'Wygasł') }}</dt>
                        <dd class="mt-0.5 font-semibold text-stone-900">
                            @if ($shop->comped)
                                bezterminowo
                            @elseif ($shop->priceYearly() <= 0)
                                nie wygasa
                            @elseif ($endsAt === null)
                                bezterminowo
                            @else
                                {{ $endsAt->format('d.m.Y') }}
                            @endif
                        </dd>
                    </div>
                </dl>

                {{-- Ostrzeżenia: po terminie mówimy, co przestało działać; przed
                     terminem przypominamy dopiero, gdy zostało ≤ 30 dni. --}}
                @if (! $active)
                    <div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                        <p class="font-medium">Pakiet {{ $shop->packageName() }} wygasł {{ $endsAt?->format('d.m.Y') }}</p>
                        <p class="mt-1 text-xs text-rose-800">
                            Sklep i zamówienia działają dalej — wyłączone są funkcje płatnego pakietu, a produkty i banery ponad limit są
                            ukryte (nic nie zostało usunięte). Po opłaceniu wszystko wraca takie, jak było, razem z ustawieniami.
                        </p>
                        @include('seller.package._renew')
                    </div>
                @elseif ($grace)
                    {{-- Karencja: termin minął, ale funkcje jeszcze działają.
                         Mówimy to wprost i podajemy datę wyłączenia, żeby nie
                         było niespodzianki. --}}
                    <div class="mt-5 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                        <p class="font-medium">Termin minął {{ $endsAt->format('d.m.Y') }} — sklep działa dalej, ale czeka na opłatę</p>
                        <p class="mt-1 text-xs text-sky-800">
                            Wszystkie funkcje pakietu są włączone do {{ $shop->subscriptionLocksAt()->format('d.m.Y') }}. Po tym dniu
                            sklep zejdzie na zasady pakietu {{ config('shop.packages.'.config('shop.default_package').'.name') }},
                            a produkty i banery ponad limit zostaną ukryte — do odwrócenia jedną opłatą.
                        </p>
                        @include('seller.package._renew')
                    </div>
                @elseif ($daysLeft !== null && $daysLeft <= (int) config('shop.subscription.notice_days'))
                    {{-- Ostatni tydzień na czerwono: żółty przez cały miesiąc
                         przestaje cokolwiek znaczyć, a im bliżej terminu, tym
                         mniej czasu na przelew. --}}
                    @php($urgent = $daysLeft <= (int) config('shop.subscription.urgent_days'))
                    <div class="mt-5 rounded-2xl border px-4 py-3 text-sm {{ $urgent ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-amber-200 bg-amber-50 text-amber-900' }}">
                        <p class="font-medium">
                            @if ($daysLeft <= 0)
                                Abonament kończy się dziś
                            @else
                                Abonament kończy się za {{ $daysLeft }} {{ trans_choice('{1}dzień|[2,4]dni|[5,*]dni', $daysLeft) }}
                            @endif
                            — {{ $endsAt->format('d.m.Y') }}
                        </p>
                        @if ($urgent)
                            <p class="mt-1 text-xs text-rose-800">
                                Po terminie masz jeszcze {{ (int) config('shop.subscription.grace_days') }} dni karencji, więc sklep nie wyłączy się nagle.
                            </p>
                        @endif
                        @include('seller.package._renew')
                    </div>
                @endif
            </div>

            {{-- Co jest w pakiecie: czytane z UPRAWNIEŃ SKLEPU, więc widać też
                 moduły nadane poza pakietem. --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Co masz w pakiecie</h2>
                <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach ($features as $feature)
                        <li class="flex items-start gap-2 text-sm text-stone-600">
                            <span class="mt-0.5 text-amber-600">✓</span>
                            <span>{{ $feature }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Wyższe pakiety: pogrubione to, co dochodzi (ta sama logika co na
                 stronie głównej, więc obietnica i panel mówią jednym głosem). --}}
            @if ($upgrades !== [])
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Co dostaniesz wyżej</h2>
                    <div class="mt-4 grid gap-5 sm:grid-cols-2">
                        @foreach ($upgrades as $package)
                            @php($quote = $quotes[$package['key']] ?? null)
                            <div class="rounded-2xl border border-stone-200 bg-white/60 p-5">
                                <p class="font-semibold text-stone-900">{{ $package['name'] }}</p>
                                <p class="mt-1 text-sm text-stone-500">
                                    {{ number_format($package['price_yearly'], 0, ',', ' ') }} zł / rok
                                    <span class="text-stone-400">· {{ intdiv($package['price_yearly'], 10) }} zł/mies., 2 miesiące gratis</span>
                                </p>

                                {{-- Kwota przejścia DZIŚ: zawsze pełny rok nowego
                                     pakietu, pomniejszony o resztówkę obecnego. --}}
                                @if ($quote !== null && $quote['kind'] === 'credit')
                                    <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                        Zmiana teraz: <span class="font-semibold">{{ \App\Support\Money::pln($quote['amount']) }}</span>
                                        <span class="block text-xs text-amber-800">
                                            {{-- Zniżka z RÓŻNICY kwot: cena roku minus to, co widać wyżej.
                                                 Surowa proporcja nie domykałaby rachunku po zaokrągleniu. --}}
                                            rok w nowym pakiecie minus {{ \App\Support\Money::pln(\App\Support\PackageUpgrade::discountShown($quote)) }} zniżki
                                            za {{ $quote['days_left'] }} {{ trans_choice('{1}niewykorzystany dzień|[2,4]niewykorzystane dni|[5,*]niewykorzystanych dni', $quote['days_left']) }}
                                        </span>
                                        <span class="mt-1 block text-xs text-amber-800">Nowy termin: {{ $quote['new_ends_at']->format('d.m.Y') }}</span>
                                    </p>
                                @elseif ($quote !== null && $quote['kind'] === 'full')
                                    <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                        Zmiana teraz: <span class="font-semibold">{{ \App\Support\Money::pln($quote['amount']) }}</span>
                                        <span class="block text-xs text-amber-800">pełny rok, licząc od dnia opłacenia</span>
                                    </p>
                                @endif

                                @if ($onlinePurchase && $quote !== null && $quote['amount'] > 0)
                                    <form method="POST" action="{{ route('seller.package.purchase', ['package' => $package['key']]) }}" class="mt-3" novalidate data-validate>
                                        @csrf
                                        {{-- `inline-flex`, nie `w-full`: przycisk ma być przyciskiem,
                                             nie paskiem przez całą kartę (tak jak przy przedłużeniu). --}}
                                        <button type="submit"
                                            class="inline-flex rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                                            Kup {{ $package['name'] }} — {{ \App\Support\Money::pln($quote['amount']) }}
                                        </button>
                                        <p class="mt-1.5 text-xs text-stone-400">BLIK, karta lub szybki przelew (Paynow)</p>
                                        @include('seller.package._immediate-start', ['purchasePackage' => $package['key']])
                                    </form>
                                @endif
                                <ul class="mt-4 space-y-2 text-sm">
                                    @foreach ($package['features'] as $feature)
                                        <li class="flex items-start gap-2 {{ $feature['is_new'] ? 'font-medium text-stone-900' : 'text-stone-500' }}">
                                            <span class="mt-0.5 {{ $feature['is_new'] ? 'text-amber-600' : 'text-stone-300' }}">✓</span>
                                            <span>{{ $feature['label'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <aside class="space-y-6 lg:col-span-4">
            {{-- Dane do faktury POKAZANE PRZED zakupem: sprzedawca ma wiedzieć, na
                 co dokument zostanie wystawiony, zanim zapłaci. Brak adresu blokuje
                 zakup (faktura bez niego jest nieważna), brak NIP-u nie blokuje —
                 wychodzi faktura imienna, co też jest w porządku. --}}
            <div class="rounded-3xl border p-6 backdrop-blur {{ $invoiceDataMissing ? 'border-rose-200 bg-rose-50' : 'border-white/60 bg-white/70' }}">
                <h2 class="font-semibold text-stone-900">Dane do faktury</h2>

                @if ($invoiceDataMissing)
                    <p class="mt-2 text-sm text-rose-800">
                        Uzupełnij nazwę i adres w <a href="{{ route('seller.shop.edit') }}#adres" class="font-medium underline decoration-rose-300 underline-offset-2">Mój sklep</a>,
                        żeby kupić pakiet. Bez nich nie wystawimy faktury.
                    </p>
                @else
                    <p class="mt-2 text-xs text-stone-400">Fakturę za pakiet wystawimy na te dane:</p>
                    <address class="mt-2 text-sm not-italic leading-relaxed text-stone-700">
                        <span class="font-medium">{{ $invoiceRecipient['name'] }}</span><br>
                        @if ($invoiceRecipient['nip'])
                            NIP {{ $invoiceRecipient['nip'] }}<br>
                        @endif
                        {{ $invoiceRecipient['street'] }}<br>
                        {{ $invoiceRecipient['postal_code'] }} {{ $invoiceRecipient['city'] }}
                    </address>

                    @if ($invoiceRecipient['personal'])
                        <p class="mt-3 text-xs text-stone-400">
                            Bez danych firmowych wystawimy <span class="font-medium text-stone-600">fakturę imienną</span>.
                            Jeśli rozliczasz sklep na firmę, dodaj nazwę i NIP w <a href="{{ route('seller.shop.edit') }}" class="underline decoration-amber-300 underline-offset-2">Mój sklep</a>.
                        </p>
                    @else
                        <p class="mt-3 text-xs text-stone-400">
                            Zmienisz je w <a href="{{ route('seller.shop.edit') }}" class="underline decoration-amber-300 underline-offset-2">Mój sklep</a>.
                        </p>
                    @endif
                @endif
            </div>

            {{-- Zużycie limitów — jedyne liczby, które sprzedawca musi pilnować sam. --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Wykorzystanie</h2>

                @php($productsPct = $usage['products_limit'] > 0 ? min(100, (int) round($usage['products'] / $usage['products_limit'] * 100)) : 0)
                <div class="mt-4">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-stone-500">Produkty</span>
                        <span class="font-semibold tabular-nums text-stone-900">{{ $usage['products'] }} / {{ $usage['products_limit'] }}</span>
                    </div>
                    {{-- Pasek jak na Pulpicie: zielony gradient z animacją. Przy
                         wyczerpanym limicie przechodzi w róż — to już komunikat,
                         nie dekoracja. --}}
                    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-stone-100">
                        <div class="h-full min-w-[0.5rem] rounded-full transition-all duration-500 {{ $productsPct >= 100 ? 'bg-rose-400' : 'bg-gradient-to-r from-emerald-400 to-emerald-600' }}" style="width: {{ $productsPct }}%"></div>
                    </div>
                    @if ($productsPct >= 100)
                        <p class="mt-2 text-xs text-rose-700">Limit wyczerpany — nowe produkty dodasz w wyższym pakiecie.</p>
                    @endif
                </div>

                @php($aiPct = $usage['ai_limit'] > 0 ? min(100, (int) round($usage['ai_used'] / $usage['ai_limit'] * 100)) : 0)
                <div class="mt-5">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-stone-500">Zadania AI w tym tygodniu</span>
                        <span class="font-semibold tabular-nums text-stone-900">{{ $usage['ai_used'] }} / {{ $usage['ai_limit'] }}</span>
                    </div>
                    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-stone-100">
                        <div class="h-full min-w-[0.5rem] rounded-full bg-gradient-to-r from-emerald-400 to-emerald-600 transition-all duration-500" style="width: {{ $aiPct }}%"></div>
                    </div>
                    <p class="mt-2 text-xs text-stone-400">Pula odnawia się co tydzień.</p>
                </div>

                {{-- Banery i zdjęcia w stronach: bez paska, bo to nie zasób, który się
                     „zużywa" w czasie — wystarczy liczba i informacja, co przy limicie. --}}
                <div class="mt-5 space-y-2 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-stone-500">Banery na stronie głównej</span>
                        <span class="font-semibold tabular-nums text-stone-900">{{ $usage['banners'] }} / {{ $usage['banners_limit'] }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-stone-500">Zdjęcia w stronach informacyjnych</span>
                        <span class="font-semibold tabular-nums text-stone-900">{{ $usage['page_images'] }} / {{ $usage['page_images_limit'] }}</span>
                    </div>
                    @if ($usage['banners'] > $usage['banners_limit'])
                        <p class="text-xs text-rose-700">Banerów jest więcej, niż pozwala pakiet — sklep pokazuje {{ $usage['banners_limit'] }} pierwszych z listy.</p>
                    @endif
                </div>
            </div>

            {{-- Przedłużenie z wyprzedzeniem: sprzedawca ma móc opłacić rok, kiedy
                 chce, nie tylko gdy ekran zaczyna o tym przypominać. Przy bliskim
                 terminie przycisk siedzi już w boksie z przypomnieniem, więc tej
                 karty wtedy nie pokazujemy. --}}
            @if ($renewal['kind'] === 'renewal' && ! $termNoticeShown)
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Przedłużenie</h2>
                    <p class="mt-2 text-sm text-stone-500">
                        Rok <span class="font-medium text-stone-700">dokleja się do obecnego terminu</span> — płacąc teraz,
                        nie tracisz ani jednego opłaconego dnia.
                    </p>
                    @include('seller.package._renew')
                </div>
            @endif

            {{-- Zmiana pakietu na wyższy: kwoty wprost, żeby sprzedawca decydował
                 na podstawie liczb, nie domysłów. --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Zmiana pakietu</h2>

                @if ($quotes !== [])
                    {{-- Zasada dopłaty wyłożona wprost, z kwotami dla każdego
                         droższego pakietu. Sprzedawca ma decydować na podstawie
                         liczb, nie domysłów. --}}
                    <p class="mt-2 text-sm text-stone-500">
                        Wyższy pakiet kupujesz <span class="font-medium text-stone-700">na rok</span>, a to, co zostało
                        z obecnego, <span class="font-medium text-stone-700">odejmujemy jako zniżkę</span> — nic nie tracisz,
                        a termin liczy się od nowa.
                    </p>

                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($quotes as $slug => $quote)
                            <li class="flex items-center justify-between gap-3 rounded-xl border border-stone-200 bg-white/60 px-3 py-2">
                                <span class="text-stone-600">{{ config("shop.packages.{$slug}.name") }}</span>
                                <span class="shrink-0 font-semibold tabular-nums text-stone-900">
                                    {{ \App\Support\Money::pln($quote['amount']) }}
                                    @if ($quote['kind'] === 'full')
                                        <span class="text-xs font-normal text-stone-400">/ rok</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    @php($sample = reset($quotes))
                    @if ($sample['kind'] === 'credit')
                        <p class="mt-3 text-xs text-stone-400">
                            Zniżka to Twoja opłata roczna × {{ $sample['days_left'] }}
                            {{ trans_choice('{1}dzień|[2,4]dni|[5,*]dni', $sample['days_left']) }} ÷ 365
                            (dziś {{ \App\Support\Money::pln(\App\Support\PackageUpgrade::discountShown($sample)) }}), zaokrąglone na Twoją korzyść.
                            Im wcześniej zmienisz pakiet, tym większa zniżka.
                        </p>
                    @endif

                    @if ($onlinePurchase)
                        <p class="mt-3 text-sm text-stone-500">Kupisz od razu — przyciski „Kup" znajdziesz przy pakietach powyżej.</p>
                    @else
                        <a href="mailto:{{ $contactEmail }}?subject={{ rawurlencode('Zmiana pakietu — '.$shop->name) }}"
                            class="mt-4 inline-flex rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                            Napisz do nas
                        </a>
                    @endif
                @else
                    <p class="mt-2 text-sm text-stone-500">
                        Masz najwyższy pakiet — wyżej już nic nie ma.
                        @if ($renewal['kind'] === 'renewal')
                            Zostaje przedłużenie o kolejny rok — {{ \App\Support\Money::pln($renewal['amount']) }}.
                        @endif
                    </p>
                    {{-- Zejście niżej: mówimy OD KIEDY, a nie „napisz do nas".
                         Przycisk pojawia się w oknie odnowienia (≤ 30 dni do końca),
                         bo tylko wtedy zniżka za resztówkę nie robi się większa od
                         kwoty do zapłaty. --}}
                    <p class="mt-2 text-xs text-stone-400">
                        @if ($downsizes !== [])
                            Chcesz zejść na mniejszy pakiet? Kupisz go przyciskiem w boksie o terminie — mniejsze limity wchodzą od razu,
                            a resztę obecnego okresu odliczamy jako zniżkę.
                        @elseif ($endsAt !== null && $shop->priceYearly() > 0 && ! $shop->comped)
                            Na mniejszy pakiet przejdziesz w ostatnich {{ (int) config('shop.subscription.notice_days') }} dniach abonamentu,
                            czyli od {{ $endsAt->copy()->subDays((int) config('shop.subscription.notice_days'))->format('d.m.Y') }} —
                            wcześniej nie ma sensu, bo płacisz za okres, który jeszcze trwa.
                        @else
                            Zmiana na mniejszy pakiet wchodzi przy odnowieniu —
                            <a href="mailto:{{ $contactEmail }}?subject={{ rawurlencode('Zmiana pakietu — '.$shop->name) }}" class="font-medium text-stone-600 underline decoration-amber-300 underline-offset-2">daj nam znać</a>.
                        @endif
                    </p>
                @endif
            </div>

            {{-- Historia PAKIETU, nie tylko opłat: pakiet zmienia się dwiema
                 drogami (płatność sprzedawcy i konsola admina), więc log musi
                 pokazywać obie. Inaczej ręcznie nadany pakiet wygląda, jakby
                 wziął się z powietrza. Pod spodem nieudane próby płatności. --}}
            @if ($changes->isNotEmpty() || $failedPayments->isNotEmpty())
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Historia pakietu</h2>
                    <ul class="mt-4 space-y-3">
                        @foreach ($changes as $index => $change)
                            {{-- Najnowszy wpis = stan obecny (lista jest od najnowszych). --}}
                            @php($isCurrent = $index === 0)
                            <li class="rounded-2xl border px-4 py-3 text-sm {{ $isCurrent ? 'border-amber-300 bg-amber-50/60' : 'border-stone-200 bg-white/60' }}">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="flex items-center gap-2 font-medium text-stone-800">
                                        {{ config('shop.packages.'.$change->package.'.name', $change->package) }}
                                        @if ($isCurrent)
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800">obecny</span>
                                        @endif
                                    </span>
                                    <span class="shrink-0 font-semibold tabular-nums text-stone-900">
                                        @if ($change->payment !== null)
                                            {{ \App\Support\Money::pln($change->payment->amount) }}
                                        @else
                                            <span class="text-stone-400">—</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="mt-1 flex flex-wrap items-center justify-between gap-x-3 text-xs text-stone-400">
                                    <span>
                                        {{ $change->created_at->format('d.m.Y') }}
                                        @if ($change->fromPayment())
                                            · opłacony
                                            @if ($change->payment?->invoicePdfUrl())
                                                · <a href="{{ $change->payment->invoicePdfUrl() }}" target="_blank" rel="noopener" class="font-medium text-stone-600 underline decoration-amber-300 underline-offset-2">faktura{{ filled($change->payment->invoice_number) ? ' '.$change->payment->invoice_number : '' }}</a>
                                            @endif
                                        @else
                                            · nadany przez Kramio
                                        @endif
                                    </span>
                                    <span>
                                        @if ($change->comped)
                                            <span class="text-emerald-700">dostęp bezpłatny</span>
                                        @elseif ($change->ends_at !== null)
                                            ważny do {{ $change->ends_at->format('d.m.Y') }}
                                        @else
                                            bez terminu
                                        @endif
                                    </span>
                                </div>
                            </li>
                        @endforeach

                        {{-- Nieudane próby: nie zmieniły pakietu, więc nie ma ich
                             w logu zmian, ale sprzedawca ma prawo wiedzieć. --}}
                        @foreach ($failedPayments as $payment)
                            <li class="rounded-2xl border border-stone-200 bg-white/60 px-4 py-3 text-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-medium text-stone-800">{{ config('shop.packages.'.$payment->target_package.'.name', $payment->target_package) }}</span>
                                    <span class="shrink-0 font-semibold tabular-nums text-stone-400">{{ \App\Support\Money::pln($payment->amount) }}</span>
                                </div>
                                <div class="mt-1 flex items-center justify-between gap-3 text-xs text-stone-400">
                                    <span>{{ $payment->created_at->format('d.m.Y') }}</span>
                                    <span class="text-rose-600">płatność nieudana</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</x-layouts.panel>
