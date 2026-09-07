<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Szablony storefrontu (wygląd sklepu)
    |--------------------------------------------------------------------------
    |
    | Motyw sklepu ma DWIE niezależne osie (patrz notatka „storefront-theme-system"):
    |   1. SZABLON  = skóra + osobowość (kolory, typografia, styl układu). Wybierany
    |                 przez sprzedawcę z gotowej siatki. Definicja żyje TU, w kodzie —
    |                 dodanie szablonu = nowy wpis + widoki/CSS, nie funkcja panelu.
    |   2. PALETA   = konkretny zestaw kolorów w ramach szablonu (gotowy „preset").
    |                 Sprzedawca klika gotowca; nie dłubie pojedynczych kolorów.
    |
    | Model przechowywania jak przy pakietach (config/shop.php): sklep trzyma tylko
    | REFERENCJĘ — `shops.template` (slug szablonu) + `shops.theme` (JSON, m.in.
    | wybrana paleta). Definicje (nazwy, kolory) są tu i można je zmieniać bez
    | ruszania bazy. Slug (EN) stały i ukryty; `name` (PL) widoczny i zmienialny.
    |
    | KONTRAKT TOKENÓW — każda paleta dostarcza ten sam mały zestaw semantycznych
    | tokenów, które storefront wystawi jako zmienne CSS (:root, serwerowo — bez
    | kompilacji per-sklep, wymóg shared-hostingu). Reszta kolorów (obramowania,
    | wyszarzenia) będzie WYLICZANA z tych w CSS, nie wybierana przez sprzedawcę:
    |   brand      — kolor marki (przyciski, linki, cena, akcenty)
    |   brand_ink  — tekst NA kolorze marki (jawny, dla kontrastu na etykietach)
    |   surface    — tło strony (ton/„nastrój")
    |   ink        — główny kolor tekstu na tle
    |
    | Kolory poniżej to rozsądny start — dopieszczymy je wizualnie, gdy storefront
    | zacznie je renderować. `order` daje jawną kolejność w siatce.
    |
    | CHROME — druga oś szablonu, obok kolorów. Sama paleta zmieniała tylko
    | przyciski, cenę i linki; pasek górny, stopka i karty liczyły się z `ink`
    | (prawie czerni), więc KAŻDY szablon miał identycznie szare obramowanie
    | strony i wszystkie wyglądały tak samo. `chrome` mówi, czym malujemy pasek
    | i stopkę:
    |   neutral    — szary tint z `ink` (dotychczasowe zachowanie)
    |   brand_tint — delikatny tint z `brand` (12%); jasny pasek w tonie marki
    |   brand      — pastel marki o sile `chrome_brand_mix` (niżej); tekst na
    |                `ink`, nie `brand_ink` (biały na pastelu jest nieczytelny)
    | Wartość nieznana (albo brak) = `neutral`, więc szablon bez tego klucza po
    | prostu zostaje przy starym wyglądzie.
    |
    | CHROME_TEXTURE — faktura na pasku i stopce, żeby kolorowy chrome nie był
    | „aplą" (prośba Rafała: „bardziej jak sztuka"). Wzór rysowany czystym CSS
    | (gradienty) w kolorze tła strony, więc sam idzie za paletą — zero grafik,
    | zero kompilacji per-sklep. Część osobowości szablonu:
    |   awning   — skośne paski jak daszek kramu/markiza
    |   dots     — naprzemienne rzędy kropek (wisienki)
    |   pinpoint — drobniutkie kropeczki (fajans/porcelana)
    |   stripes  — delikatne skośne paski, w przeciwną stronę niż awning
    | Brak klucza / wartość nieznana = gładko (bez wzoru).
    |
    */

    'templates' => [

        'velvet_cloud' => [
            'name' => 'Aksamitna chmurka',
            'description' => 'Jasny i powietrzny — biel z nutą błękitu. Lekki, czysty, wygodny do czytania.',
            'order' => 1,
            'chrome' => 'neutral',
            'default_palette' => 'sky',
            'palettes' => [
                'sky' => [
                    'name' => 'Błękit',
                    'tokens' => [
                        'brand' => '#3B82F6',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#F8FAFC',
                        'ink' => '#1E293B',
                    ],
                ],
                'lavender' => [
                    'name' => 'Lawenda',
                    'tokens' => [
                        'brand' => '#7C6FD6',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FAFAFF',
                        'ink' => '#2A2540',
                    ],
                ],
                'mint' => [
                    'name' => 'Mięta',
                    'tokens' => [
                        'brand' => '#2FA98C',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#F5FBF8',
                        'ink' => '#1E3A33',
                    ],
                ],
                'blush' => [
                    'name' => 'Pudrowy róż',
                    'tokens' => [
                        'brand' => '#E0699B',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FDF7FA',
                        'ink' => '#3A2530',
                    ],
                ],
                'coral' => [
                    'name' => 'Koral',
                    'tokens' => [
                        'brand' => '#F0765B',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FEF8F5',
                        'ink' => '#3A2822',
                    ],
                ],
            ],
        ],

        'green_nook' => [
            'name' => 'Zielony zakątek',
            'description' => 'Kolory natury — zieleń, brąz i len. Ciepły, ekologiczny klimat.',
            'order' => 2,
            'chrome' => 'neutral',
            'default_palette' => 'forest',
            'palettes' => [
                'forest' => [
                    'name' => 'Las',
                    'tokens' => [
                        'brand' => '#3F7D4E',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#F5F3EC',
                        'ink' => '#2E3A2E',
                    ],
                ],
                'moss' => [
                    'name' => 'Mech',
                    'tokens' => [
                        'brand' => '#6B8E4E',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#F3F1E7',
                        'ink' => '#33372B',
                    ],
                ],
                'clay' => [
                    'name' => 'Glina',
                    'tokens' => [
                        'brand' => '#B4633E',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#F6F1EA',
                        'ink' => '#3A2C24',
                    ],
                ],
                'olive' => [
                    'name' => 'Oliwka',
                    'tokens' => [
                        'brand' => '#7A7B3F',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#F4F2E8',
                        'ink' => '#34331F',
                    ],
                ],
                'honey' => [
                    'name' => 'Miód',
                    'tokens' => [
                        'brand' => '#C79338',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#F7F3E9',
                        'ink' => '#3A3018',
                    ],
                ],
            ],
        ],

        'graphite_dusk' => [
            'name' => 'Grafitowy wieczór',
            'description' => 'Ciemny i elegancki — grafit z ciepłym akcentem. Produkty wychodzą na pierwszy plan.',
            'order' => 3,
            'chrome' => 'neutral',
            'default_palette' => 'ember',
            'palettes' => [
                'ember' => [
                    'name' => 'Bursztyn',
                    'tokens' => [
                        'brand' => '#E0A25E',
                        'brand_ink' => '#1A1A1A',
                        'surface' => '#1F2124',
                        'ink' => '#E8E6E1',
                    ],
                ],
                'rose' => [
                    'name' => 'Róża',
                    'tokens' => [
                        'brand' => '#D98A8A',
                        'brand_ink' => '#1A1A1A',
                        'surface' => '#222024',
                        'ink' => '#ECE8E6',
                    ],
                ],
                'gold' => [
                    'name' => 'Złoto',
                    'tokens' => [
                        'brand' => '#D4B15F',
                        'brand_ink' => '#1A1A1A',
                        'surface' => '#202225',
                        'ink' => '#E9E6DE',
                    ],
                ],
                'teal' => [
                    'name' => 'Turkus',
                    'tokens' => [
                        'brand' => '#5FB3AE',
                        'brand_ink' => '#1A1A1A',
                        'surface' => '#1E2325',
                        'ink' => '#E4E9E8',
                    ],
                ],
                'orchid' => [
                    'name' => 'Storczyk',
                    'tokens' => [
                        'brand' => '#B394D6',
                        'brand_ink' => '#1A1A1A',
                        'surface' => '#232027',
                        'ink' => '#E9E5EC',
                    ],
                ],
            ],
        ],

        'velour_mist' => [
            'name' => 'Welurowa mgła',
            'description' => 'Miękkie, przygaszone barwy — brudny róż i welurowe, zamglone odcienie. Ciepły, nastrojowy klimat.',
            'order' => 4,
            'chrome' => 'neutral',
            'default_palette' => 'dusty_rose',
            'palettes' => [
                'dusty_rose' => [
                    'name' => 'Brudny róż',
                    'tokens' => [
                        'brand' => '#B26E79',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FBF5F6',
                        'ink' => '#3B2C30',
                    ],
                ],
                'dusty_blue' => [
                    'name' => 'Zamglony błękit',
                    'tokens' => [
                        'brand' => '#6F8BA6',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#F4F7FA',
                        'ink' => '#2C343C',
                    ],
                ],
                'sage' => [
                    'name' => 'Szałwia',
                    'tokens' => [
                        'brand' => '#6F8D6D',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#F4F8F3',
                        'ink' => '#2E372C',
                    ],
                ],
                'cocoa' => [
                    'name' => 'Kakao',
                    'tokens' => [
                        'brand' => '#9D7B67',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#F8F4F0',
                        'ink' => '#372B24',
                    ],
                ],
                'ochre' => [
                    'name' => 'Ochra',
                    'tokens' => [
                        'brand' => '#A6853C',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FAF6EE',
                        'ink' => '#352C1C',
                    ],
                ],
            ],
        ],

        /*
        | Cztery poniższe szablony to świadomie INNA rodzina niż cztery powyższe:
        | tam kremowe, przygaszone tło i przybrudzone akcenty; tu czysta biel (lub
        | jasna szarość) i jeden nasycony kolor. Jasno, kontrastowo, „sklepowo".
        |
        | Wszystkie kolory `brand` mają kontrast ≥ 4.5:1 z bielą — bo `brand`
        | robi u nas nie tylko tło przycisku, ale też cenę i linki NA tle strony.
        | Dlatego np. bursztyn jest w odcieniu 700, nie 500 jak w panelu Kramio:
        | amber-500 na bieli jest nieczytelny.
        */

        'kramio_light' => [
            'name' => 'Bursztynowy kram',
            'description' => 'Jasne, lekko szare tło i bursztynowy akcent — ten sam klimat co panel Kramio.',
            'order' => 5,
            'card_mix' => 6,
            'chrome' => 'brand_tint',
            'chrome_texture' => 'awning',
            'default_palette' => 'amber',
            'palettes' => [
                'amber' => [
                    'name' => 'Bursztyn',
                    'tokens' => [
                        'brand' => '#B45309',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FAFAF9',
                        'ink' => '#1C1917',
                    ],
                ],
                'graphite' => [
                    'name' => 'Grafit',
                    'tokens' => [
                        'brand' => '#3F3F46',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FAFAF9',
                        'ink' => '#1C1917',
                    ],
                ],
                'copper' => [
                    'name' => 'Miedź',
                    'tokens' => [
                        'brand' => '#A94B22',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FAFAF9',
                        'ink' => '#1C1917',
                    ],
                ],
                'sage' => [
                    'name' => 'Szałwia',
                    'tokens' => [
                        'brand' => '#467053',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FAFAF9',
                        'ink' => '#1C1917',
                    ],
                ],
                'slate' => [
                    'name' => 'Stalowy granat',
                    'tokens' => [
                        'brand' => '#334155',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FAFAF9',
                        'ink' => '#1C1917',
                    ],
                ],
            ],
        ],

        'white_red' => [
            'name' => 'Wiśniowy sad',
            'description' => 'Czysta biel z mocnym czerwonym akcentem. Wyrazisty, energiczny, dobrze robi promocjom.',
            'order' => 6,
            'card_mix' => 6,
            'chrome' => 'brand',
            'chrome_texture' => 'dots',
            'default_palette' => 'red',
            'palettes' => [
                'red' => [
                    'name' => 'Czerwień',
                    'tokens' => [
                        'brand' => '#DC2626',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FFFFFF',
                        'ink' => '#1C1717',
                    ],
                ],
                'ruby' => [
                    'name' => 'Rubin',
                    'tokens' => [
                        'brand' => '#BE123C',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FFFFFF',
                        'ink' => '#1C1717',
                    ],
                ],
                'burgundy' => [
                    'name' => 'Burgund',
                    'tokens' => [
                        'brand' => '#9F1239',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FFFFFF',
                        'ink' => '#1C1717',
                    ],
                ],
                'brick' => [
                    'name' => 'Cegła',
                    'tokens' => [
                        'brand' => '#B03A20',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FFFFFF',
                        'ink' => '#1C1717',
                    ],
                ],
                'raspberry' => [
                    'name' => 'Malina',
                    'tokens' => [
                        'brand' => '#C2185B',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FFFFFF',
                        'ink' => '#1C1717',
                    ],
                ],
            ],
        ],

        'white_blue' => [
            'name' => 'Błękitna porcelana',
            'description' => 'Czysta biel z niebieskim akcentem. Spokojny, uporządkowany, budzi zaufanie.',
            'order' => 7,
            'card_mix' => 6,
            'chrome' => 'brand',
            'chrome_texture' => 'pinpoint',
            'default_palette' => 'cobalt',
            'palettes' => [
                'cobalt' => [
                    'name' => 'Kobalt',
                    'tokens' => [
                        'brand' => '#2563EB',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FFFFFF',
                        'ink' => '#171A26',
                    ],
                ],
                'azure' => [
                    'name' => 'Lazur',
                    'tokens' => [
                        'brand' => '#0369A1',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FFFFFF',
                        'ink' => '#171A26',
                    ],
                ],
                'navy' => [
                    'name' => 'Granat',
                    'tokens' => [
                        'brand' => '#1E3A8A',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FFFFFF',
                        'ink' => '#171A26',
                    ],
                ],
                'indigo' => [
                    'name' => 'Indygo',
                    'tokens' => [
                        'brand' => '#4F46E5',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FFFFFF',
                        'ink' => '#171A26',
                    ],
                ],
                'steel' => [
                    'name' => 'Stal',
                    'tokens' => [
                        'brand' => '#475569',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FFFFFF',
                        'ink' => '#171A26',
                    ],
                ],
            ],
        ],

        'white_green' => [
            'name' => 'Zimowy ogród',
            'description' => 'Czysta biel z zielonym akcentem. Świeży i naturalny, ale bez ekologicznej patyny.',
            'order' => 8,
            'card_mix' => 6,
            'chrome' => 'brand',
            'chrome_texture' => 'stripes',
            'default_palette' => 'emerald',
            'palettes' => [
                'emerald' => [
                    'name' => 'Szmaragd',
                    'tokens' => [
                        'brand' => '#047857',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FFFFFF',
                        'ink' => '#16211A',
                    ],
                ],
                'pine' => [
                    'name' => 'Sosna',
                    'tokens' => [
                        'brand' => '#15803D',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FFFFFF',
                        'ink' => '#16211A',
                    ],
                ],
                'deep_forest' => [
                    'name' => 'Bór',
                    'tokens' => [
                        'brand' => '#166534',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FFFFFF',
                        'ink' => '#16211A',
                    ],
                ],
                'marine' => [
                    'name' => 'Morska',
                    'tokens' => [
                        'brand' => '#0F766E',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FFFFFF',
                        'ink' => '#16211A',
                    ],
                ],
                'olive' => [
                    'name' => 'Oliwka',
                    'tokens' => [
                        'brand' => '#3F6212',
                        'brand_ink' => '#FFFFFF',
                        'surface' => '#FFFFFF',
                        'ink' => '#16211A',
                    ],
                ],
            ],
        ],

    ],

    // Slug szablonu domyślnego — przypisywany nowym sklepom (spójne z kolumną
    // `shops.template` default). Musi istnieć w `templates` powyżej.
    'default_template' => 'velvet_cloud',

    // Siła pastelu dla chrome `brand`: ile PROCENT koloru marki idzie do tła
    // paska i stopki (reszta to tło strony). Dostrajane na oko z Rafałem:
    // 100 było za ciężkie, 50 wciąż za mocne → 30. Jedna liczba, trzy miejsca
    // ją czytają (layout storefrontu + podgląd kafli w panelu, PHP i JS).
    'chrome_brand_mix' => 30,

    // Boxy (st-card): o ile procent box jest CIEMNIEJSZY od tła strony
    // (technicznie: tyle % `ink` domieszane do `surface`, więc odcień idzie
    // za paletą — na niebieskim tle box jest niebieskawy, nie szary).
    // To globalny domyślny; szablon może nadpisać własnym `card_mix`
    // (nowa biała rodzina chce wyraźniejszych boxów niż stare kremowe
    // szablony, gdzie 4% siedzi w projekcie od początku).
    'card_mix' => 4,

    /*
    |--------------------------------------------------------------------------
    | Charakter sklepu — dwie osie NIEZALEŻNE od szablonu
    |--------------------------------------------------------------------------
    |
    | Szablon z paletą dają „skórę”; te dwie osie pozwalają tę samą skórę
    | ustawić spokojniej. Ten sam szablon ma obsłużyć i pracownię biżuterii,
    | i wędliniarza robiącego szynkę na zamówienie — a dekoracyjny serif
    | z mocno zaokrąglonymi kaflami pasuje wyłącznie do pierwszego.
    |
    | Wybór jest GLOBALNY dla sklepu: leży OBOK szablonu, nie w nim. Paleta
    | jest zapamiętywana per szablon (`palettes.{slug}`), te dwie osie nie —
    | przeskok na inny szablon ich nie rusza. Trzymane w JSON `shops.theme`
    | pod kluczami `font` i `radius`.
    |
    | Jak to działa: obie osie nadpisują ZMIENNE CSS, z których korzysta już
    | zbudowany Tailwind (`.font-serif{font-family:var(--font-serif)}`,
    | `.rounded-xl{border-radius:var(--radius-xl)}`). Jedno miejsce w :root
    | storefrontu przestawia wszystkie użycia naraz — bez edycji widoków
    | i bez kompilacji CSS per sklep (wymóg shared-hostingu).
    |
    | FONT — czym pisane są nagłówki i tytuły kart:
    |   decorative — Instrument Serif (dotychczasowe, domyślne)
    |   plain      — ten sam sans co treść; plik serifu w ogóle się nie pobiera
    |
    | RADIUS — jak zaokrąglone są boxy, kafle i pola. Skala geometryczna
    | ¼ → ½ → 1×, gdzie `large` to wartości Tailwinda używane w projekcie
    | od początku. Do zera NIE schodzimy świadomie: pigułki (`rounded-full`)
    | mają w CSS literał zamiast zmiennej, więc przy zerowanych `--radius-*`
    | zostałyby owalne obok idealnie ostrych kafli.
    |
    | Klucze w `vars` to KOŃCÓWKI zmiennych Tailwinda (`lg` → `--radius-lg`);
    | muszą pokrywać każdy stopień używany w widokach storefrontu.
    |
    */
    'fonts' => [
        'decorative' => [
            'name' => 'Dekoracyjna',
            'description' => 'Ozdobny krój w nagłówkach i tytułach — butikowy, rękodzielniczy klimat.',
        ],
        'plain' => [
            'name' => 'Prosta',
            'description' => 'Nagłówki tym samym krojem co treść — rzeczowo i bez ozdobników.',

            // Stopnie nagłówków są w widokach dobrane pod SERIF, który czyta się
            // optycznie mniejszy niż sans. Ten sam `text-4xl` w sansie wychodzi
            // ciężki i za duży, więc krój prosty dostaje własną drabinkę. Im
            // większy stopień, tym mocniejsza korekta — różnica optyczna rośnie
            // z rozmiarem.
            //
            // Klucze to KOŃCÓWKI zmiennych Tailwinda (`2xl` → `--text-2xl`).
            // Nadpisujemy je NA elementach z klasą `.font-serif`, nie w :root —
            // dzięki temu kurczą się wyłącznie nagłówki, a treść zostaje.
            // Wysokości wierszy są w Tailwindzie bezjednostkowe, więc schodzą
            // razem z rozmiarem same z siebie.
            // Wartości dobrane na oko z Rafałem na żywym storefroncie (druga
            // runda — pierwsza, łagodniejsza, wciąż czytała się za ciężko).
            // W komentarzu stopień domyślny (pod serif) i skala korekty.
            'sizes' => [
                'xl' => '1.0625rem',    // 1.25   −15%
                '2xl' => '1.1875rem',   // 1.5    −21%
                '3xl' => '1.4375rem',   // 1.875  −23%
                '4xl' => '1.6875rem',   // 2.25   −25%
                '5xl' => '2.125rem',    // 3      −29%
                '6xl' => '2.625rem',    // 3.75   −30%
                '7xl' => '3.125rem',    // 4.5    −31%
            ],
        ],
    ],

    'default_font' => 'decorative',

    'radii' => [
        'small' => [
            'name' => 'Małe',
            'description' => 'Prawie ostre kanty — porządek i konkret.',
            'vars' => [
                'md' => '0.125rem',
                'lg' => '0.125rem',
                'xl' => '0.1875rem',
                '2xl' => '0.25rem',
                '3xl' => '0.375rem',
            ],
        ],
        'medium' => [
            'name' => 'Średnie',
            'description' => 'Delikatnie złagodzone rogi — złoty środek.',
            'vars' => [
                'md' => '0.1875rem',
                'lg' => '0.25rem',
                'xl' => '0.375rem',
                '2xl' => '0.5rem',
                '3xl' => '0.75rem',
            ],
        ],
        'large' => [
            'name' => 'Duże',
            'description' => 'Miękkie, mocno zaokrąglone kafle — ciepło i przytulnie.',
            'vars' => [
                'md' => '0.375rem',
                'lg' => '0.5rem',
                'xl' => '0.75rem',
                '2xl' => '1rem',
                '3xl' => '1.5rem',
            ],
        ],
    ],

    'default_radius' => 'large',

    /*
    |--------------------------------------------------------------------------
    | Gęstość wykazu — adaptacja do SKALI katalogu
    |--------------------------------------------------------------------------
    |
    | Wykaz (/produkty) sam dobiera układ do liczby aktywnych produktów sklepu:
    | mały katalog → mniej kolumn = duże, wyraziste kafle; duży → więcej kolumn
    | i więcej na stronę. To trzecia oś motywów („adaptacja do ilości") — globalna,
    | wspólna dla wszystkich szablonów (nie zależy od wyboru szablonu).
    |
    | Reguła: bierzemy PIERWSZY (najmniejszy) układ z drabinki `steps`, przy którym
    | wszystkie aktywne produkty mieszczą się w `max_pages` podstronach. Rośniemy
    | w kolejności wpisów: najpierw wiersze przy 3 kolumnach (3→5), a dopiero gdy
    | i to nie starcza — skok na 4 kolumny (wiersze 4→5). Powyżej ostatniego stopnia
    | zostaje sufit i podstron po prostu przybywa.
    |
    | SUFIT TO 20 NA STRONĘ (4×5) I TO NIE JEST LICZBA PRZYPADKOWA. Limity
    | produktów w pakietach (60 / 300 / 600) są jej wielokrotnościami, więc sklep
    | z PEŁNYM katalogiem ma wszystkie podstrony pełne co do sztuki: 3, 15 i 30
    | podstron. Przy dawnym suficie 24 Stragan wychodził 12×24+12 — ostatnia
    | podstrona w połowie pusta.
    |
    | Zmiana sufitu albo limitów w `config/shop.php` musi iść PARAMI, inaczej ta
    | własność cicho znika. Sufit jest jednocześnie ostatnim szczeblem drabinki
    | i limitem Krama (60 = 3 × 20) — darmowy sklep z pełnym katalogiem mieści
    | się dokładnie w założonych trzech podstronach.
    |
    | Czego to NIE załatwia: sklep, który ma 47 produktów, dalej skończy stronę
    | niepełnym wierszem. Równa siatka na ostatniej podstronie zależy od liczby
    | produktów SPRZEDAWCY, a limit rozstrzyga tylko o przypadku katalogu pełnego.
    |
    | Klucz: WIELKOŚĆ KAFLA robią kolumny (3 = duże, 4 = gęstsze); `rows` steruje
    | tylko długością strony (per_page = columns × rows). Liczba liczona z aktywnych
    | produktów CAŁEGO sklepu, NIE z przefiltrowanego widoku — skala jest stała
    | niezależnie od tego, co klient akurat filtruje/sortuje.
    |
    | Klasy siatki (`lg:grid-cols-3` / `lg:grid-cols-4`) muszą istnieć w buildzie
    | Tailwinda — trzymaj `columns` w zbiorze {3, 4}, inaczej dorób klasę i widok.
    |
    */
    'listing' => [
        'max_pages' => 3,
        'steps' => [
            ['columns' => 3, 'rows' => 3], // 9  — do 27 produktów
            ['columns' => 3, 'rows' => 4], // 12 — do 36
            ['columns' => 3, 'rows' => 5], // 15 — do 45
            ['columns' => 4, 'rows' => 4], // 16 — do 48
            ['columns' => 4, 'rows' => 5], // 20 — do 60 (dalej: sufit + więcej podstron)
        ],
    ],

];
