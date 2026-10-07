<x-layouts.panel title="Informacje">
    <x-slot:heading>Informacje</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-stone-900">Strony sklepu</h2>
                        <p class="mt-1 text-sm text-stone-500">Regulamin i własne strony (np. dostawa, zwroty, o sklepie). Kolejność ustalasz przeciąganiem — ta sama w menu i w stopce sklepu.</p>
                    </div>
                    <a href="{{ route('seller.pages.create') }}"
                        class="shrink-0 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">
                        Dodaj stronę
                    </a>
                </div>

                @if ($pages->isEmpty())
                    <div class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 px-6 py-12 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-100 text-2xl">📄</span>
                        <p class="mt-4 font-medium text-stone-700">Brak stron</p>
                        <p class="mt-1 text-sm text-stone-500">Dodaj pierwszą stronę — pojawi się w menu i stopce sklepu.</p>
                    </div>
                @else
                    <ul data-page-list data-reorder-url="{{ route('seller.pages.reorder') }}" class="mt-6 space-y-2">
                        @foreach ($pages as $page)
                            <li data-page-item data-id="{{ $page->id }}" draggable="true"
                                class="group flex items-center gap-3 rounded-2xl border border-stone-200 bg-white/80 px-3 py-3 shadow-sm transition">
                                <span data-page-handle aria-hidden="true"
                                    class="shrink-0 cursor-grab select-none px-1 text-lg leading-none text-stone-300 transition group-hover:text-stone-400">⣿</span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="truncate font-medium text-stone-800">{{ $page->title }}</span>
                                        @unless ($page->published)
                                            <span class="rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-medium text-stone-500">Ukryta</span>
                                        @endunless
                                    </div>
                                    <p class="mt-0.5 truncate text-xs text-stone-400">/informacje/{{ $page->id }}-{{ $page->slug }}</p>
                                </div>

                                <div class="flex shrink-0 items-center gap-3">
                                    <a href="{{ route('seller.pages.edit', $page) }}"
                                        class="rounded-xl border border-stone-200 bg-white px-3 py-1.5 text-sm font-medium text-stone-700 transition hover:bg-stone-100">
                                        Edytuj
                                    </a>
                                    {{-- Wiersz ma kilkadziesiąt pikseli, więc pytanie staje
                                         W NIM, a nie na nakładce, która by się nie zmieściła. --}}
                                    {{-- Regulaminu nie usuniesz nigdy, Zasad punktów — dopóki punkty działają. --}}
                                    @if ($page->isDeletable())
                                        <livewire:confirm-action
                                            :key="'usun-strone-'.$page->id"
                                            :action="route('seller.pages.destroy', $page)"
                                            :title="'Usunąć stronę '.$page->title.'?'"
                                            :lines="['Zniknie ze sklepu razem z odnośnikiem do niej.', 'Tej operacji nie da się cofnąć.']"
                                            label="Usuń stronę"
                                            text="Usuń"
                                            icon="none"
                                            :inline="true"
                                            trigger-class="text-sm font-medium text-rose-700 transition hover:text-rose-800" />
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-4 text-xs text-stone-400">Przeciągnij stronę za uchwyt, aby zmienić kolejność. Regulamin możesz przestawić, ale nie usuniesz — jest wymagany.</p>
                @endif
            </div>
        </div>

        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to działa</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">📄</span>
                        <span>Strony pojawiają się w menu <span class="font-medium text-stone-700">Informacje</span> i w stopce sklepu — w tej samej kolejności.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">⚖️</span>
                        <span><span class="font-medium text-stone-700">Regulamin</span> jest wymagany i zawsze widoczny — uzupełnij go treścią pod swój sklep.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🙈</span>
                        <span>Stronę w przygotowaniu możesz oznaczyć jako <span class="font-medium text-stone-700">ukrytą</span> — nie pokaże się klientom, dopóki jej nie opublikujesz.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🔒</span>
                        <span>Politykę prywatności zapewnia Kramio — nie musisz jej pisać.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
