<x-layouts.panel title="Wiadomości do sprzedawców">
    <x-slot:heading>Wiadomości do sprzedawców</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-stone-900">Twoje wiadomości</h2>
                        <p class="mt-1 text-sm text-stone-500">Napisz do sprzedawców, którzy zgodzili się na treści handlowe od Kramio.</p>
                    </div>
                    <a href="{{ route('administrator.mailings.create') }}"
                        class="shrink-0 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                        Napisz wiadomość
                    </a>
                </div>

                @if ($mailings->isEmpty())
                    <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">📣</span>
                        <p class="mt-4 font-medium text-stone-700">Nie napisałeś jeszcze żadnej wiadomości</p>
                        <p class="mt-1 text-sm text-stone-500">Napisz pierwszą, wyślij próbkę do siebie i dopiero potem puść ją do sprzedawców.</p>
                    </div>
                @else
                    <ul class="mt-6 space-y-3">
                        @foreach ($mailings as $mailing)
                            <li class="relative rounded-2xl border border-stone-200 bg-white/70 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="{{ route('administrator.mailings.edit', $mailing) }}" class="break-words font-semibold text-stone-900 transition hover:text-amber-700">
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

                                    {{-- Dopóki kolejka nie opustoszeje, pokazujemy POSTĘP —
                                         bez tego długa wysyłka wygląda na zawieszoną. --}}
                                    @php($total = $mailing->messages_count)
                                    @php($delivered = $mailing->delivered_count)
                                    @php($inProgress = $mailing->isSent() && ($delivered + $mailing->failed_count) < $total)

                                    @if ($inProgress)
                                        <span class="shrink-0 rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800">{{ 'Wysyłam '.min($delivered + 1, $total).' z '.$total }}</span>
                                    @elseif ($mailing->isSent())
                                        <span class="shrink-0 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">{{ 'Wysłano '.$delivered.' '.trans_choice('{1}wiadomość|[2,4]wiadomości|[5,*]wiadomości', $delivered) }}</span>
                                    @else
                                        <span class="shrink-0 rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-500">Szkic</span>
                                    @endif
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-stone-100 pt-3">
                                    <a href="{{ route('administrator.mailings.edit', $mailing) }}"
                                        class="rounded-xl border border-stone-200 bg-white px-3 py-1.5 text-sm font-medium text-stone-700 transition hover:bg-stone-100">
                                        {{ $mailing->isSent() ? 'Podejrzyj' : 'Edytuj' }}
                                    </a>

                                    {{-- Wysłanej nie da się skasować — sprzedawcy mają ją
                                         w skrzynkach, więc zostaje jako zapis, co poszło. --}}
                                    @unless ($mailing->isSent())
                                        <div class="ml-auto">
                                            <livewire:confirm-action
                                                :key="'usun-szkic-'.$mailing->id"
                                                :action="route('administrator.mailings.destroy', $mailing)"
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
                        <div class="mt-6">{{ $mailings->onEachSide(1)->links() }}</div>
                    @endif
                @endif
            </div>
        </div>

        <aside class="space-y-6 lg:col-span-4">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Ze zgodą na oferty</h2>
                <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums text-stone-900">{{ $eligible }}</p>
                <p class="mt-1 text-sm text-stone-500">
                    {{ $eligible === 1 ? 'sprzedawca zgodził się' : 'sprzedawców zgodziło się' }} na treści handlowe od Kramio.
                </p>
                <p class="mt-3 text-xs text-stone-400">
                    Zgodę zaznacza sam sprzedawca — przy aktywacji konta albo później w profilu. Nie da się jej dodać za niego,
                    a wypisanie działa natychmiast.
                </p>
                <a href="{{ route('administrator.sellers.index', ['zgoda' => '1']) }}"
                    class="mt-4 inline-flex items-center rounded-xl border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700 shadow-sm transition hover:bg-stone-100">
                    Zobacz, kto się zgodził
                </a>
            </div>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Zanim naciśniesz wyślij</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">⚖️</span>
                        <span>To narzędzie służy <span class="font-medium text-stone-700">wyłącznie ofertom</span> — kodom, nowościom, zachętom do pakietu. Faktura, wygaśnięcie pakietu czy zmiana regulaminu idą własnymi ścieżkami do wszystkich.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">☑️</span>
                        <span>Odbiorców <span class="font-medium text-stone-700">zaznaczasz sam</span>. Zaznaczenie kogoś bez zgody nic nie da — lista i tak przechodzi przez zgody.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">⏱️</span>
                        <span><span class="font-medium text-stone-700">Bez karencji</span> — kolejną wiadomość piszesz i wysyłasz od ręki. Jedna wiadomość leci jednak tylko raz.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🔓</span>
                        <span>Każdy mail ma <span class="font-medium text-stone-700">link do wypisania się</span>, który działa natychmiast i bezterminowo.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
