<x-layouts.panel title="Wiadomości do klientów">
    <x-slot:heading>Wiadomości do klientów</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: szkice i historia wysyłek --}}
        <div class="lg:col-span-8">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-stone-900">Twoje wiadomości</h2>
                        <p class="mt-1 text-sm text-stone-500">Napisz do klientów, którzy zgodzili się na wiadomości od Twojego sklepu.</p>
                    </div>
                    @if ($allowed)
                        <a href="{{ route('seller.mailings.create') }}"
                            class="shrink-0 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                            Napisz wiadomość
                        </a>
                    @endif
                </div>

                @unless ($allowed)
                    <div class="mt-6">
                        <x-seller.locked-feature feature="bulk_mail" icon="📣" title="Wiadomości do klientów" :shop="$shop">
                            Napisz o nowościach albo promocji do klientów, którzy się na to zgodzili — z kartą promowanego
                            produktu i bez zewnętrznych narzędzi.
                        </x-seller.locked-feature>
                    </div>
                @elseif ($mailings->isEmpty())
                    <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">📣</span>
                        <p class="mt-4 font-medium text-stone-700">Nie napisałeś jeszcze żadnej wiadomości</p>
                        <p class="mt-1 text-sm text-stone-500">Napisz pierwszą, wyślij próbkę do siebie i dopiero potem puść ją do klientów.</p>
                    </div>
                @else
                    <ul class="mt-6 space-y-3">
                        @foreach ($mailings as $mailing)
                            <li class="relative rounded-2xl border border-stone-200 bg-white/70 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="{{ route('seller.mailings.edit', $mailing) }}" class="break-words font-semibold text-stone-900 transition hover:text-amber-700">
                                            {{ $mailing->subject }}
                                        </a>
                                        <p class="mt-1 text-xs text-stone-400">
                                            @if ($mailing->isSent())
                                                Wysłano {{ $mailing->sent_at->format('d.m.Y, H:i') }}
                                                · {{ $mailing->recipients_count }} {{ $mailing->recipients_count === 1 ? 'odbiorca' : 'odbiorców' }}
                                                @if ($mailing->failed_count > 0)
                                                    · <span class="text-rose-600">{{ $mailing->failed_count }} nie dotarło</span>
                                                @endif
                                            @else
                                                Szkic · utworzony {{ $mailing->created_at->format('d.m.Y') }}
                                                @if ($mailing->test_sends > 0)
                                                    · sprawdzony {{ $mailing->test_sends }} {{ $mailing->test_sends === 1 ? 'raz' : 'razy' }}
                                                @endif
                                            @endif
                                        </p>
                                    </div>
                                    {{-- Stan wysyłki: dopóki kolejka nie opustoszeje,
                                         pokazujemy POSTĘP na bursztynowo („Wysyłam
                                         153 z 350"), bo przy tempie 10/min wysyłka
                                         trwa długo i bez tego wygląda na zawieszoną. --}}
                                    {{-- Kampanie sprzed wprowadzenia powiązania maili
                                         z mailingiem nie mają wierszy w outboxie —
                                         dla nich liczbą jest migawka `recipients_count`. --}}
                                    @php($total = $mailing->messages_count > 0 ? $mailing->messages_count : (int) $mailing->recipients_count)
                                    @php($delivered = $mailing->messages_count > 0 ? $mailing->delivered_count : (int) $mailing->recipients_count)
                                    @php($inProgress = $mailing->isSent() && ($delivered + $mailing->failed_count) < $total)

                                    @if ($inProgress)
                                        {{-- `delivered + 1` = numer wiadomości W TOKU.
                                             „Wysyłam 0 z 350" sugerowałoby, że nic się
                                             nie dzieje. --}}
                                        <span class="shrink-0 rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800">{{ 'Wysyłam '.min($delivered + 1, $total).' z '.$total }}</span>
                                    @elseif ($mailing->isSent())
                                        <span class="shrink-0 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">{{ 'Wysłano '.$delivered.' '.trans_choice('{1}wiadomość|[2,4]wiadomości|[5,*]wiadomości', $delivered) }}</span>
                                    @else
                                        <span class="shrink-0 rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-500">Szkic</span>
                                    @endif
                                </div>

                                {{-- Akcje jak przy kodach rabatowych: edycja z lewej,
                                     usuwanie z prawej. Wysłanej wiadomości nie da się
                                     skasować — klienci mają ją w skrzynkach, więc
                                     zostaje jako zapis, co do nich poszło. --}}
                                <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-stone-100 pt-3">
                                    <a href="{{ route('seller.mailings.edit', $mailing) }}"
                                        class="rounded-xl border border-stone-200 bg-white px-3 py-1.5 text-sm font-medium text-stone-700 transition hover:bg-stone-100">
                                        {{ $mailing->isSent() ? 'Podejrzyj' : 'Edytuj' }}
                                    </a>

                                    @unless ($mailing->isSent())
                                        <div class="ml-auto">
                                            <livewire:confirm-action
                                                :key="'usun-szkic-'.$mailing->id"
                                                :action="route('seller.mailings.destroy', $mailing)"
                                                :title="'Usunąć szkic '.$mailing->subject.'?'"
                                                :lines="['Szkic nie został wysłany, więc nikt go nie dostał.', 'Zniknie na zawsze i nie da się tego cofnąć.']"
                                                label="Usuń szkic"
                                                text="Usuń"
                                                icon="none"
                                                trigger-class="text-sm font-medium text-rose-700 transition hover:text-rose-800" />
                                        </div>
                                    @endunless
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    @if ($mailings->hasPages())
                        <div class="mt-6">
                            {{ $mailings->onEachSide(1)->links() }}
                        </div>
                    @endif
                @endunless
            </div>
        </div>

        {{-- Kolumna boczna: kto dostanie i kiedy można wysłać.
             ZAWSZE widoczna — przy zablokowanym pakiecie zostawała pusta, więc ekran
             wyglądał na zepsuty, a nie na zamknięty (Rafał wyłapał niespójność z
             Kodami rabatowymi, gdzie prawa kolumna zostaje). --}}
        <aside class="space-y-6 lg:col-span-4">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Odbiorcy</h2>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-stone-900">{{ $recipients }}</p>
                <p class="mt-1 text-sm text-stone-500">
                    {{ $recipients === 1 ? 'klient zgodził się' : 'klientów zgodziło się' }} na wiadomości od Twojego sklepu.
                </p>
                <p class="mt-3 text-xs text-stone-400">
                    Zgodę zaznacza sam klient — przy zakładaniu konta albo później w swoim profilu. Nie da się jej dodać za niego,
                    a wypisanie działa natychmiast.
                </p>
            </div>

            @if ($allowed)
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Kiedy możesz wysłać</h2>
                    @if ($blockedUntil !== null)
                        <p class="mt-3 text-sm font-medium text-amber-700">Od {{ $blockedUntil->format('d.m.Y') }}</p>
                        <p class="mt-1 text-xs text-stone-500">
                            Klienci dostają najwyżej jedną wiadomość na {{ config('bulk_mail.cooldown_days') }} dni. Szkic możesz pisać i testować już teraz.
                        </p>
                    @else
                        <p class="mt-3 text-sm font-medium text-emerald-700">Możesz wysłać teraz</p>
                        <p class="mt-1 text-xs text-stone-500">
                            Po wysyłce kolejna wiadomość odblokuje się po {{ config('bulk_mail.cooldown_days') }} dniach.
                        </p>
                    @endif
                </div>
            @else
                {{-- Odpowiednik „Jak to działa" z Kodów rabatowych: przy blokadzie
                     tłumaczymy zasady, żeby prawa kolumna niosła treść, a nie ciszę. --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Jak to działa</h2>
                    <ul class="mt-4 space-y-3 text-sm text-stone-500">
                        <li class="flex gap-3">
                            <span class="mt-0.5 shrink-0 text-amber-500">✍️</span>
                            <span>Piszesz wiadomość jak zwykły list, a <span class="font-medium text-stone-700">próbki do siebie</span> wysyłasz bez limitu — dopiero potem puszczasz ją do klientów.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-0.5 shrink-0 text-amber-500">🛍️</span>
                            <span>Możesz dołączyć <span class="font-medium text-stone-700">kartę promowanego produktu</span> ze zdjęciem i ceną — klient klika i trafia wprost na jego stronę.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-0.5 shrink-0 text-amber-500">📅</span>
                            <span>Jedna wiadomość na {{ config('bulk_mail.cooldown_days') }} dni — dla spokoju Twoich klientów i dostarczalności Twoich maili.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-0.5 shrink-0 text-amber-500">🔓</span>
                            <span>Każdy mail ma <span class="font-medium text-stone-700">link do wypisania się</span>, który działa natychmiast i bezterminowo.</span>
                        </li>
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</x-layouts.panel>
