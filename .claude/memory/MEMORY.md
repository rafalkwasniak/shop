# Memory index

- [PRIORYTETY: najpierw wyjście do ludzi](priorities-launch-first.md) — OBOWIĄZUJĄCA kolejność. Czytać PRZED planowaniem sesji.

## Zasady współpracy
- [Nie piętrz zastrzeżeń](feedback-dont-stack-caveats.md) — ryzyko nazwij RAZ. NIE dotyczy faktów nieprawdziwych.
- [Decisions override spec](decisions-override-spec.md) — żywe ustalenia nadrzędne nad docs/specyfikacja.md.
- [References are suggestions](references-are-suggestions.md) — cudze produkty = inspiracja, nie specyfikacja.
- [Incremental checkpoints](incremental-checkpoints-per-element.md) — element po elemencie, przystanek na froncie.
- [CP = commit + push](cp-shorthand-commit-push.md)
- [Pamięć ma kopię w repo](memory-copy-travels-in-repo.md) — **przy CP z handoffem: `memory-sync.sh save`, potem commit.**
- [Pytaj o stan Rafała, sprawdzaj fakty w kodzie](feedback-ask-dont-probe-for-user-state.md) — czy coś już zrobił = PYTANIE; jak działa kod = SPRAWDZENIE.
- [Dokumenty do druku = PROSTY HTML](feedback-print-documents-plain-html.md) — bez kart i siatek; `break-inside: avoid` na bloku wyższym niż strona rozjeżdża tekst.
- [Build nie przechodzi = MÓW OD RAZU](build-fails-tell-rafal-immediately.md) — 1–2 próby; build robi Rafał.
- [No co-author footer](no-coauthor-footer-in-commits.md) — NIGDY stopek generatora.
- [Ton tekstów marketingowych](feedback-marketing-tone-kramio.md) — SPRZEDAŻ nie oferówka; PRODUKTY nie usługi.

## Prawne
- [DSA: Kramio = HOSTING](legal-dsa-hosting-classification.md) — decyzja 15.08. **Galeria sklepów zabiłaby argument nr 1.**
- [Regulamin i polityka v3](legal-audit-2026-08-15.md) — **numeracja się przesunęła**; stare odesłania nieaktualne.
- [Wzory dokumentów dla sprzedawców](plan-seller-legal-templates.md) — regulamin ZROBIONY, zostaje POLITYKA. NIP nie może być twardym warunkiem.

## Otwarte / do decyzji
- [PLAN: pracownicy sklepu](plan-shop-employees.md) — przyjęty 07.09, ZERO kodu. Nazwa `employee`. Kramio pierwsze, potem cherry-pick.
- [Magellan Bay — osobny projekt](plan-magellan-bay-separate-project.md) — **oferta 6 000 zł wysłana 01.09, czekamy.** NIE część roadmapy Kramio.
- [OTWARTE: limit 250 procesów](open-hosting-process-limit.md) — **w trakcie pracy testy FILTROWANE**, pełna suita raz przed commitem.
- [KIERUNEK: NOWY SERWER](plan-dev-environment.md) — przeprowadzka planowana, termin nieustalony.
- [BACKUP Etap 1+3 wdrożone](open-no-file-backups.md) — kopie 2×/dobę, odtworzenie przećwiczone. Etap 2 (poza serwerem) otwarty.
- [ZROBIONE: crony ursalogic](disabled-ursalogic-queue-crons.md) — fix `low --max-time=55` WSTRZYMANY do nowego serwera.
- [ROZEZNANE: Przelewy24](plan-przelewy24-payments.md) — ZERO kodu; obowiązkowy `verify` po webhooku.
- [ODŁOŻONE: sklep na cudzej stronie](plan-embed-shop-on-external-site.md) — ZERO kodu; iframe odpada.
- [DZIAŁA: zakup pakietu online](plan-package-payments.md) — **NIE planować jako „do zrobienia".**
- [Panel admina — KOMPLETNY](plan-admin-panel-and-landing.md) · [Wiadomości do sprzedawców](plan-platform-mailing.md) — adresaci TYLKO przez `User::activeMarketingConsent()`.

## Pakiety / biznes
- [Cennik pakietów](pricing-packages.md) — Kram 0 / Stragan 750 / Pawilon 1500 zł/rok BRUTTO; drabinka 2,0×.
- [Przekreślona cena — ZAMKNIĘTE](open-monthly-plan-vs-struck-price.md) — **nie podnosić ponownie.**
- [Wygaśnięcie pakietu](plan-subscription-expiry.md) — karencja 7 dni, maile 14/7/1.
- [Plan: packages](plan-packages.md) — uprawnienia LEPKIE (snapshot per sklep).
- [Per-sklep własna cena](plan-per-shop-custom-pricing.md) — NIE betonować „pakiet→funkcje".
- [Gate: edycja zamówienia = Pawilon](gate-order-edit-behind-paid.md) · [Limity AI per pakiet](plan-ai-usage-limits.md) — pula ZADAŃ 100/400/800.
- [Kody rabatowe](plan-discount-codes.md) — 3 typy, 2 zakresy, rabat rozbijany na pozycje i stawki VAT.
- [Zwroty/odstąpienie 14 dni](legal-consumer-returns-withdrawal.md) — we WSZYSTKICH pakietach.
- [Kartoteka klientów](plan-customer-directory.md) — klucz = ADRES E-MAIL · [Newsletter](plan-bulk-mail.md) — tempo przez `scheduled_at`.
- [Własna analityka](plan-own-analytics.md) — `ShopAnalytics::for(?Shop)`, dwa zakresy. Rollup czeka.
- [Dashboard stats direction](dashboard-stats-direction.md) — własna analityka dla wszystkich; GA/GTM → płatny.
- [Audyt SEO](plan-seo-audit.md) — ZAMKNIĘTY 28.07.
- [Mapa strony + Search Console](plan-sitemap-search-console.md) — **GOTCHY: `public/robots.txt` MUSI nie istnieć; trasy centrali na KOŃCU routes.**

## Architektura / konwencje
- [Multi-tenant subdomain](multitenant-subdomain-architecture.md) — centrala = zarządzanie; storefront = subdomena; jedna baza + shop_id.
- [Naming & locale](naming-and-locale-convention.md) — URL/interfejs PL, kod EN; pl-first.
- [Frontend stack](frontend-stack-decision.md) — Livewire w panelach; storefront Blade-first; brak JSON API.
- [AI: zadania zamiast modeli](ai-task-profiles-architecture.md) — NIGDY nazwy modelu w kodzie.
- [Storefront theme system](storefront-theme-system.md) · [UI design direction](ui-design-direction.md) — panele „warm boutique".
- [Shared hosting constraints](shared-hosting-constraints.md) · [Destructive DB guard](defer-destructive-db-guard.md)
- [App timezone = Europe/Warsaw](app-timezone-warsaw.md) · [Migration to kramio.pl](migration-to-kramio.md) · [Subdomeny działają](front-blocked-on-subdomain-ssl.md)
- [Input normalization](input-normalization-conventions.md) — normalizuj w prepareForValidation; NIP mod-11.
- [Form client validation](form-client-validation-convention.md) — UX przez forms.js; Form Requesty = źródło prawdy.
- [Grupy radio w forms.js](form-radio-groups-in-forms-js.md) — **zmiana JS: najpierw build, POTEM atrybut w Bladzie.**
- [Text truncation](text-truncation-preserve-words.md) · [Email outbox + cron](email-outbox-cron-pattern.md)

## Gotchas techniczne
- [Artefakt udostępnia PRZYPIĘTĄ wersję](gotcha-artifact-share-pinned-version.md) — **republish jej NIE zmienia; każda zmiana = NOWY artefakt.**
- [TESTY NIGDY nie strzelają do API](tests-never-hit-real-apis.md) — INCYDENT 30.07: ~46 realnych faktur. `preventStrayRequests()` nigdy nie zdejmować.
- [TESTY NIGDY nie ruszają plików produkcji](tests-never-touch-production-files.md) — INCYDENT 04.08. GOTCHA: `Storage::fake()` gubi `url`.
- [Factory na produkcji = konto z hasłem `password`](gotcha-factory-on-production-db.md) — INCYDENT 16.08, zabezpieczone Warstwą 7.
- [Tailwind: klasa musi być w buildzie](tailwind-classes-must-exist-in-build.md) — grepuj `public/build/assets/*.css`.
- [route()/url() na subdomenie](gotcha-route-helpers-on-subdomain.md) — używaj `Central::url()`.
- [Livewire: ostrzejsza sygnatura = fatal](livewire-override-signature-fatal.md) — kopiuj sygnatury znak w znak.
- [Blade: blok @php psuje @php(...)](blade-php-block-breaks-inline-php.md) — rozwala CAŁY widok.
- [Blade: `@if` przyklejone do słowa](blade-directive-glued-to-word-not-compiled.md) — cały widok 500. **Trafione 2×.**
- [NIGDY zmiennej widoku `$errors`](blade-never-name-view-variable-errors.md) — nadpisuje worek walidacji.
- [Test: post() + get() gubi flash](gotcha-test-flash-lost-between-requests.md) — `from()` + `followingRedirects()`.
- [InPost pobranie: `company_data_missing`](gotcha-inpost-cod-requires-company-data.md) — patrzeć na transakcje, nie na status.
- [Klucz `stall` to Kram, nie Stragan](gotcha-package-key-stall-is-kram.md) — Stragan to `booth`.
- [Kotwica zjada nagłówek w Safari](safari-anchor-scrolls-overflow-hidden-parent.md) — `overflow-hidden` NIGDY na kontenerze z treścią.
- [Safari duch przy transition](safari-opacity-transition-ghost.md) · [Tailwind truncate rozpycha mobile](tailwind-truncate-breaks-mobile.md)
- [Vite build RAYON_NUM_THREADS=1](vite-build-rayon-threads.md) · [Orphaned build processes](orphaned-build-processes-incident.md)

## Moduły WDROŻONE (skrót)
- [Wiadomość serwisowa do sprzedawcy](plan-seller-service-notices.md) — 24.08. **POZA zgodą marketingową — to sens modułu.**
- [Przesyłki za pobraniem InPost](plan-cash-on-delivery.md) — 17.08. Pobranie = metody DOSTAWY. **Sklep z samym pobraniem MUSI przyjmować zamówienia.**
- [Wolna subdomena zaprasza do rejestracji](plan-unclaimed-subdomain-landing.md) — 12.08. Status zostaje 404/noindex.
- [Sklep bez dostawy i płatności](plan-catalog-mode-no-cart.md) — `Shop::acceptsOrders()`. GOTCHA: `factory()->sellable()` w testach koszyka.
- [Charakter sklepu](plan-shop-character-axes.md) — **nadpisywanie zmiennych Tailwinda v4 = ~200 klas bez rebuildu.**
- [Usunięcie sklepu](plan-shop-self-deletion.md) — karencja 7 dni, kwarantanna adresu 90 dni.
- [Zgoda na cookies](plan-cookie-consent.md) — blokada SERWEROWA, zgoda per host.
- [Karta sklepu na Facebooka](plan-shop-og-image-redesign.md) — GOTCHA: generowanie zablokowane w testach.
- [Fakturownia (FV/KSeF)](plan-fakturownia.md) — **brak sandboxa: każde żądanie tworzy REALNY dokument.**
- [Płatności Paynow](plan-online-payments-mbank.md) — PER-SKLEP; opłaty za Kramio = osobne konto platformy.
- [Wysyłki InPost](plan-shipping.md) — **data odbioru = start 14 dni na odstąpienie.** Zostało: test produkcyjny.
- [Kurier InPost](plan-inpost-courier.md) — GOTCHY: `sending_method` NIEODWRACALNY; `201` ≠ sukces.
- [Zgoda SPRZEDAWCY na oferty](seller-marketing-consent.md) · [Zgoda na mailing klientów](next-marketing-consent.md) — per SKLEP + link wypisu.
- [Order statuses](plan-order-statuses.md) · [panel zamówień](next-orders-panel-tab.md) · [badge](plan-new-orders-badge.md)
- [Customer accounts](plan-customer-accounts.md) — konta per-sklep. Gotcha: w destroy logout PRZED delete.
- [Odzyskiwanie hasła](handoff-2026-08-02.md) — sprzedawca = token brokera, klient = podpisany link per sklep.
- [Sale unit](plan-sale-unit-weight.md) · [NIP autofill + Trix](plan-nip-autofill-and-editor.md) · [Shop settings](plan-shop-settings-storage.md) · [Shop edit tabs](plan-shop-edit-tabs.md)
- [Bank transfer](bank-transfer-payment-method.md) · [Shop visibility](shop-visibility-auto-publish.md) — status z aktywnych produktów.
- [Omnibus 30d](omnibus-lowest-price-30d.md) · [Prose normalize on output](prose-normalize-on-output.md)
- [Wołacz ze słownika](plan-vocative-dictionary.md) — fallback = MIANOWNIK; zero LLM w runtime.
- [Mail footer](mail-footer-company-data.md) · [bez „automatycznie"](open-mail-footer-contradiction.md) · [tożsamość per sklep](per-shop-email-identity-branding.md)
- [Logo i favicona](brand-logo-assets.md) · [Custom brand color](plan-custom-brand-color.md) · [Storefront theming](plan-storefront-theming.md) · [Editorial + CMS](plan-storefront-editorial-and-pages.md)
- [Informacje left menu](next-informacje-left-menu.md) · [Coming-soon](next-redesign-coming-soon.md) · [Draft preview](storefront-draft-preview.md) · [Image treatment](storefront-image-treatment.md)
- [DeepSeek „Popraw przez AI"](deepseek-ai-improve.md) · [Korekta AI po fragmentach](plan-ai-chunked-correction.md) · [Streaming AI](plan-ai-streaming-response.md) — `AI_STREAMING=false` = wyłącznik.
- [Audyt obietnic landingu](landing-promises-drift-audit.md) — **landing dryfuje za kodem.** Jedno źródło = `PackageFeatures::highlights()`.
- [ROZWIĄZANE: zmyślone statystyki](open-landing-fabricated-stats.md) · [Stock availability](stock-availability-verification.md)

## Wizje / do analizy
- [Vision: email-driven orders](vision-email-driven-orders.md) — auto-przejścia statusów + zarządzanie z maila.
- [Shipping aggregator](shipping-aggregator-idea.md) — **ZAMKNIĘTE: Furgonetka WYPADA.**

## Handoffy (najnowsze u góry)
- [08-30 pulpit + analityka platformy](handoff-2026-08-30.md) — 1704→1713. **ZAMROŻENIE KODU do przeprowadzki; stary serwer zostaje żywy → wyłączyć na nim crona.**
- [08-24 awaria rejestracji + garda fabryk](handoff-2026-08-24.md) — 1693→1704. **2× pierwsza hipoteza groźniejsza niż prawda.** Pierwsi sprzedawcy wchodzą.
- [08-17 pobranie InPost + kopie 2×/dobę](handoff-2026-08-17.md) — 1657→1693. **Sonda na sandboxie przesądziła sens modułu.** Rafał 2× przeciął myślenie trafnie.
- [08-15 audyt prawny + DSA](handoff-2026-08-15.md) — 1653→1657. Rafał znalazł 5 wad na froncie. GOTCHA: Pint tylko na własnych plikach.
- [08-12 FB + wolna subdomena + BACKUP](handoff-2026-08-12.md) — 1599→1631. Baza to `host473413_kramio`. **Rafał sprostował 4×.**
- [08-11 panel admina KOMPLETNY](handoff-2026-08-11.md) — 1521→1599. GOTCHA: `assertSee` nie przechodzi przez łamanie linii Blade.
- [08-10 Sprzedawcy + Wiadomości](handoff-2026-08-10.md) — 1465→1521. GOTCHY: `assertDontSee` łapie pasek; kropka w klasie Tailwinda escapowana.
- [08-09 koszyk znika bez metod](handoff-2026-08-09-koszyk.md) — **Rafał przeciął diagnozę „usterka" ramą „to legalny sposób użycia".**
- [08-09 limity produktów](handoff-2026-08-09.md) — GOTCHA: zmiana limitu wywraca testy zejść z pakietu.
- [08-08 InPost kurierem](handoff-2026-08-08-inpost-kurier.md) — **wzorzec: „a może po prostu spróbuj".**
- [08-08 charakter sklepu](handoff-2026-08-08.md) · [08-07 InPost ShipX](handoff-2026-08-07-inpost.md) — **Rafał znalazł 2 błędy prawne; DOPYTYWAĆ o reguły biznesowe.**
- [08-07 szablony + dokumenty v2](handoff-2026-08-07-szablony-i-dokumenty.md) · [08-05 garda dysków](handoff-2026-08-05.md)
- [08-04 usuwanie sklepu](handoff-2026-08-04.md) — **GOTCHA: katalog produkcyjny = produkcja, o `migrate --force` pytać OD RAZU.**
- [08-03 logo + mapa strony](handoff-2026-08-03.md) · [08-02 karta sklepu + cookies](handoff-2026-08-02.md) — GOTCHA: `config()` NIE DZIAŁA w bootstrap/app.php.
- [07-31 GA na centrali](handoff-2026-07-31-analytics.md) — **3× ta sama luka: funkcja dla SKLEPÓW, nie dla centrali.**
- [07-31 OG + formularze](handoff-2026-07-31-security.md) · [07-31](handoff-2026-07-31.md) · [07-30](handoff-2026-07-30.md) · [07-29](handoff-2026-07-29.md) · [07-28](handoff-2026-07-28.md)
- [07-27 SEO](handoff-2026-07-27-seo.md) · [07-27](handoff-2026-07-27.md) · [07-25](handoff-2026-07-25.md) · [07-24](handoff-2026-07-24.md) · [07-20](handoff-2026-07-20.md) · [07-16](handoff-2026-07-16-quick.md)
- [07-15 promoted](handoff-2026-07-15-promoted-pages.md) · [07-15 messenger](handoff-2026-07-15-messenger.md) · [07-14 statusy](handoff-2026-07-14-statuses.md) · [07-14](handoff-2026-07-14.md)
- [07-11 produkt](handoff-2026-07-11-product.md) · [07-11 storefront](handoff-2026-07-11-storefront.md) · [07-11](handoff-2026-07-11.md) · [07-10](handoff-2026-07-10.md) · [07-04](handoff-2026-07-04.md) · [07-03](handoff-2026-07-03.md) · [07-02](handoff-2026-07-02.md) · [07-01](handoff-2026-07-01.md) · [06-30](handoff-2026-06-30.md) · [06-29](handoff-2026-06-29.md)
