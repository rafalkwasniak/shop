@props(['shop'])

{{--
    Podsumowanie dostawy i płatności sklepu jako czytelna „tabelka" (etykieta po
    lewej, koszt po prawej) — informacyjnie na karcie produktu, żeby klient znał
    koszt wysyłki i możliwe opcje jeszcze przed koszykiem. To dane sklepu, nie
    formularz: realny wybór i tak jest w kasie. Pokazujemy tylko realnie dostępne
    metody (te same warunki co kasa: `*Available()`). Koszt kuriera to koszt
    bazowy sklepu (koszyk zwykle ma więcej pozycji, więc nie liczymy per-produkt);
    gdy sklep ma próg darmowej dostawy — dopisujemy go jako notkę. Cały box znika,
    gdy sklep nie oferuje żadnej metody.
--}}

@php
    $deliveries = [];

    if ($shop->pickupAvailable()) {
        $deliveries[] = [
            'label' => \App\Enums\DeliveryMethod::Pickup->label(),
            'value' => 'Gratis',
            // Adres odbioru jako podpis — ta sama rola co „gratis od…" przy
            // wysyłce (dopowiedzenie warunku metody) i ta sama informacja, którą
            // kasa pokazuje przy płatności przy odbiorze. Bez tego jedyny wiersz
            // bez podpisu wybijał się z rytmu tabelki. Adres jest zawsze pełny:
            // pickupAvailable() wymaga kompletnego adresu sklepu.
            'note' => $shop->addressLine(),
        ];
    }

    if ($shop->courierAvailable()) {
        $courierCost = (float) ($shop->courier_cost ?? 0);
        $deliveries[] = [
            'label' => \App\Enums\DeliveryMethod::Courier->label(),
            'value' => $courierCost > 0 ? \App\Support\Money::pln($courierCost) : 'Gratis',
            'note' => ($courierCost > 0 && $shop->courier_free_from !== null)
                ? 'gratis od '.\App\Support\Money::pln((float) $shop->courier_free_from)
                : null,
        ];
    }

    if ($shop->parcelLockerAvailable()) {
        $lockerCost = (float) ($shop->parcel_locker_cost ?? 0);
        $deliveries[] = [
            'label' => \App\Enums\DeliveryMethod::ParcelLocker->label(),
            'value' => $lockerCost > 0 ? \App\Support\Money::pln($lockerCost) : 'Gratis',
            'note' => ($lockerCost > 0 && $shop->parcel_locker_free_from !== null)
                ? 'gratis od '.\App\Support\Money::pln((float) $shop->parcel_locker_free_from)
                : null,
        ];
    }

    if ($shop->courierCodAvailable()) {
        $courierCodCost = (float) ($shop->courier_cod_cost ?? 0);
        $deliveries[] = [
            'label' => \App\Enums\DeliveryMethod::CourierCod->label(),
            'value' => $courierCodCost > 0 ? \App\Support\Money::pln($courierCodCost) : 'Gratis',
            'note' => ($courierCodCost > 0 && $shop->courier_cod_free_from !== null)
                ? 'gratis od '.\App\Support\Money::pln((float) $shop->courier_cod_free_from)
                : null,
        ];
    }

    if ($shop->parcelLockerCodAvailable()) {
        $lockerCodCost = (float) ($shop->parcel_locker_cod_cost ?? 0);
        $deliveries[] = [
            'label' => \App\Enums\DeliveryMethod::ParcelLockerCod->label(),
            'value' => $lockerCodCost > 0 ? \App\Support\Money::pln($lockerCodCost) : 'Gratis',
            'note' => ($lockerCodCost > 0 && $shop->parcel_locker_cod_free_from !== null)
                ? 'gratis od '.\App\Support\Money::pln((float) $shop->parcel_locker_cod_free_from)
                : null,
        ];
    }

    $payments = [];

    // Płatność online na czele (preferowana), spójnie z kolejnością w kasie.
    // Z nazwami bramek („Płatność online (Paynow lub Przelewy24)") — kupujący
    // często wybiera sklep po tym, czy zapłaci „swoim" operatorem.
    if ($shop->onlinePaymentsEnabled()) {
        $payments[] = \App\Enums\PaymentMethod::Online->label().' ('
            .collect($shop->activePaymentProviders())->map->label()->join(', ', ' lub ').')';
    }

    if ($shop->bankTransferAvailable()) {
        $payments[] = \App\Enums\PaymentMethod::BankTransfer->label();
    }

    if ($shop->payOnPickupAvailable()) {
        $payments[] = \App\Enums\PaymentMethod::PayOnPickup->label();
    }

    // Pobranie NIE jest wyborem klienta (wynika z dostawy), ale jako sposób
    // zapłaty musi się tu pojawić — inaczej sklep sprzedający wyłącznie za
    // pobraniem miałby pustą rubrykę „Płatność".
    if ($shop->cashOnDeliveryAvailable()) {
        $payments[] = \App\Enums\PaymentMethod::CashOnDelivery->label();
    }
@endphp

@if (count($deliveries) || count($payments))
    <div {{ $attributes->merge(['class' => 'st-card st-border rounded-3xl border p-6 text-left']) }}>
        <h2 class="st-brand st-box-title">Dostawa i płatność</h2>

        @if (count($deliveries))
            <p class="mt-4 text-xs uppercase tracking-wide opacity-50">Dostawa</p>
            <dl class="mt-2 space-y-2">
                @foreach ($deliveries as $row)
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-sm opacity-90">
                            {{ $row['label'] }}
                            @if ($row['note'])
                                <span class="block text-xs opacity-60">{{ $row['note'] }}</span>
                            @endif
                        </dt>
                        <dd class="whitespace-nowrap text-sm font-medium">{{ $row['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        @if (count($payments))
            <p class="mt-5 text-xs uppercase tracking-wide opacity-50">Płatność</p>
            <ul class="mt-2 space-y-2">
                @foreach ($payments as $label)
                    <li class="text-sm opacity-90">{{ $label }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
