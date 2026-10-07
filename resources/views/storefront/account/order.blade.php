@php use App\Enums\PaymentMethod; use App\Enums\DeliveryMethod; use App\Enums\OrderStatus; @endphp
<x-storefront.account-shell :shop="$shop" active="orders" heading="Zamówienie #{{ $order->number }}" :back="$back" :crumbs="[
    ['label' => $shop->name, 'url' => '/'],
    ['label' => 'Moje konto', 'url' => '/moje-konto'],
    ['label' => 'Zamówienia', 'url' => '/moje-konto/zamowienia'],
    ['label' => 'Zamówienie #'.$order->number],
]">
    <div class="flex flex-wrap items-center gap-3">
        <span class="rounded-full px-3 py-1 text-sm font-medium {{ $order->status->badgeClasses() }}">{{ $order->status->label() }}</span>
    </div>

    <div class="mt-6 space-y-6">
        {{-- Lewa kolumna: podsumowanie + faktura --}}
        <div class="space-y-6">
        <div class="st-card st-border rounded-3xl border p-6">
            <h2 class="st-brand st-box-title">Podsumowanie</h2>
            <ul class="mt-4 space-y-3">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-3 text-sm">
                        <span class="opacity-80">{{ $item->sale_unit->formatQuantity((float) $item->quantity) }} × {{ $item->name }}</span>
                        <span class="shrink-0 tabular-nums">{{ \App\Support\Money::pln($item->line_total_gross) }}</span>
                    </li>
                @endforeach
            </ul>
            <x-storefront.order-totals :order="$order" />
        </div>

        {{-- Punkty za to zamówienie — te same wpisy co w zakładce „Punkty",
             zawężone do tego zamówienia. SAME PUNKTY, bez wartości w zł (decyzja
             Rafała 07.10). Bez wpisów karty nie ma. --}}
        @if ($loyaltyEntries->isNotEmpty())
            <div class="st-card st-border rounded-3xl border p-6">
                <h2 class="st-brand st-box-title">Punkty</h2>
                <ul class="mt-4 space-y-3">
                    @foreach ($loyaltyEntries as $entry)
                        <li class="flex justify-between gap-3 text-sm">
                            <span class="min-w-0">
                                <span class="opacity-80">{{ $entry->type->label() }}</span>
                                <span class="block text-xs opacity-60">
                                    {{ $entry->created_at->format('d.m.Y') }}
                                    @if ($entry->points > 0 && $entry->available_at)
                                        @if ($entry->available_at->isFuture())
                                            · do wykorzystania od {{ $entry->available_at->format('d.m.Y') }}
                                        @endif
                                        @if ($entry->expires_at)
                                            · ważne do {{ $entry->expires_at->format('d.m.Y') }}
                                        @endif
                                    @endif
                                </span>
                            </span>
                            <span class="shrink-0 font-semibold tabular-nums {{ $entry->points >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $entry->points > 0 ? '+' : '' }}{{ $entry->points }} pkt</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Historia zamówienia: „kiedy co się wydarzyło”. Ten sam układ osi, co
             widzi sprzedawca, ale w klasach motywu — kropki i linia w kolorze
             sklepu. Status początkowy dokładamy ręcznie (nie ma go wśród zdarzeń:
             zamówienie się z nim urodziło, nikt go nie zmieniał). Notatka przy
             zdarzeniu to wiadomość, którą sprzedawca wysłał kupującemu mailem. --}}
        <div class="st-card st-border rounded-3xl border p-6">
            <h2 class="st-brand st-box-title">Historia zamówienia</h2>
            <ol class="st-border mt-4 space-y-3 border-l pl-4">
                <li class="relative">
                    <span class="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full opacity-40" style="background: currentColor"></span>
                    <p class="text-sm font-medium">Złożone</p>
                    <p class="text-xs opacity-50">{{ $order->created_at->format('d.m.Y, H:i') }}</p>
                </li>
                <li class="relative">
                    <span class="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full" style="background: var(--brand)"></span>
                    <p class="text-sm font-medium">{{ $initialStatus->label() }}</p>
                    <p class="text-xs opacity-50">{{ $order->created_at->format('d.m.Y, H:i') }}</p>
                </li>
                @foreach ($order->statusEvents as $event)
                    <li class="relative">
                        <span class="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full" style="background: var(--brand)"></span>
                        <p class="text-sm font-medium">{{ $event->to_status->label() }}</p>
                        <p class="text-xs opacity-50">{{ $event->created_at->format('d.m.Y, H:i') }}</p>
                        @if (filled($event->note))
                            <p class="mt-0.5 text-xs italic opacity-70">„{{ $event->note }}"</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
        </div>

        {{-- Płatność + dostawa --}}
        <div class="space-y-6">
            <div class="st-card st-border rounded-3xl border p-6">
                <h2 class="st-brand st-box-title">Płatność — {{ $order->payment_method->label() }}</h2>
                @if ($order->payment_method === PaymentMethod::BankTransfer)
                    <dl class="mt-4 space-y-1.5 text-sm">
                        @if ($shop->bankAccountHolderName())
                            <div class="flex justify-between gap-3"><dt class="opacity-60">Odbiorca</dt><dd class="text-right font-medium">{{ $shop->bankAccountHolderName() }}</dd></div>
                        @endif
                        @if ($shop->formattedBankAccountNumber())
                            <div class="flex justify-between gap-3"><dt class="opacity-60">Numer konta</dt><dd class="text-right font-mono">{{ $shop->formattedBankAccountNumber() }}</dd></div>
                        @endif
                        <div class="flex justify-between gap-3"><dt class="opacity-60">Tytuł przelewu</dt><dd class="text-right font-medium">Zamówienie #{{ $order->number }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="opacity-60">Kwota</dt><dd class="text-right font-bold">{{ \App\Support\Money::pln($order->total_gross) }}</dd></div>
                    </dl>
                @elseif ($order->payment_method === PaymentMethod::Online)
                    @if ($order->isAwaitingOnlinePayment())
                        <p class="mt-2 text-sm opacity-70">Oczekuje na płatność online (BLIK, karta lub szybki przelew).</p>
                        <a href="/platnosc/{{ $order->paymentToken() }}" class="st-btn mt-4 inline-flex items-center gap-2 rounded-full px-6 py-2.5 text-sm font-semibold shadow-sm transition hover:brightness-105">
                            Zapłać — {{ \App\Support\Money::pln($order->total_gross) }}
                        </a>
                    @else
                        <p class="mt-2 text-sm opacity-70">Opłacone online.</p>
                    @endif
                @else
                    <p class="mt-2 text-sm opacity-70">Płatność na miejscu przy odbiorze zamówienia.</p>
                @endif
            </div>

            <div class="st-card st-border rounded-3xl border p-6">
                <h2 class="st-brand st-box-title">Dostawa — {{ $order->delivery_method->label() }}</h2>
                @if ($order->delivery_method === DeliveryMethod::Pickup)
                    <p class="mt-2 text-sm opacity-70">Odbiór pod adresem sklepu:</p>
                    <p class="mt-1 text-sm font-medium">
                        {{ $shop->street }} {{ $shop->building_number }}{{ $shop->apartment_number ? '/'.$shop->apartment_number : '' }},
                        {{ $shop->postal_code }} {{ $shop->city }}
                    </p>
                @elseif ($order->delivery_method->requiresShippingAddress())
                    <p class="mt-2 text-sm opacity-70">Wysyłka na adres:</p>
                    <p class="mt-1 text-sm font-medium">
                        {{ $order->ship_street }} {{ $order->ship_building_number }}{{ $order->ship_apartment_number ? '/'.$order->ship_apartment_number : '' }},
                        {{ $order->ship_postal_code }} {{ $order->ship_city }}
                    </p>
                @elseif ($order->delivery_method->requiresParcelLocker())
                    <p class="mt-2 text-sm opacity-70">Wysyłka do paczkomatu:</p>
                    <p class="mt-1 text-sm font-medium">{{ $order->parcel_locker_code }}</p>
                    @if (filled($order->parcel_locker_address))
                        <p class="mt-0.5 text-sm opacity-70">{{ $order->parcel_locker_address }}</p>
                    @endif
                @endif

                {{-- Numer przesyłki dla klienta: pojawia się sam, gdy sprzedawca ją
                     nada. To pierwsze, czego klient tu szuka po zakupie („gdzie moja
                     paczka?"), więc daje też link wprost do śledzenia InPostu. --}}
                @if (filled($order->shipment_tracking_number))
                    <div class="st-border mt-4 border-t pt-4">
                        <p class="text-sm opacity-70">Numer przesyłki:</p>
                        <p class="mt-1 break-all font-mono text-sm font-medium">{{ $order->shipment_tracking_number }}</p>
                        @if ($order->trackingUrl())
                            <a href="{{ $order->trackingUrl() }}" target="_blank" rel="noopener"
                                class="st-brand mt-2 inline-block text-sm underline underline-offset-2">Śledź przesyłkę</a>
                        @endif
                        @if ($order->delivered_at)
                            <p class="mt-2 text-sm opacity-70">Odebrano: <span class="font-medium opacity-100">{{ $order->delivered_at->format('d.m.Y, H:i') }}</span></p>
                        @endif
                    </div>
                @endif
            </div>

            @if (filled($order->note))
                <div class="st-card st-border rounded-3xl border p-6">
                    <h2 class="st-brand st-box-title">Uwagi</h2>
                    <p class="mt-2 whitespace-pre-line text-sm opacity-80">{{ $order->note }}</p>
                </div>
            @endif
        </div>

        {{-- Zwroty: przycisk do formularza, dopóki biegnie termin, oraz historia
             złożonych oświadczeń. Prowadzi pod TEN SAM adres co link z maila —
             klient ma jedno miejsce niezależnie od tego, którędy wszedł.

             Karta pokazuje się TAKŻE przed wydaniem towaru (gdy w zamówieniu jest
             cokolwiek objętego prawem odstąpienia): klient ma wiedzieć, że taka
             droga istnieje i kiedy się otworzy, zamiast szukać jej na próżno.

             Po wydaniu towaru i upływie terminu, bez zgłoszonych zwrotów, karty
             NIE MA — nie miałaby żadnej treści (pusta ramka, zgłosił Rafał 07.10).
             Dlatego „przed wydaniem" wymaga wprost, by towaru jeszcze nie wydano. --}}
        @php($returnsAhead = $order->hasWithdrawableItems() && ! $order->status->isTerminal() && ! $order->hasBeenHandedOver())
        @if ($order->acceptsReturns() || $order->returns->isNotEmpty() || $returnsAhead)
            <div class="st-card st-border rounded-3xl border p-6">
                <h2 class="st-brand st-box-title">Zwrot</h2>

                @if ($order->returns->isNotEmpty())
                    <ul class="mt-4 space-y-2">
                        @foreach ($order->returns as $return)
                            <li class="flex justify-between gap-3 text-sm">
                                <span class="min-w-0 opacity-80">
                                    {{ $return->created_at->format('d.m.Y') }} —
                                    {{ trans_choice('{1}:count pozycja|[2,4]:count pozycje|[5,*]:count pozycji', $return->items->count(), ['count' => $return->items->count()]) }}
                                    @if ($return->isRefunded())
                                        <span class="text-emerald-600">· pieniądze zwrócone</span>
                                    @else
                                        <span class="opacity-60">· czeka na rozliczenie</span>
                                    @endif
                                </span>
                                <span class="shrink-0 tabular-nums">{{ \App\Support\Money::pln($return->refund_gross) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($order->acceptsReturns())
                    <p class="mt-4 text-sm opacity-70">
                        {{-- Termin zna datę dopiero po realizacji zamówienia; wcześniej
                             mówimy zasadą, a nie datą (inaczej obiecalibyśmy klientowi
                             dzień wyliczony z niczego). --}}
                        @if ($order->withdrawalDeadline())
                            Możesz odstąpić od umowy bez podania przyczyny do {{ $order->withdrawalDeadline()->format('d.m.Y') }}.
                        @else
                            Możesz odstąpić od umowy bez podania przyczyny w ciągu {{ config('legal.withdrawal.days') }} dni od otrzymania zamówienia.
                        @endif
                    </p>
                    <a href="/zwrot/{{ $order->paymentToken() }}"
                        class="st-btn mt-4 inline-flex items-center gap-2 rounded-full px-6 py-2.5 text-sm font-semibold shadow-sm transition hover:brightness-105">
                        {{ $order->hasReturns() ? 'Zgłoś kolejny zwrot' : 'Zgłoś zwrot' }}
                    </a>
                @elseif (! $order->hasBeenHandedOver() && ! $order->status->isTerminal())
                    {{-- Zamówienie w drodze: zamiast przycisku, który i tak by odprawił,
                         mówimy co klientowi przysługuje i KIEDY droga się otworzy. --}}
                    <p class="mt-4 text-sm opacity-70">
                        Masz prawo odstąpić od umowy bez podania przyczyny w ciągu {{ config('legal.withdrawal.days') }} dni od otrzymania zamówienia.
                    </p>
                    <p class="mt-2 text-sm opacity-70">
                        Formularz zwrotu pojawi się tutaj, gdy sklep oznaczy zamówienie jako zrealizowane
                        @if ($order->delivery_method?->requiresParcelLocker())
                            albo gdy odbierzesz paczkę z paczkomatu.
                        @else
                            — czyli po wydaniu towaru.
                        @endif
                        Wcześniej nie ma czego odsyłać.
                    </p>
                    @if (filled($order->shop?->contact_email))
                        <p class="mt-2 text-sm opacity-70">
                            Chcesz zrezygnować już teraz? Napisz do sklepu: <span class="font-medium">{{ $order->shop->contact_email }}</span>.
                        </p>
                    @endif
                @endif
            </div>
        @endif

        @if ($order->hasInvoice() && $order->invoicePdfUrl())
            {{-- Faktura VAT na samym dole. --}}
            <div class="st-card st-border rounded-3xl border p-6">
                <h2 class="st-brand st-box-title">Faktura VAT</h2>
                <p class="mt-2 text-sm opacity-70">
                    @if (filled($order->invoice_number))
                        Faktura nr <span class="font-medium">{{ $order->invoice_number }}</span> do tego zamówienia jest gotowa.
                    @else
                        Faktura do tego zamówienia jest gotowa.
                    @endif
                </p>
                <a href="{{ $order->invoicePdfUrl() }}" target="_blank" rel="noopener"
                    class="st-btn mt-4 inline-flex items-center gap-2 rounded-full px-6 py-2.5 text-sm font-semibold shadow-sm transition hover:brightness-105">
                    <span aria-hidden="true">⬇</span> Pobierz fakturę VAT
                </a>
            </div>
        @endif
    </div>
</x-storefront.account-shell>
