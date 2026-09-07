<x-layouts.panel title="Pulpit sprzedawcy">
    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Postęp konfiguracji — liczony z realnych danych sklepu.
             Tylko dla właściciela: każdy krok tej listy prowadzi na ekran
             właścicielski (dane firmy, wygląd, pakiet), więc pracownikowi
             pokazywałaby zadania, których nie ma prawa wykonać. --}}
        @unless ($isEmployee)
        <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur lg:col-span-2">
            @php($pct = $total > 0 ? (int) round($done / $total * 100) : 0)
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-stone-900">Skonfiguruj swój sklep</h2>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">{{ $done }} / {{ $total }}</span>
            </div>
            <p class="mt-1 text-sm text-stone-500">
                @if ($total > 0 && $done === $total)
                    Świetnie — sklep jest gotowy do działania.
                @else
                    Przejdź kolejne kroki, aby pokazać sklep klientom.
                @endif
            </p>

            <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-stone-100">
                <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-600 transition-all duration-500" style="width: {{ $pct }}%"></div>
            </div>

            <ul class="mt-6 space-y-3">
                @foreach ($steps as $step)
                    <li>
                        <a href="{{ route($step['route']).($step['anchor'] ?? '') }}" class="flex items-center gap-4 rounded-2xl bg-white/60 px-4 py-3 transition hover:bg-white">
                            @if ($step['done'])
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-semibold text-emerald-600">✓</span>
                            @else
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-stone-300 text-xs text-stone-400">{{ $loop->iteration }}</span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-stone-900">{{ $step['label'] }}</p>
                                <p class="text-xs text-stone-500">{{ $step['desc'] }}</p>
                            </div>
                            <span class="shrink-0 text-stone-300" aria-hidden="true">→</span>
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6 rounded-2xl border border-stone-100 bg-stone-50/70 px-4 py-3">
                <p class="text-sm font-medium text-stone-700">Co dalej?</p>
                <p class="mt-0.5 text-xs text-stone-500">
                    @if ($productCount === 0)
                        Dodaj pierwszy produkt — to ostatni krok, by opublikować sklep i zacząć sprzedaż.
                    @elseif ($activeProductCount === 0)
                        Masz produkty, ale wszystkie są ukryte. Włącz przynajmniej jeden, aby sklep stał się widoczny dla klientów.
                    @elseif ($done < $total)
                        Sklep jest widoczny. Uzupełnij pozostałe kroki, aby budził pełne zaufanie klientów.
                    @else
                        Wszystko gotowe — zadbaj o zdjęcia i opisy produktów, a wkrótce ruszą zamówienia i statystyki.
                    @endif
                </p>
            </div>
        </div>
        @endunless

        {{-- Twój sklep --}}
        <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
            <h2 class="font-semibold text-stone-900">Twój sklep</h2>
            @if ($shop)
                @php($isActive = $shop->status === \App\Enums\ShopStatus::Active)
                @php($logo = $shop->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($shop->logo_path) : null)
                <div class="mt-6 flex flex-col items-center justify-center text-center">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="Logo sklepu" class="h-28 w-auto max-h-28 max-w-[16rem] object-contain">
                    @else
                        <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-stone-100 text-2xl">🛍️</span>
                    @endif
                    <p class="mt-4 font-medium text-stone-900">{{ $shop->name }}</p>
                    <a href="https://{{ $shop->host() }}" target="_blank" rel="noopener"
                        class="mt-1 inline-flex items-center gap-1 text-sm font-medium text-amber-700 transition hover:text-amber-800">
                        {{ $shop->host() }}
                        <span aria-hidden="true">↗</span>
                    </a>
                    @if ($shop->addressComplete())
                        <p class="mt-3 text-xs text-stone-500">
                            {{ $shop->street }} {{ $shop->building_number }}@if ($shop->apartment_number)/{{ $shop->apartment_number }}@endif, {{ $shop->postal_code }} {{ $shop->city }}
                        </p>
                    @endif
                    <span @class([
                        'mt-4 rounded-full px-3 py-1 text-xs font-medium',
                        'bg-emerald-100 text-emerald-700' => $isActive,
                        'bg-stone-100 text-stone-500' => ! $isActive,
                    ])>{{ $shop->status->label() }}</span>
                    <p class="mt-2 text-xs text-stone-500">
                        @if ($isActive)
                            Sklep jest widoczny dla klientów.
                        @elseif ($productCount > 0)
                            Sklep jest ukryty — wszystkie produkty są nieaktywne. Włącz przynajmniej jeden, aby sklep stał się widoczny.
                        @else
                            Twój sklep nie jest jeszcze publiczny. Opublikujemy go automatycznie po dodaniu pierwszego widocznego produktu.
                        @endif
                    </p>

                    {{-- Pakiet: termin + oba wykorzystania (produkty i AI). Te same
                         liczby i te same paski co na „Mój pakiet" — sprzedawca ma
                         widzieć spójny obraz niezależnie od ekranu. --}}
                    @php($maxProducts = (int) $shop->entitlement('max_products'))
                    @php($usagePct = $maxProducts > 0 ? min(100, (int) round($productCount / $maxProducts * 100)) : 0)
                    @php($aiPct = $aiLimit > 0 ? min(100, (int) round($aiUsed / $aiLimit * 100)) : 0)
                    @php($endsAt = $shop->subscription_ends_at)
                    @php($daysLeft = $endsAt !== null && ! $shop->comped && $shop->priceYearly() > 0 ? (int) now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false) : null)
                    {{-- Cała sekcja pakietu i pasków wykorzystania tylko w Kramio.
                         W sklepie dedykowanym limity są symboliczne (milion
                         produktów), więc pasek postępu pokazywałby zawsze zero i
                         sugerował ograniczenie, którego nie ma. Nazwa pakietu i
                         termin abonamentu też nie mają tam adresata. --}}
                    @if (\App\Support\Mode::saas() && ! $isEmployee)
                    <div class="mt-6 w-full text-left">
                        {{-- HR oddzielający sekcję pakietu od danych sklepu --}}
                        <div class="mx-auto w-4/5 border-t border-rose-200"></div>

                        <div class="mt-5 flex flex-wrap items-center justify-between gap-2">
                            <a href="{{ route('seller.package.show') }}" class="text-sm font-medium text-stone-800 underline decoration-amber-300 underline-offset-2 transition hover:text-amber-700">
                                {{-- Nazwa EFEKTYWNA: po wygaśnięciu sklep działa
                                     na zasadach Kramu, więc tak ma się nazywać.
                                     Co wygasło, mówi plakietka obok. --}}
                                Pakiet {{ $shop->effectivePackageName() }}
                            </a>
                            @if (! $shop->subscriptionActive())
                                <span class="rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-medium text-rose-700">{{ $shop->packageName() }} wygasł</span>
                            @elseif ($shop->inSubscriptionGrace())
                                {{-- Karencja = własny kolor, patrz baner w layoucie. --}}
                                <span class="rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-medium text-sky-700">czeka na opłatę</span>
                            @elseif ($shop->comped)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">bezpłatny</span>
                            @elseif ($daysLeft !== null && $daysLeft <= (int) config('shop.subscription.notice_days'))
                                {{-- Ostatni tydzień na czerwono, tak samo jak na
                                     „Mój pakiet" — jeden próg, jeden kolor, żeby
                                     dwa ekrany nie mówiły o tym samym inaczej. --}}
                                @php($urgent = $daysLeft <= (int) config('shop.subscription.urgent_days'))
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $urgent ? 'bg-rose-50 text-rose-700' : 'bg-amber-100 text-amber-800' }}">kończy się {{ $endsAt->format('d.m.Y') }}</span>
                            @elseif ($endsAt !== null)
                                <span class="text-xs text-stone-500">do {{ $endsAt->format('d.m.Y') }}</span>
                            @endif
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-2">
                            <span class="text-xs text-stone-500">Produkty</span>
                            <span class="text-xs tabular-nums text-stone-500">{{ $productCount }} / {{ $maxProducts }}</span>
                        </div>
                        <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-stone-100">
                            <div class="h-full min-w-[0.5rem] rounded-full transition-all duration-500 {{ $usagePct >= 100 ? 'bg-rose-400' : 'bg-gradient-to-r from-emerald-400 to-emerald-600' }}" style="width: {{ $usagePct }}%"></div>
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-2">
                            <span class="text-xs text-stone-500">Zadania AI (tydzień)</span>
                            <span class="text-xs tabular-nums text-stone-500">{{ $aiUsed }} / {{ $aiLimit }}</span>
                        </div>
                        <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-stone-100">
                            <div class="h-full min-w-[0.5rem] rounded-full bg-gradient-to-r from-emerald-400 to-emerald-600 transition-all duration-500" style="width: {{ $aiPct }}%"></div>
                        </div>
                    </div>
                    @endif

                    <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                        @unless ($isEmployee)
                        <a href="{{ route('seller.shop.edit') }}"
                            class="inline-flex rounded-2xl border border-stone-200 bg-white/70 px-5 py-2.5 text-sm font-semibold text-stone-700 transition hover:bg-white">
                            Edytuj sklep
                        </a>
                        @if (\App\Support\Mode::saas())
                            <a href="{{ route('seller.package.show') }}"
                                class="inline-flex rounded-2xl border border-stone-200 bg-white/70 px-5 py-2.5 text-sm font-semibold text-stone-700 transition hover:bg-white">
                                Mój pakiet
                            </a>
                        @endif
                        @endunless
                    </div>
                </div>
            @else
                <div class="mt-6 flex flex-col items-center justify-center text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">🛍️</span>
                    <p class="mt-4 font-medium text-stone-700">Sklep w przygotowaniu</p>
                    <p class="mt-1 text-sm text-stone-500">Nie jest jeszcze widoczny dla klientów.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Sprzedaż — ruszy po dodaniu produktów i publikacji.
         Za działem „Zamówienia": kafle prowadzą na listę zamówień i pokazują
         przychód sklepu. Pracownik od samych produktów nie ma po co ich
         oglądać, a odnośnik kończący się 403 wygląda jak usterka. --}}
    @if ($canSeeOrders)
    <div class="mt-8">
        <h2 class="text-sm font-medium text-stone-500">Twoja sprzedaż</h2>
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- „Nowe zamówienia": powiadomienie z listy Zamówień (to samo źródło co badge
                 w menu) — klik prowadzi na listę, gdzie licznik się zeruje. Akcent gdy > 0. --}}
            <a href="{{ route('seller.orders.index') }}"
               @class([
                   'rounded-3xl border p-5 backdrop-blur transition',
                   'border-emerald-200 bg-emerald-50/80 hover:bg-emerald-50' => $unseenOrders > 0,
                   'border-white/60 bg-white/70 hover:bg-white' => $unseenOrders === 0,
               ])>
                <div class="flex items-center justify-between">
                    <p @class(['text-sm font-medium', 'text-emerald-600' => $unseenOrders > 0, 'text-stone-500' => $unseenOrders === 0])>Nowe zamówienia</p>
                    <span class="text-lg">🔔</span>
                </div>
                <p @class(['mt-2 text-3xl font-semibold tracking-tight', 'text-emerald-600' => $unseenOrders > 0, 'text-stone-900' => $unseenOrders === 0])>{{ $unseenOrders }}</p>
                <p class="mt-1 text-xs text-stone-400">{{ $unseenOrders > 0 ? 'Od Twojej ostatniej wizyty' : 'Brak nowych zamówień' }}</p>
            </a>
            @foreach ([
                ['Produkty', (string) $productCount, $productCount > 0 ? 'W Twoim sklepie' : 'Dodaj pierwszy produkt', '🏷️'],
                ['Zamówienia', (string) $orderCount, $orderCount > 0 ? 'W ostatnich 30 dniach' : 'Czekają na pierwszych klientów', '📦'],
                ['Przychód', \App\Support\Money::pln($revenue), $revenue > 0 ? 'W ostatnich 30 dniach' : 'Pierwsza sprzedaż przed Tobą', '💰'],
            ] as [$label, $value, $hint, $icon])
                <div class="rounded-3xl border border-white/60 bg-white/70 p-5 backdrop-blur">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-stone-500">{{ $label }}</p>
                        <span class="text-lg">{{ $icon }}</span>
                    </div>
                    <p class="mt-2 text-3xl font-semibold tracking-tight text-stone-900">{{ $value }}</p>
                    <p class="mt-1 text-xs text-stone-400">{{ $hint }}</p>
                </div>
            @endforeach
        </div>
    </div>
    @endif
</x-layouts.panel>
