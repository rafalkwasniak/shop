<x-layouts.panel :title="'Sprzedawca: '.trim($seller->name.' '.$seller->surname)">
    <x-slot:actions>
        <a href="{{ route('administrator.sellers.index') }}"
            class="rounded-full bg-white/70 px-4 py-1.5 text-sm font-medium text-stone-600 backdrop-blur transition hover:bg-white">
            ← Wróć do listy
        </a>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8 space-y-6">
            {{-- Konto --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-stone-900">Konto</h2>
                    @if ($seller->isActivated())
                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700">aktywne</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">czeka na aktywację</span>
                    @endif
                </div>

                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-stone-400">E-mail</dt>
                        <dd class="mt-1 break-words text-sm text-stone-800">
                            <a href="mailto:{{ $seller->email }}" class="underline decoration-amber-300 underline-offset-2">{{ $seller->email }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-stone-400">Telefon</dt>
                        <dd class="mt-1 text-sm text-stone-800">
                            @if ($seller->phone)
                                <a href="tel:{{ app(\App\Services\PhoneService::class)->href($seller->phone) }}" class="underline decoration-amber-300 underline-offset-2">{{ $seller->phone }}</a>
                            @else
                                <span class="text-stone-400">—</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-stone-400">Rejestracja</dt>
                        <dd class="mt-1 text-sm tabular-nums text-stone-800">{{ $seller->created_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-stone-400">Aktywacja</dt>
                        <dd class="mt-1 text-sm tabular-nums text-stone-800">
                            {{ $seller->email_verified_at?->format('d.m.Y H:i') ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-stone-400">Ostatnie logowanie</dt>
                        <dd class="mt-1 text-sm tabular-nums text-stone-800">
                            @if ($seller->last_login_at)
                                {{ $seller->last_login_at->format('d.m.Y H:i') }}
                            @else
                                <span class="text-stone-400">—</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @unless ($seller->isActivated())
                    {{-- Jedyna akcja na tym ekranie. Sprzedawca ma własny przycisk na
                         ekranie „Sprawdź skrzynkę", ale ten znika razem z zamkniętą
                         kartą — a mail potrafi wylądować w spamie. --}}
                    <form method="POST" action="{{ route('administrator.sellers.activation', $seller) }}" class="mt-6 border-t border-stone-100 pt-5">
                        @csrf
                        <p class="text-sm text-stone-600">
                            To konto nie ma jeszcze własnego hasła. Wyślij link aktywacyjny ponownie — jest ważny 24 godziny.
                        </p>
                        <button type="submit"
                            class="mt-4 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                            Wyślij link aktywacyjny ponownie
                        </button>
                    </form>
                @endunless
            </div>

            {{-- Sklep --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Sklep</h2>

                @if ($seller->shop)
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <p class="text-lg font-medium text-stone-900">{{ $seller->shop->name }}</p>
                        <span class="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700">{{ $seller->shop->packageName() }}</span>
                        <span @class([
                            'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium',
                            'bg-emerald-100 text-emerald-700' => $seller->shop->status === \App\Enums\ShopStatus::Active,
                            'bg-stone-100 text-stone-500' => $seller->shop->status !== \App\Enums\ShopStatus::Active,
                        ])>{{ $seller->shop->status->label() }}</span>
                    </div>

                    <p class="mt-1 break-words text-sm text-stone-500">{{ $seller->shop->host() }}</p>

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <a href="https://{{ $seller->shop->host() }}" target="_blank" rel="noopener"
                            class="inline-flex items-center gap-1 rounded-xl border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700 shadow-sm transition hover:bg-stone-100">
                            Zobacz sklep
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3 text-stone-400" aria-hidden="true">
                                <path d="M14 4h6v6M20 4l-8 8M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5" />
                            </svg>
                        </a>
                        <a href="{{ route('administrator.shops.edit', $seller->shop) }}"
                            class="inline-flex items-center rounded-xl border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700 shadow-sm transition hover:bg-stone-100">
                            Zarządzaj pakietem i uprawnieniami
                        </a>
                    </div>

                    @if ($seller->shop->deletion_scheduled_at)
                        <p class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700">
                            Sprzedawca zlecił usunięcie sklepu — zniknie razem z tym kontem
                            {{ $seller->shop->deletion_scheduled_at->format('d.m.Y') }}.
                        </p>
                    @endif
                @else
                    {{-- Rejestracja zawsze zakłada sklep, więc to stan nienormalny, a nie
                         etap onboardingu — mówimy o tym wprost, zamiast pokazać pustkę. --}}
                    <p class="mt-3 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        To konto nie ma sklepu, choć rejestracja zawsze go zakłada. Warto sprawdzić, skąd się wzięło.
                    </p>
                @endif
            </div>

            {{-- Zgody --}}
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Zgody</h2>
                <p class="mt-1 text-sm text-stone-500">Dowód na wypadek pytania „na co dokładnie i kiedy ta osoba się zgodziła”.</p>

                <h3 class="mt-5 text-xs uppercase tracking-wide text-stone-400">Dokumenty</h3>
                @if ($seller->consents->isEmpty())
                    <p class="mt-2 text-sm text-stone-500">Brak zapisanych akceptacji.</p>
                @else
                    <ul class="mt-2 divide-y divide-stone-100">
                        @foreach ($seller->consents->sortByDesc('accepted_at') as $consent)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                                <div>
                                    <p class="text-sm font-medium text-stone-800">
                                        {{ $consent->document?->type->label() ?? 'Dokument usunięty' }}
                                        @if ($consent->document)
                                            <span class="text-stone-400">wersja {{ $consent->document->version }}</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-stone-400">IP {{ $consent->ip_address ?? 'nieznane' }}</p>
                                </div>
                                <span class="text-sm tabular-nums text-stone-600">{{ $consent->accepted_at?->format('d.m.Y H:i') ?? '—' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <h3 class="mt-6 text-xs uppercase tracking-wide text-stone-400">Informacje handlowe od {{ config('app.name') }}</h3>
                <ul class="mt-2 divide-y divide-stone-100">
                    @foreach (\App\Enums\ConsentChannel::cases() as $channel)
                        @php($record = $seller->marketingConsents->firstWhere('channel', $channel))
                        <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                            <div>
                                <p class="text-sm font-medium text-stone-800">{{ $channel->label() }}</p>
                                @if ($record)
                                    <p class="text-xs text-stone-400">
                                        treść w wersji {{ $record->version ?? 'nieznanej' }} · IP {{ $record->ip_address ?? 'nieznane' }}
                                    </p>
                                @else
                                    {{-- Brak wiersza i wycofanie to dwa różne stany: pierwszy znaczy
                                         „nigdy nie pytany albo odmówił", drugi „zgodził się i się
                                         rozmyślił". Różnica jest dowodowa, więc nie zlewamy jej. --}}
                                    <p class="text-xs text-stone-400">nigdy nie wyraził zgody</p>
                                @endif
                            </div>

                            @if ($record?->isActive())
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                    czynna od {{ $record->granted_at?->format('d.m.Y') }}
                                </span>
                            @elseif ($record)
                                <span class="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-500">
                                    wycofana {{ $record->revoked_at?->format('d.m.Y') }}
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-500">brak</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Czego tu nie ma</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">✍️</span>
                        <span><span class="text-stone-700">Edycji danych sprzedawcy</span> — zmienia je on sam w swoim profilu. Poprawka z tej strony nie zostawiałaby śladu, kto jej dokonał.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">📣</span>
                        <span><span class="text-stone-700">Wysyłki wiadomości</span> — zgody już się zbierają, samo narzędzie to osobny krok.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-rose-500">🗑️</span>
                        <span><span class="text-stone-700">Usunięcia konta</span> — kasuje się je razem ze sklepem, w konsoli sklepu. Konto bez sklepu nie zostawia sieroty.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
