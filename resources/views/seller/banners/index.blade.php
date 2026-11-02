{{-- Wygląd → Banery. Kolejność przeciąganiem — reużywamy mechanizmu stron
     „Informacji" (resources/js/page-order.js czyta atrybuty data-page-*), więc
     bez nowego JS i bez przebudowy front-endu. --}}
<x-layouts.panel title="Banery">
    <x-slot:heading>Banery na stronie głównej</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-stone-900">Banery</h2>
                        <p class="mt-1 text-sm text-stone-500">
                            Pokazują się pod nazwą sklepu na stronie głównej. Jeden baner to obrazek, kilka — pokaz przewijany automatycznie.
                            Wykorzystano {{ $banners->count() }} z {{ $limit }}.
                        </p>
                    </div>
                    @if ($limitReached)
                        <span class="shrink-0 rounded-2xl border border-stone-200 bg-stone-100 px-5 py-2.5 text-sm font-semibold text-stone-400" title="Limit banerów w Twoim pakiecie">Dodaj baner</span>
                    @else
                        <a href="{{ route('seller.banners.create') }}"
                            class="shrink-0 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                            Dodaj baner
                        </a>
                    @endif
                </div>

                @if ($banners->isEmpty())
                    <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">🖼️</span>
                        <p class="mt-4 font-medium text-stone-700">Brak banerów</p>
                        <p class="mt-1 text-sm text-stone-500">Dodaj pierwszy — pojawi się na stronie głównej sklepu, pod jego nazwą.</p>
                    </div>
                @else
                    <ul data-page-list data-reorder-url="{{ route('seller.banners.reorder') }}" class="mt-6 space-y-2">
                        @foreach ($banners as $banner)
                            @php
                                $withinLimit = $loop->index < $limit;
                                $status = $banner->statusLabel($withinLimit);
                                $visible = str_starts_with($status, 'Widoczny');
                            @endphp
                            <li data-page-item data-id="{{ $banner->id }}" draggable="true"
                                class="group flex items-center gap-3 rounded-2xl border border-stone-200 bg-white/80 px-3 py-3 shadow-sm transition">
                                <span data-page-handle aria-hidden="true"
                                    class="shrink-0 cursor-grab select-none px-1 text-lg leading-none text-stone-300 transition group-hover:text-stone-400">⣿</span>

                                <img src="{{ $banner->thumbnailUrl() }}" alt="" class="h-14 w-24 shrink-0 rounded-xl object-cover {{ $visible ? '' : 'opacity-50' }}">

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="truncate font-medium text-stone-800">{{ $banner->alt }}</span>
                                        <span class="rounded-full {{ $visible ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-500' }} px-2 py-0.5 text-[11px] font-medium">{{ $status }}</span>
                                    </div>
                                    <p class="mt-0.5 truncate text-xs text-stone-400">{{ $banner->link_url ?: 'Bez linku' }}</p>
                                </div>

                                <div class="flex shrink-0 items-center gap-3">
                                    <a href="{{ route('seller.banners.edit', $banner) }}"
                                        class="rounded-xl border border-stone-200 bg-white px-3 py-1.5 text-sm font-medium text-stone-700 transition hover:bg-stone-100">
                                        Edytuj
                                    </a>
                                    <livewire:confirm-action
                                        :key="'usun-baner-'.$banner->id"
                                        :action="route('seller.banners.destroy', $banner)"
                                        title="Usunąć ten baner?"
                                        :lines="['Zniknie ze strony głównej razem z obrazkiem.', 'Tej operacji nie da się cofnąć.']"
                                        label="Usuń baner"
                                        text="Usuń"
                                        icon="none"
                                        :inline="true"
                                        trigger-class="text-sm font-medium text-rose-700 transition hover:text-rose-800" />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-4 text-xs text-stone-400">Przeciągnij baner za uchwyt, aby zmienić kolejność. Pierwszy wyznacza wysokość pokazu — kolejne dopasowują się do niego.</p>
                @endif
            </div>

            @if ($banners->count() > 1)
                <form method="POST" action="{{ route('seller.banners.interval') }}" class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    @csrf
                    <h2 class="font-semibold text-stone-900">Czas wyświetlania</h2>
                    <p class="mt-1 text-sm text-stone-500">Co ile sekund pokaz przechodzi do następnego banera. Zatrzymuje się, gdy klient najedzie na baner albo go dotknie.</p>
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <label for="banner_interval" class="sr-only">Czas wyświetlania</label>
                        <select id="banner_interval" name="banner_interval"
                            class="rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            @foreach (range(config('shop.banners.interval_min'), config('shop.banners.interval_max')) as $seconds)
                                <option value="{{ $seconds }}" @selected(old('banner_interval', $shop->bannerInterval()) == $seconds)>{{ $seconds }} s</option>
                            @endforeach
                        </select>
                        <button type="submit"
                            class="rounded-2xl border border-stone-200 bg-white px-5 py-3 text-sm font-medium text-stone-700 transition hover:bg-stone-100">
                            Zapisz
                        </button>
                    </div>
                    @error('banner_interval')
                        <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </form>
            @endif
        </div>

        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Wskazówki</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">📐</span>
                        <span>Wgrywaj obrazki o <span class="font-medium text-stone-700">tych samych proporcjach</span> — zalecany rozmiar to <span class="font-medium text-stone-700">1920 × 540 px</span>. Wysokość pokazu wyznacza pierwszy baner.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🔤</span>
                        <span>Unikaj drobnego tekstu w grafice — ten sam obrazek widać na telefonie, gdzie napis zrobi się nieczytelny.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">📅</span>
                        <span>Baner promocyjny może mieć daty <span class="font-medium text-stone-700">od–do</span> — sam pojawi się i zniknie.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🙈</span>
                        <span>Ukryty baner zostaje na liście — możesz go włączyć w każdej chwili, bez wgrywania od nowa.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
