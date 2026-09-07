<x-layouts.panel :title="'Zamówienie '.$order->number">
    <x-slot:actions>
        <a href="{{ route('administrator.orders.index') }}"
            class="rounded-full bg-white/70 px-4 py-1.5 text-sm font-medium text-stone-600 backdrop-blur transition hover:bg-white">
            ← Zamówienia
        </a>
    </x-slot:actions>

    {{-- Karta jest w całości DO ODCZYTU (decyzja Rafała 2026-08-11): żadnego
         formularza, żadnego przycisku akcji. Zamówieniem steruje sprzedawca. --}}
    <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-lg font-semibold tracking-tight text-stone-900">{{ $order->number }}</h2>
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $order->status->badgeClasses() }}">
                        {{ $order->status->label() }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-stone-500">
                    Złożone {{ $order->created_at->format('d.m.Y') }} o {{ $order->created_at->format('H:i') }}
                    @if ($order->shop)
                        · <a href="{{ route('administrator.shops.edit', $order->shop) }}"
                            class="text-stone-700 underline decoration-amber-300 underline-offset-2">{{ $order->shop->name }}</a>
                    @endif
                </p>
            </div>

            <div class="text-right">
                <p class="text-2xl font-semibold tracking-tight tabular-nums text-stone-900">{{ \App\Support\Money::pln($order->total_gross) }}</p>
                <p class="text-xs text-stone-400">brutto</p>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-12">
        <div class="space-y-6 lg:col-span-8">
            <div class="overflow-hidden rounded-3xl border border-white/60 bg-white/70 backdrop-blur">
                <div class="border-b border-stone-200/70 px-5 py-4">
                    <h2 class="font-medium text-stone-900">Pozycje</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm" style="min-width: 34rem">
                        <thead class="border-b border-stone-200/70 text-xs uppercase tracking-wide text-stone-400">
                            <tr>
                                <th class="px-5 py-3 font-medium">Produkt</th>
                                <th class="px-5 py-3 text-right font-medium">Cena</th>
                                <th class="px-5 py-3 text-right font-medium">Ilość</th>
                                <th class="px-5 py-3 text-right font-medium">Wartość</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($order->items as $item)
                                <tr>
                                    <td class="px-5 py-3">
                                        <span class="font-medium text-stone-900">{{ $item->name }}</span>
                                        <p class="text-xs text-stone-400">VAT {{ $item->vat_rate->label() }}</p>
                                    </td>
                                    <td class="px-5 py-3 text-right tabular-nums text-stone-600">{{ \App\Support\Money::pln($item->unit_price_gross) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums text-stone-700">
                                        {{-- Formatowanie ilości z `SaleUnit`, nie własne: to ono zna
                                             jednostkę (szt./kg) i sposób obcinania zer. --}}
                                        {{ $item->sale_unit->formatQuantity($item->effectiveQuantity()) }}
                                        {{-- Zwrot pomniejsza ILOŚĆ EFEKTYWNĄ, a `quantity` zostaje
                                             migawką z chwili zakupu. Pokazujemy oba, bo inaczej
                                             karta przeczyłaby fakturze sprzed zwrotu. --}}
                                        @if ((float) $item->returned_quantity > 0)
                                            <p class="text-xs font-normal text-rose-600">zwrot {{ $item->sale_unit->formatQuantity((float) $item->returned_quantity) }} z {{ $item->sale_unit->formatQuantity((float) $item->quantity) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right tabular-nums font-medium text-stone-900">{{ \App\Support\Money::pln($item->line_total_gross) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-stone-200/70 bg-white/40 px-5 py-4">
                    <dl class="ml-auto max-w-sm space-y-2 text-sm">
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-stone-500">Produkty</dt>
                            <dd class="tabular-nums text-stone-700">{{ \App\Support\Money::pln($order->items_total) }}</dd>
                        </div>
                        @if ((float) $order->discount_amount > 0)
                            <div class="flex items-baseline justify-between gap-4">
                                <dt class="text-stone-500">Rabat{{ $order->discount_code ? ' ('.$order->discount_code.')' : '' }}</dt>
                                <dd class="tabular-nums text-emerald-700">−{{ \App\Support\Money::pln($order->discount_amount) }}</dd>
                            </div>
                        @endif
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-stone-500">Dostawa</dt>
                            <dd class="tabular-nums text-stone-700">{{ \App\Support\Money::pln($order->delivery_cost) }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4 border-t border-stone-200/70 pt-2">
                            <dt class="font-medium text-stone-900">Razem</dt>
                            <dd class="tabular-nums font-semibold text-stone-900">{{ \App\Support\Money::pln($order->total_gross) }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-xs text-stone-400">w tym VAT</dt>
                            <dd class="text-xs tabular-nums text-stone-400">{{ \App\Support\Money::pln($order->total_vat) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Historia statusów to jedyne miejsce, z którego widać, KIEDY
                 zamówienie utknęło — data założenia i bieżący status tego nie mówią. --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-medium text-stone-900">Historia</h2>

                @if ($order->statusEvents->isEmpty())
                    <p class="mt-3 text-sm text-stone-500">Zamówienie nie zmieniło jeszcze statusu.</p>
                @else
                    <ol class="mt-4 space-y-3">
                        @foreach ($order->statusEvents as $event)
                            <li class="flex flex-wrap items-baseline gap-x-3 gap-y-2">
                                <span class="w-32 shrink-0 text-xs tabular-nums text-stone-400">{{ $event->created_at->format('d.m.Y H:i') }}</span>
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $event->to_status->badgeClasses() }}">
                                    {{ $event->to_status->label() }}
                                </span>
                                @if ($event->from_status)
                                    <span class="text-xs text-stone-400">z „{{ $event->from_status->label() }}"</span>
                                @endif
                                <span class="text-xs text-stone-400">· {{ $event->authorLabel() }}</span>
                                @if (filled($event->note))
                                    <span class="w-full text-xs text-stone-500">{{ $event->note }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>

        <aside class="space-y-6 lg:col-span-4">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Kupujący</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-stone-400">Osoba</dt>
                        <dd class="mt-0.5 text-stone-900">{{ trim($order->buyer_name.' '.$order->buyer_surname) ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-stone-400">Kontakt</dt>
                        <dd class="mt-0.5 break-words text-stone-900">
                            {{ $order->buyer_email }}
                            @if (filled($order->buyer_phone))
                                <span class="block tabular-nums text-stone-600">{{ $order->buyer_phone }}</span>
                            @endif
                        </dd>
                    </div>
                    @if ($order->is_company)
                        <div>
                            <dt class="text-xs text-stone-400">Firma</dt>
                            <dd class="mt-0.5 text-stone-900">
                                {{ $order->company_name }}
                                <span class="block tabular-nums text-stone-600">NIP {{ $order->company_nip }}</span>
                                <span class="block text-stone-600">
                                    {{ trim($order->company_street.' '.$order->company_building_number.($order->company_apartment_number ? '/'.$order->company_apartment_number : '')) }},
                                    {{ $order->company_postal_code }} {{ $order->company_city }}
                                </span>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Dostawa i płatność</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-stone-400">Sposób dostawy</dt>
                        <dd class="mt-0.5 text-stone-900">{{ $order->delivery_method?->label() ?? 'brak dostawy' }}</dd>
                    </div>

                    @if (filled($order->parcel_locker_code))
                        <div>
                            <dt class="text-xs text-stone-400">Paczkomat</dt>
                            <dd class="mt-0.5 text-stone-900">
                                {{ $order->parcel_locker_code }}
                                <span class="block text-xs text-stone-500">{{ $order->parcel_locker_address }}</span>
                            </dd>
                        </div>
                    @elseif (filled($order->ship_street))
                        <div>
                            <dt class="text-xs text-stone-400">Adres wysyłki</dt>
                            <dd class="mt-0.5 text-stone-900">
                                {{ trim($order->ship_street.' '.$order->ship_building_number.($order->ship_apartment_number ? '/'.$order->ship_apartment_number : '')) }}
                                <span class="block">{{ $order->ship_postal_code }} {{ $order->ship_city }}</span>
                            </dd>
                        </div>
                    @endif

                    @if ($order->hasShipment() || filled($order->shipment_error))
                        <div>
                            <dt class="text-xs text-stone-400">Przesyłka</dt>
                            <dd class="mt-0.5 text-stone-900">
                                @if (filled($order->shipment_error))
                                    <span class="block rounded-2xl bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ $order->shipment_error }}</span>
                                @elseif ($order->trackingUrl())
                                    <a href="{{ $order->trackingUrl() }}" target="_blank" rel="noopener"
                                        class="tabular-nums text-stone-700 underline decoration-amber-300 underline-offset-2">{{ $order->shipment_tracking_number }}</a>
                                @else
                                    <span class="text-stone-600">nadana, numeru jeszcze nie ma</span>
                                @endif
                            </dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-xs text-stone-400">Płatność</dt>
                        <dd class="mt-0.5 text-stone-900">
                            {{ $order->payment_method?->label() ?? '—' }}
                            @if (filled($order->payment_status))
                                <span class="block text-xs text-stone-500">status operatora: {{ $order->payment_status }}</span>
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-stone-400">Faktura</dt>
                        <dd class="mt-0.5 text-stone-900">
                            @if ($order->invoicePdfUrl())
                                <a href="{{ $order->invoicePdfUrl() }}" target="_blank" rel="noopener"
                                    class="text-stone-700 underline decoration-amber-300 underline-offset-2">{{ $order->invoice_number ?: 'PDF' }}</a>
                            @elseif ($order->isInvoicePending())
                                <span class="text-stone-600">w przygotowaniu</span>
                            @elseif ($order->invoice_status === \App\Enums\InvoiceStatus::Failed)
                                <span class="text-rose-600">nie powstała — sprzedawca musi ponowić</span>
                            @else
                                <span class="text-stone-400">nie wystawiono</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            @if (filled($order->note))
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Uwagi klienta</h2>
                    <p class="mt-3 whitespace-pre-line text-sm text-stone-600">{{ $order->note }}</p>
                </div>
            @endif

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to czytać</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">👀</span>
                        <span>Karta jest <span class="text-stone-700">wyłącznie do odczytu</span>. Cokolwiek trzeba tu zmienić, robi to sprzedawca w swoim panelu.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">↩️</span>
                        <span>Przy zwrocie ilość pokazuje stan <span class="text-stone-700">po zwrocie</span>, a obok widnieje ilość pierwotna — faktura sprzed zwrotu opiewa na tę drugą.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
