@props(['shop', 'banners'])

{{-- Banery strony głównej: na szerokość kolumny treści (ta sama co moduły
     poniżej, `max-w-6xl`), pod nazwą sklepu. Rogi `rounded-3xl` jak karty —
     klasa Tailwinda, więc idzie za „charakterem sklepu” (stopień zaokrągleń).

     JEDEN baner = statyczny obrazek, bez skryptu i bez kontrolek.
     KILKA = slider. Przewijanie palcem robi natywnie przeglądarka (CSS scroll
     snap), więc działa nawet bez JavaScriptu; skrypt dokłada tylko automat,
     kropki, strzałki i pauzę.

     WYSOKOŚĆ wyznacza PIERWSZY baner (jego proporcje → `aspect-ratio`), kolejne
     dopasowują się z przycięciem brzegów. Bez tego strona skakałaby przy każdej
     zmianie slajdu. Górny limit 75vh: pionowe zdjęcie nie zajmie trzech ekranów.

     Automat zatrzymuje się, gdy klient najedzie na baner, dotknie go albo
     przejdzie do niego klawiaturą; jest przycisk pauzy (wymóg dostępności dla
     treści ruchomej dłuższej niż 5 s). Przy systemowym „ogranicz ruch"
     automat nie startuje sam.

     Pierwszy obrazek ładuje się priorytetowo (to pierwsza rzecz na ekranie),
     kolejne leniwie. `srcset` + `sizes` (szerokość kolumny treści): telefon bierze mały wariant.

     Style i skrypt są WBUDOWANE (storefront nie ładuje app.js), więc komponent
     nie zależy od przebudowy front-endu. --}}
@if ($banners->isNotEmpty())
    @php
        $first = $banners->first();
        $many = $banners->count() > 1;
    @endphp

    @once
        <style>
            .sbn { position: relative; }
            .sbn-track { display: flex; width: 100%; max-height: 75vh; overflow-x: auto; overflow-y: hidden;
                scroll-snap-type: x mandatory; scroll-behavior: smooth; scrollbar-width: none; }
            .sbn-track::-webkit-scrollbar { display: none; }
            .sbn-slide { position: relative; flex: 0 0 100%; scroll-snap-align: start; }
            .sbn-slide a, .sbn-slide img { display: block; width: 100%; height: 100%; }
            .sbn-slide img { object-fit: cover; }
            .sbn-btn { position: absolute; z-index: 2; display: flex; align-items: center; justify-content: center;
                width: 2.5rem; height: 2.5rem; border: 0; border-radius: 9999px; cursor: pointer;
                background: rgba(255,255,255,.85); color: #1c1917; box-shadow: 0 1px 4px rgba(0,0,0,.2);
                font-size: 1rem; line-height: 1; transition: background .2s; }
            .sbn-btn:hover { background: #fff; }
            .sbn-btn:focus-visible, .sbn-dot:focus-visible { outline: 3px solid #fff; outline-offset: 2px; }
            .sbn-prev, .sbn-next { top: 50%; transform: translateY(-50%); }
            .sbn-prev { left: 1rem; }
            .sbn-next { right: 1rem; }
            .sbn-pause { right: 1rem; bottom: 1rem; width: 2.25rem; height: 2.25rem; font-size: .8rem; }
            .sbn-dots { position: absolute; z-index: 2; left: 50%; bottom: 1.1rem; transform: translateX(-50%);
                display: flex; gap: .5rem; padding: .35rem .6rem; border-radius: 9999px; background: rgba(0,0,0,.25); }
            .sbn-dot { width: .6rem; height: .6rem; padding: 0; border: 0; border-radius: 9999px; cursor: pointer;
                background: rgba(255,255,255,.55); transition: background .2s, transform .2s; }
            .sbn-dot[aria-current="true"] { background: #fff; transform: scale(1.25); }
            @media (max-width: 767px) { .sbn-prev, .sbn-next { display: none; } }
            @media (prefers-reduced-motion: reduce) { .sbn-track { scroll-behavior: auto; } }
        </style>
    @endonce

    <section class="mx-auto mt-10 max-w-6xl px-6">
    <div class="sbn"
        @if ($many)
            data-banner-slider data-interval="{{ $shop->bannerInterval() }}"
            aria-roledescription="karuzela" aria-label="Banery"
        @endif>
        <div class="sbn-track rounded-3xl" data-track style="aspect-ratio: {{ $first->image_width }} / {{ $first->image_height }};">
            @foreach ($banners as $banner)
                @php
                    $image = '<img src="'.e($banner->url()).'" srcset="'.e($banner->srcset()).'" sizes="(min-width: 1152px) 1104px, calc(100vw - 3rem)"'
                        .' width="'.$banner->image_width.'" height="'.$banner->image_height.'" alt="'.e($banner->alt).'"'
                        .($loop->first ? ' fetchpriority="high"' : ' loading="lazy"').'>';
                @endphp
                <div class="sbn-slide"
                    @if ($many) role="group" aria-roledescription="slajd" aria-label="{{ $loop->iteration }} z {{ $loop->count }}" @endif>
                    @if (filled($banner->link_url))
                        @if ($banner->linksOutside($shop))
                            <a href="{{ $banner->link_url }}" target="_blank" rel="noopener">{!! $image !!}</a>
                        @else
                            <a href="{{ $banner->link_url }}">{!! $image !!}</a>
                        @endif
                    @else
                        {!! $image !!}
                    @endif
                </div>
            @endforeach
        </div>

        @if ($many)
            <button type="button" class="sbn-btn sbn-prev" data-prev aria-label="Poprzedni baner">‹</button>
            <button type="button" class="sbn-btn sbn-next" data-next aria-label="Następny baner">›</button>
            <div class="sbn-dots">
                @foreach ($banners as $banner)
                    <button type="button" class="sbn-dot" data-dot aria-label="Pokaż baner {{ $loop->iteration }}"
                        aria-current="{{ $loop->first ? 'true' : 'false' }}"></button>
                @endforeach
            </div>
            <button type="button" class="sbn-btn sbn-pause" data-pause aria-label="Zatrzymaj pokaz">❚❚</button>

            @once
                <script>
                    (function () {
                        function init(root) {
                            if (root.dataset.ready) return;
                            root.dataset.ready = '1';

                            var track = root.querySelector('[data-track]');
                            var slides = track.children.length;
                            var dots = root.querySelectorAll('[data-dot]');
                            var pauseBtn = root.querySelector('[data-pause]');
                            var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                            var delay = Math.max(3, parseInt(root.dataset.interval, 10) || 6) * 1000;
                            var index = 0, timer = null, holding = false, stopped = reduce, frame = null;

                            function go(i) {
                                index = (i + slides) % slides;
                                track.scrollTo({ left: index * track.clientWidth, behavior: reduce ? 'auto' : 'smooth' });
                            }
                            function sync() {
                                index = Math.round(track.scrollLeft / Math.max(1, track.clientWidth));
                                dots.forEach(function (d, k) { d.setAttribute('aria-current', k === index ? 'true' : 'false'); });
                            }
                            function tick() {
                                if (!document.body.contains(root)) { clearInterval(timer); return; }
                                if (!holding && !stopped && !document.hidden) go(index + 1);
                            }
                            // Każdy ruch klienta liczy czas od nowa — slajd nie ucieka sekundę po ręcznym wyborze.
                            function restart() {
                                clearInterval(timer);
                                if (!stopped) timer = setInterval(tick, delay);
                            }
                            function paintPause() {
                                pauseBtn.textContent = stopped ? '▶' : '❚❚';
                                pauseBtn.setAttribute('aria-label', stopped ? 'Wznów pokaz' : 'Zatrzymaj pokaz');
                            }

                            track.addEventListener('scroll', function () {
                                cancelAnimationFrame(frame);
                                frame = requestAnimationFrame(sync);
                            }, { passive: true });
                            root.addEventListener('mouseenter', function () { holding = true; });
                            root.addEventListener('mouseleave', function () { holding = false; restart(); });
                            root.addEventListener('focusin', function () { holding = true; });
                            root.addEventListener('focusout', function (e) {
                                if (!root.contains(e.relatedTarget)) { holding = false; restart(); }
                            });
                            track.addEventListener('touchstart', function () { holding = true; }, { passive: true });
                            track.addEventListener('touchend', function () { holding = false; restart(); }, { passive: true });

                            root.querySelector('[data-prev]').addEventListener('click', function () { go(index - 1); restart(); });
                            root.querySelector('[data-next]').addEventListener('click', function () { go(index + 1); restart(); });
                            dots.forEach(function (d, k) { d.addEventListener('click', function () { go(k); restart(); }); });
                            pauseBtn.addEventListener('click', function () { stopped = !stopped; paintPause(); restart(); });

                            paintPause();
                            restart();
                        }

                        function boot() { document.querySelectorAll('[data-banner-slider]').forEach(init); }

                        boot();
                        // Nawigacja Livewire (wire:navigate) podmienia stronę bez przeładowania.
                        if (!window.__bannerSliderBooted) {
                            window.__bannerSliderBooted = true;
                            document.addEventListener('livewire:navigated', boot);
                        }
                    })();
                </script>
            @endonce
        @endif
    </div>
    </section>
@endif
