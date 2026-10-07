<x-layouts.panel :title="'Zamówienie #'.$order->number">
    <x-slot:heading>Zamówienie #{{ $order->number }}</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: pozycje + podsumowanie --}}
        <div class="space-y-6 lg:col-span-8">
            <livewire:seller.order-editor :order="$order" />

            {{-- Zwroty tuż pod pozycjami: to one wyjaśniają, czemu kwoty zamówienia
                 są niższe niż pierwotnie. Karta sama się chowa, gdy zwrotów nie ma. --}}
            <livewire:seller.order-returns :order="$order" />

            {{-- Punkty tego zamówienia — te same wpisy, które klient widzi przy
                 zamówieniu w „Moim koncie". Panel pokazuje też bieżące saldo
                 klienta z odnośnikiem do kartoteki (historia i korekta). --}}
            @if ($loyaltyEntries->isNotEmpty())
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <div class="flex flex-wrap items-baseline justify-between gap-3">
                        <h2 class="font-semibold text-stone-900">Punkty</h2>
                        <a href="{{ route('seller.customers.show', ['email' => mb_strtolower(trim($order->buyer_email))]) }}"
                            class="text-sm font-medium text-stone-500 underline decoration-amber-300 underline-offset-2 hover:text-stone-800">
                            Saldo klienta: <span class="tabular-nums">{{ $customerPoints }} pkt</span>
                        </a>
                    </div>
                    <ul class="mt-4 space-y-2">
                        @foreach ($loyaltyEntries as $entry)
                            <li class="flex items-start justify-between gap-3 text-sm">
                                <div class="min-w-0">
                                    <p class="text-stone-700">{{ $entry->type->label() }}</p>
                                    <p class="text-xs text-stone-400">
                                        {{ $entry->created_at->format('d.m.Y') }}
                                        @if ($entry->points > 0 && $entry->available_at)
                                            @if ($entry->available_at->isFuture())
                                                · dostępne od {{ $entry->available_at->format('d.m.Y') }}
                                            @endif
                                            @if ($entry->expires_at)
                                                · ważne do {{ $entry->expires_at->format('d.m.Y') }}
                                            @endif
                                        @endif
                                    </p>
                                </div>
                                <span class="shrink-0 font-semibold tabular-nums {{ $entry->points >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $entry->points > 0 ? '+' : '' }}{{ $entry->points }} pkt</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (filled($order->note))
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Uwagi klienta</h2>
                    <p class="mt-2 whitespace-pre-line text-sm text-stone-600">{{ $order->note }}</p>
                </div>
            @endif

            <div class="grid gap-6 md:grid-cols-2 md:items-start">
                {{-- Lewa kolumna: kupujący, a pod nim termin na odstąpienie —
                     tu jest więcej miejsca niż przy dostawie. --}}
                <div class="space-y-6">
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Kupujący</h2>
                    <div class="mt-3 space-y-1.5 text-sm text-stone-600">
                        <p class="font-medium text-stone-800">{{ trim($order->buyer_name.' '.$order->buyer_surname) }}</p>
                        <p><a href="mailto:{{ $order->buyer_email }}" class="text-amber-700 hover:underline">{{ $order->buyer_email }}</a></p>
                        @if (filled($order->buyer_phone))
                            <p>{{ app(\App\Services\PhoneService::class)->format($order->buyer_phone) ?? $order->buyer_phone }}</p>
                        @endif
                    </div>

                    @if ($order->is_company)
                        <div class="mt-4 border-t border-stone-100 pt-4 text-sm text-stone-600">
                            <p class="text-xs font-medium uppercase tracking-wide text-stone-400">Dane do faktury</p>
                            <p class="mt-1.5 font-medium text-stone-800">{{ $order->company_name }}</p>
                            @if (filled($order->company_nip))<p>NIP {{ $order->company_nip }}</p>@endif
                            @if (filled($order->company_street))
                                <p class="mt-1">
                                    {{ $order->company_street }} {{ $order->company_building_number }}@if (filled($order->company_apartment_number))/{{ $order->company_apartment_number }}@endif
                                </p>
                                <p>{{ $order->company_postal_code }} {{ $order->company_city }}</p>
                            @endif
                        </div>
                    @endif

                    {{-- Cały cykl faktury VAT (przycisk → w przygotowaniu → Pobierz PDF)
                         przy danych do faktury — tam, gdzie sprzedawca na nie patrzy. --}}
                    <livewire:seller.order-invoice :order="$order" />
                </div>

                {{-- Termin na odstąpienie od umowy — ZAWSZE, gdy w zamówieniu jest
                     choć jeden towar objęty prawem zwrotu. Świadomie osobna karta,
                     a nie dopisek przy przesyłce: to fakt prawny dotyczący całego
                     zamówienia, niezależny od tego, czy sklep nadaje przez InPost.
                     Integracja zmienia tylko DOKŁADNOŚĆ daty, nie samą zasadę. --}}
                @if ($order->hasWithdrawableItems())
                    @php($deadline = $order->withdrawalDeadline())
                    @php($open = $order->withinWithdrawalWindow())
                    {{-- Dni liczymy OD DZIŚ DO terminu (w tej kolejności) — odwrotna
                         daje wynik ujemny. Doba zaczyna się od północy, więc oba
                         końce ścinamy do początku dnia. --}}
                    @php($daysLeft = $deadline ? (int) floor(now()->startOfDay()->diffInDays($deadline)) : null)
                    <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                        <h2 class="font-semibold text-stone-900">Odstąpienie od umowy</h2>

                        {{-- `gap-2` (nie `gap-x-2`/`gap-y-1` — tych NIE MA w buildzie
                             Tailwinda, więc odstęp cicho by nie zadziałał). --}}
                        <div class="mt-3 flex flex-wrap items-baseline gap-2">
                            <span class="text-sm text-stone-500">Termin dla klienta:</span>
                            @if ($deadline === null)
                                {{-- Zamówienie w realizacji: termin jeszcze nie wystartował. --}}
                                <span class="font-semibold text-stone-800">jeszcze nie biegnie</span>
                                <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-500">przed dostawą</span>
                            @else
                                <span class="font-semibold {{ $open ? 'text-stone-800' : 'text-stone-400' }}">do {{ $deadline->format('d.m.Y') }}</span>
                                @if ($open)
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                        {{ $daysLeft === 0 ? 'ostatni dzień' : 'zostało '.$daysLeft.' '.trans_choice('dzień|dni|dni', $daysLeft) }}
                                    </span>
                                @else
                                    <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-500">termin minął</span>
                                @endif
                            @endif
                        </div>

                        {{-- Skąd wzięła się data — sprzedawca musi wiedzieć, czy patrzy
                             na fakt, czy na szacunek (przy sporze to różnica). --}}
                        <p class="mt-2 text-xs leading-relaxed text-stone-500">
                            @if ($order->delivered_at)
                                Liczony dokładnie: {{ config('legal.withdrawal.days') }} dni od potwierdzonego odbioru paczki
                                ({{ $order->delivered_at->format('d.m.Y') }}).
                            @elseif ($deadline === null)
                                {{ config('legal.withdrawal.days') }} dni liczy się od chwili, gdy klient odbierze towar — a ten
                                jeszcze do niego nie dotarł. Termin pojawi się tu po oznaczeniu zamówienia jako zrealizowane,
                                a przy wysyłce przez InPost stanie się dokładny (data odbioru zapisuje się sama).
                            @else
                                Szacowany — nie znamy daty doręczenia, więc liczymy od realizacji zamówienia
                                i dokładamy {{ config('legal.withdrawal.delivery_buffer_days') }} dni zapasu na dostawę.
                                Przy wysyłce przez InPost data odbioru zapisuje się sama i termin staje się dokładny.
                            @endif
                        </p>

                        @unless ($order->items->every(fn ($item) => $item->isWithdrawable()))
                            <p class="mt-2 text-xs text-stone-400">Część towarów z tego zamówienia jest wyłączona ze zwrotu.</p>
                        @endunless
                    </div>
                @endif
                </div>

                {{-- Prawa kolumna siatki: dostawa i płatność, a pod nią kod dla
                     klienta — obie karty trzymają tę samą szerokość. --}}
                <div class="space-y-6">
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Dostawa i płatność</h2>
                    <div class="mt-3 space-y-2 text-sm text-stone-600">
                        <div class="flex justify-between gap-3">
                            <span class="text-stone-500">Dostawa</span>
                            <span class="text-right font-medium text-stone-800">{{ $order->delivery_method->label() }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-stone-500">Płatność</span>
                            <span class="text-right font-medium text-stone-800">{{ $order->payment_method->label() }}</span>
                        </div>
                        @if (filled($order->ship_street))
                            <div class="mt-2 border-t border-stone-100 pt-2">
                                <p class="text-xs font-medium uppercase tracking-wide text-stone-400">Adres dostawy</p>
                                <p class="mt-1">
                                    {{ $order->ship_street }} {{ $order->ship_building_number }}@if (filled($order->ship_apartment_number))/{{ $order->ship_apartment_number }}@endif
                                </p>
                                <p>{{ $order->ship_postal_code }} {{ $order->ship_city }}</p>
                            </div>
                        @endif
                        {{-- Kod paczkomatu: po nim sprzedawca nadaje paczkę, więc
                             dostaje wagę i tabular-nums (przepisywany znak w znak). --}}
                        @if (filled($order->parcel_locker_code))
                            <div class="mt-2 border-t border-stone-100 pt-2">
                                <p class="text-xs font-medium uppercase tracking-wide text-stone-400">Paczkomat</p>
                                <p class="mt-1 font-semibold tabular-nums text-stone-800">{{ $order->parcel_locker_code }}</p>
                                @if (filled($order->parcel_locker_address))
                                    <p class="text-stone-500">{{ $order->parcel_locker_address }}</p>
                                @endif
                            </div>
                        @endif

                        {{-- Nadanie przesyłki i etykieta — tuż pod kodem paczkomatu,
                             czyli tam, gdzie sprzedawca i tak patrzy, pakując paczkę. --}}
                        <livewire:seller.order-shipment :order="$order" />
                    </div>
                </div>

                {{-- Nagroda dla klienta: kod rabatowy wystawiony wprost z zamówienia.
                     Ten sam formularz co w dziale Kody rabatowe, tylko z wypełnionym
                     klientem i trybem jednorazowym. Wymaga uprawnienia `discount_codes`
                     (Pawilon). Stoi pod „Dostawą i płatnością", bo to domknięcie
                     obsługi zamówienia, nie element statusu. --}}
                @if ($order->shop->entitlement('discount_codes'))
                    <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                        <h2 class="font-semibold text-stone-900">Kod dla klienta</h2>
                        <p class="mt-1 text-sm text-stone-500">
                            Zadowolony klient wraca. Wystaw jednorazowy kod rabatowy — jako podziękowanie za zakupy albo zachętę do kolejnych.
                        </p>
                        <a href="{{ route('seller.discounts.create', $order->customer_id !== null ? ['klient' => $order->customer_id] : ['jednorazowy' => 1]) }}"
                            class="mt-4 inline-flex rounded-2xl border border-stone-200 bg-white px-5 py-2.5 text-sm font-semibold text-stone-700 transition hover:bg-stone-100">
                            Wystaw kod
                        </a>
                        @if ($order->customer_id === null)
                            <p class="mt-3 text-xs text-stone-400">
                                To zamówienie złożono bez konta, więc kod nie będzie imienny — zadziała u każdego, kto go dostanie. Wyślij go klientowi na jego adres e-mail.
                            </p>
                        @endif
                    </div>
                @endif
                </div>
            </div>

            <div class="pt-2">
                <a href="{{ route('seller.orders.index', $listQuery) }}" class="text-sm font-medium text-stone-500 transition hover:text-stone-800">← Wróć do listy</a>
            </div>
        </div>

        {{-- Kolumna boczna: status + kontakt z klientem --}}
        <aside class="space-y-6 lg:col-span-4">
            <livewire:seller.order-status-manager :order="$order" />
            <livewire:seller.order-messenger :order="$order" />
        </aside>
    </div>
</x-layouts.panel>
