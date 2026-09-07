<div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
    <div class="flex items-center justify-between gap-3">
        <h2 class="font-semibold text-stone-900">Status</h2>
        <span class="inline-flex rounded-full px-3 py-1 text-sm font-medium {{ $order->status->badgeClasses() }}">{{ $order->status->label() }}</span>
    </div>

    {{-- Oś czasu: „Złożone" (created_at, szare) → status początkowy → kolejne
         przejścia. Status początkowy dokładamy ręcznie, bo nie ma go wśród
         zdarzeń — zamówienie się z nim urodziło, nikt go nie zmieniał. Bez tego
         oś zjadałaby pierwszy status i zaczynała od pierwszej ZMIANY. --}}
    <ol class="mt-4 space-y-3 border-l border-stone-200 pl-4">
        <li class="relative">
            <span class="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full bg-stone-300"></span>
            <p class="text-sm font-medium text-stone-700">Złożone</p>
            <p class="text-xs text-stone-400">{{ $order->created_at->format('d.m.Y, H:i') }}</p>
        </li>
        <li class="relative">
            <span class="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full bg-amber-400"></span>
            <p class="text-sm font-medium text-stone-700">{{ $initialStatus->label() }}</p>
            <p class="text-xs text-stone-400">{{ $order->created_at->format('d.m.Y, H:i') }}</p>
        </li>
        @foreach ($events as $event)
            <li class="relative">
                <span class="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full bg-amber-400"></span>
                <p class="text-sm font-medium text-stone-700">{{ $event->to_status->label() }}</p>
                {{-- Podpis: „kto to zrobil" to pierwsze pytanie, gdy w panelu
                     pracuje wiecej niz jedna osoba. `Automatycznie` znaczy
                     webhook platnosci albo cron — nie brak danych. --}}
                <p class="text-xs text-stone-400">{{ $event->created_at->format('d.m.Y, H:i') }} · {{ $event->authorLabel() }}</p>
                @if (filled($event->note))
                    <p class="mt-0.5 text-xs italic text-stone-500">„{{ $event->note }}"</p>
                @endif
            </li>
        @endforeach
    </ol>

    {{-- Ścieżka statusów TEGO zamówienia, w kolejności realizacji. Wynika z
         metody płatności i dostawy, więc statusy spoza niej nie są chowane —
         one dla tego zamówienia po prostu nie istnieją.

         Język wizualny: WYPEŁNIONE = stan (jak plakietki w całym panelu),
         OBRYS = akcja do kliknięcia. Dlatego ptaszek nosi wyłącznie status
         bieżący; propozycja nigdy nie może wyglądać na ustawioną. --}}
    @if ($canChange && $confirmingCancel)
        {{-- Potwierdzenie w miejscu: podmienia listę statusów, więc nie da się
             anulować „przy okazji". Mówi wprost, co się stanie i że nie ma odwrotu. --}}
        <div class="mt-5 border-t border-stone-100 pt-4">
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
                <p class="font-medium text-rose-900">Anulować zamówienie #{{ $order->number }}?</p>
                <ul class="mt-2 space-y-1 text-xs text-rose-800">
                    <li>• Tej operacji <strong>nie da się cofnąć</strong> — zamówienie zostanie zamrożone.</li>
                    <li>• Produkty wrócą na stan magazynowy.</li>
                    <li>• Kupujący dostanie maila z informacją o anulowaniu.</li>
                </ul>

                <label for="cancel-reason" class="mt-3 block text-xs font-medium text-rose-900">Powód anulowania (opcjonalnie)</label>
                <textarea
                    id="cancel-reason"
                    wire:model="cancelReason"
                    rows="2"
                    placeholder="Np. Brak towaru w magazynie"
                    class="mt-1 w-full rounded-2xl border border-rose-200 bg-white px-3 py-2 text-sm text-stone-700 placeholder:text-stone-400 focus:border-rose-400 focus:ring-rose-400"
                ></textarea>
                <p class="mt-1 text-xs text-rose-700">Powód znajdzie się w mailu do kupującego.</p>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        type="button"
                        wire:click="cancel"
                        class="inline-flex items-center rounded-full bg-rose-600 px-4 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-rose-700"
                    >Tak, anuluj zamówienie</button>
                    <button
                        type="button"
                        wire:click="dismissCancel"
                        class="inline-flex items-center rounded-full border border-stone-200 bg-white px-4 py-1.5 text-sm font-medium text-stone-600 transition hover:bg-stone-50"
                    >Nie, wróć</button>
                </div>
            </div>
        </div>
    @elseif ($canChange && $pending)
        {{-- Potwierdzenie w miejscu, bliźniacze do anulowania: klik w status nie
             zmienia go od razu — najpierw ta karta mówi wprost, co się stanie, i
             dopiero tu sprzedawca dopisuje wiadomość do kupującego. Amber (nie
             rose): to normalny krok realizacji, nie operacja nieodwracalna. --}}
        <div class="mt-5 border-t border-stone-100 pt-4">
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                <p class="font-medium text-amber-900">Zmienić status na „{{ $pending->label() }}”?</p>
                <ul class="mt-2 space-y-1 text-xs text-amber-800">
                    <li>• Kupujący dostanie maila o zmianie statusu.</li>
                    @if ($pending !== $suggested)
                        <li>• To <strong>nie jest kolejny krok</strong> tego zamówienia.</li>
                    @endif
                </ul>

                {{-- Uwaga: ta wiadomość NIE jest wewnętrzna — leci w mailu do kupującego.
                     Podpis musi to mówić wprost, inaczej sprzedawca wpisze tu coś, czego
                     nigdy nie chciał pokazać klientowi. --}}
                <label for="status-note" class="mt-3 block text-xs font-medium text-amber-900">Wiadomość do kupującego (opcjonalnie)</label>
                <textarea
                    id="status-note"
                    wire:model="note"
                    rows="2"
                    placeholder="Np. Paczkę nadam jeszcze dziś"
                    class="mt-1 w-full rounded-2xl border border-amber-200 bg-white px-3 py-2 text-sm text-stone-700 placeholder:text-stone-400 focus:border-amber-400 focus:ring-amber-400"
                ></textarea>
                <p class="mt-1 text-xs text-amber-700">Ta wiadomość znajdzie się w treści maila.</p>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        type="button"
                        wire:click="changeTo('{{ $pending->value }}')"
                        class="inline-flex items-center rounded-full bg-amber-600 px-4 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-amber-700"
                    >Tak, zmień status</button>
                    <button
                        type="button"
                        wire:click="dismissChange"
                        class="inline-flex items-center rounded-full border border-stone-200 bg-white px-4 py-1.5 text-sm font-medium text-stone-600 transition hover:bg-stone-50"
                    >Nie, wróć</button>
                </div>
            </div>
        </div>
    @elseif ($canChange)
        <div class="mt-5 border-t border-stone-100 pt-4">
            <label class="text-xs font-medium uppercase tracking-wide text-stone-400">Zmień status</label>
            <p class="mt-1 text-xs text-stone-400">Każda zmiana statusu wysyła kupującemu maila — najpierw poprosimy o potwierdzenie.</p>

            <div class="mt-3 space-y-1.5">
                @foreach ($statuses as $status)
                    @if ($status === $order->status)
                        <div aria-current="step" class="flex items-center gap-2 rounded-2xl bg-amber-600 px-4 py-2 text-sm font-medium text-white">
                            <span aria-hidden="true">✓</span>
                            <span>{{ $status->label() }}</span>
                            <span class="ml-auto text-xs font-normal text-amber-100">teraz</span>
                        </div>
                    @else
                        <button
                            type="button"
                            wire:click="askChange('{{ $status->value }}')"
                            @class([
                                'flex w-full items-center gap-2 rounded-2xl border px-4 py-2 text-sm transition',
                                'border-amber-300 bg-amber-50 font-medium text-amber-900 hover:bg-amber-100' => $status === $suggested,
                                'border-stone-200 bg-white/70 text-stone-500 hover:bg-stone-50 hover:text-stone-700' => $status !== $suggested,
                            ])
                        >
                            <span>{{ $status->label() }}</span>
                            @if ($status === $suggested)
                                <span class="ml-auto text-xs font-normal text-amber-600">zalecany</span>
                            @endif
                        </button>
                    @endif
                @endforeach
            </div>

            <div class="mt-3 border-t border-stone-100 pt-3">
                <button
                    type="button"
                    wire:click="askCancel"
                    class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-4 py-1.5 text-sm font-medium text-rose-700 transition hover:bg-rose-100"
                >Anuluj zamówienie</button>
            </div>
        </div>
    @else
        <div class="mt-5 border-t border-stone-100 pt-4">
            <p class="text-sm text-stone-500">Zamówienie anulowane — statusu nie da się już zmienić. Zostaje w historii jako ślad, że tak było.</p>
        </div>
    @endif
</div>
