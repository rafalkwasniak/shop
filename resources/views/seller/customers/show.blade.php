<x-layouts.panel :title="$customer['name'] !== '' ? $customer['name'] : $customer['email']">
    <x-slot:heading>{{ $customer['name'] !== '' ? $customer['name'] : $customer['email'] }}</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: historia zamówień --}}
        <div class="space-y-6 lg:col-span-8">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-stone-900">Zamówienia</h2>
                        <p class="mt-1 text-sm text-stone-500">Od najnowszego. Kliknij, by przejść do szczegółów zamówienia.</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-600">{{ $orders->total() }} {{ trans_choice('{1}zamówienie|[2,4]zamówienia|[5,*]zamówień', $orders->total()) }}</span>
                </div>

                <ul class="mt-5 space-y-3">
                    @foreach ($orders as $order)
                        <li>
                            <a href="{{ route('seller.orders.show', $order) }}"
                                class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-stone-200 bg-white/70 p-4 transition hover:border-amber-300 hover:bg-white">
                                <div class="min-w-0">
                                    <p class="font-semibold text-stone-900">#{{ $order->number }}</p>
                                    <p class="mt-1 text-xs text-stone-400">
                                        {{ $order->created_at->format('d.m.Y, H:i') }}
                                        · {{ $order->items_count }} {{ trans_choice('{1}pozycja|[2,4]pozycje|[5,*]pozycji', $order->items_count) }}
                                        · {{ $order->delivery_method->label() }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $order->status->badgeClasses() }}">{{ $order->status->label() }}</span>
                                    <span class="font-semibold tabular-nums text-stone-900">{{ \App\Support\Money::pln($order->total_gross) }}</span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>

                @if ($orders->hasPages())
                    <div class="mt-6">
                        {{ $orders->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>

            {{-- Historia punktów — te same wpisy, które klient widzi w „Moim koncie". --}}
            @if ($loyalty !== null && $loyalty['history']->isNotEmpty())
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Historia punktów</h2>
                    <p class="mt-1 text-sm text-stone-500">Od najnowszych. Klient widzi te same wpisy na swoim koncie.</p>
                    <ul class="mt-5 space-y-2">
                        @foreach ($loyalty['history'] as $entry)
                            <li class="flex items-start justify-between gap-3 rounded-2xl border border-stone-200 bg-white/70 px-4 py-3 text-sm">
                                <div class="min-w-0">
                                    <p class="font-medium text-stone-800">
                                        {{ $entry->type->label() }}
                                        @if ($entry->order)
                                            · <a href="{{ route('seller.orders.show', $entry->order) }}" class="underline decoration-amber-300 underline-offset-2">#{{ $entry->order->number }}</a>
                                        @endif
                                    </p>
                                    <p class="mt-0.5 text-xs text-stone-400">
                                        {{ $entry->created_at->format('d.m.Y') }}
                                        @if ($entry->note)
                                            · {{ $entry->note }}
                                        @endif
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

            <div class="pt-2">
                {{-- „Wróć do listy" — ta sama formuła co na szczególe zamówienia. --}}
                <a href="{{ route('seller.customers.index') }}" class="text-sm font-medium text-stone-500 transition hover:text-stone-800">← Wróć do listy</a>
            </div>
        </div>

        {{-- Kolumna boczna: kontakt, liczby, działania --}}
        <aside class="space-y-6 lg:col-span-4">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Kontakt</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div>
                        <dt class="text-xs text-stone-400">E-mail</dt>
                        <dd class="break-words"><a href="mailto:{{ $customer['email'] }}" class="font-medium text-stone-700 underline decoration-amber-300 underline-offset-2">{{ $customer['email'] }}</a></dd>
                    </div>
                    @if (filled($customer['phone']))
                        <div>
                            <dt class="text-xs text-stone-400">Telefon</dt>
                            <dd class="font-medium text-stone-700">{{ $customer['phone'] }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($customer['has_account'])
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">Ma konto w sklepie</span>
                    @else
                        <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-500">Kupował jako gość</span>
                    @endif
                    @if ($customer['has_consent'])
                        <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800">Zgoda na wiadomości</span>
                    @endif
                </div>
            </div>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Podsumowanie</h2>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-stone-900">{{ \App\Support\Money::pln($customer['total_spent']) }}</p>
                <p class="mt-1 text-sm text-stone-500">wydane u Ciebie łącznie</p>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-stone-500">Zamówienia</dt>
                        <dd class="font-medium tabular-nums text-stone-800">{{ $customer['orders_count'] }}</dd>
                    </div>
                    @if ($customer['cancelled_count'] > 0)
                        {{-- Anulowane są w historii, ale nie w wydatkach — bez tej
                             linii liczby wyglądałyby na niezgodne. --}}
                        <div class="flex justify-between gap-3">
                            <dt class="text-stone-500">w tym anulowane</dt>
                            <dd class="font-medium tabular-nums text-stone-800">{{ $customer['cancelled_count'] }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-3">
                        <dt class="text-stone-500">Średnie zamówienie</dt>
                        <dd class="font-medium tabular-nums text-stone-800">{{ \App\Support\Money::pln($customer['average_order']) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-stone-500">Pierwszy zakup</dt>
                        <dd class="font-medium text-stone-800">{{ $customer['first_order_at']->format('d.m.Y') }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-stone-500">Ostatni zakup</dt>
                        <dd class="font-medium text-stone-800">{{ $customer['last_order_at']->format('d.m.Y') }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Punkty: saldo + ręczna korekta. Sprzedawca widzi też wartość w zł —
                 zasada „same punkty" dotyczy klienta, nie panelu. --}}
            @if ($loyalty !== null)
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Punkty</h2>
                    <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums {{ $loyalty['balance'] < 0 ? 'text-rose-700' : 'text-stone-900' }}">{{ $loyalty['balance'] }} pkt</p>
                    <p class="mt-1 text-sm text-stone-500">do wykorzystania · {{ \App\Support\Money::pln(max(0, $loyalty['balance']) * $shop->loyaltyPointValue()) }}</p>
                    @if ($loyalty['pending'] > 0)
                        <p class="mt-2 text-sm text-stone-500">Oczekujące: <span class="font-medium tabular-nums text-stone-700">{{ $loyalty['pending'] }} pkt</span></p>
                    @endif

                    <form method="POST" action="{{ route('seller.customers.points', ['email' => $customer['email']]) }}" class="mt-5 space-y-3 border-t border-stone-100 pt-4" novalidate data-validate>
                        @csrf
                        <p class="text-sm font-medium text-stone-700">Korekta punktów</p>
                        <div>
                            <label for="points" class="block text-xs text-stone-500">Liczba punktów — ujemna odejmuje</label>
                            <input id="points" name="points" type="text" inputmode="numeric" placeholder="np. 500 albo -200" required
                                value="{{ old('points') }}" data-msg-required="Podaj liczbę punktów, np. 500 albo -200."
                                class="mt-1 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            @error('points')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="note" class="block text-xs text-stone-500">Za co — klient zobaczy to w historii</label>
                            <input id="note" name="note" type="text" maxlength="120" placeholder="np. przeprosiny za opóźnienie" required
                                value="{{ old('note') }}" data-msg-required="Napisz krótko, za co ta korekta."
                                class="mt-1 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            @error('note')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit"
                            class="inline-flex rounded-2xl border border-stone-200 bg-white px-5 py-2.5 text-sm font-semibold text-stone-700 transition hover:bg-stone-100">
                            Zapisz korektę
                        </button>
                        <p class="text-xs text-stone-400">Dopisane punkty są do wykorzystania od razu. Odjęcie więcej niż saldo da saldo ujemne — wyrówna się kolejnymi punktami klienta.</p>
                    </form>
                </div>
            @endif

            @if ($shop->entitlement('discount_codes'))
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Kod dla klienta</h2>
                    <p class="mt-1 text-sm text-stone-500">Podziękuj za zakupy albo zachęć do kolejnych.</p>
                    <a href="{{ route('seller.discounts.create', $customer['customer'] !== null ? ['klient' => $customer['customer']->id] : ['jednorazowy' => 1]) }}"
                        class="mt-4 inline-flex rounded-2xl border border-stone-200 bg-white px-5 py-2.5 text-sm font-semibold text-stone-700 transition hover:bg-stone-100">
                        Wystaw kod
                    </a>
                    @if ($customer['customer'] === null)
                        <p class="mt-3 text-xs text-stone-400">Ten klient nie ma konta, więc kod nie będzie imienny — zadziała u każdego, kto go dostanie.</p>
                    @endif
                </div>
            @endif
        </aside>
    </div>
</x-layouts.panel>
