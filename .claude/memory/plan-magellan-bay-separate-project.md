---
name: plan-magellan-bay-separate-project
description: "AKTYWNE od 25.08: sklep Magellan Bay — osobne WDROŻENIE tego samego kodu (nie fork, nie najemca Kramio). Oferta napisana: wdrożenie + abonament roczny. Ceny do przemyślenia przez Rafała."
metadata: 
  node_type: memory
  type: project
  originSessionId: 85301e1d-9f9e-4502-a6f9-d1381d0aa86c
  modified: 2026-09-01T12:22:30.291Z
---

**To NIE jest część roadmapy Kramio.** Osobny klient, osobne pieniądze. Nie dopisywać do zaległości Kramio.

> ## STAN 2026-08-25: OFERTA WYSŁANA DO KLIENTA — CZEKAMY NA ODPOWIEDŹ
>
> Nic nie robimy, dopóki klient nie odpowie. **Nie proponować prac, nie planować kodu.** Jeśli Rafał wróci do tematu: najpierw zapytać, jaka była odpowiedź klienta.
>
> Klient poprosił o wycenę. Dokumenty (aktualizować pod TYMI adresami, nie tworzyć nowych):
> ## STAN 2026-09-01: KLIENT WYBRAŁ ZAKUP, OFERTA ETAPOWA WYSŁANA
>
> Klient odrzucił abonament („starej daty, chce mieć swoje"). Poprosił o rozbicie na etapy i o **PDF, bo linki były dla niego nieczytelne**. Finalna oferta — krótka, dwustronicowa, plik `scratchpad/oferta-magellan-zakup.html` (poza repozytorium, PDF przez Ctrl+P, ma arkusz `@media print` z wymuszoną jasną kolorystyką):
>
> | | Kwota | Czas |
> |---|---|---|
> | Etap 1 — licencja na silnik + wdrożenie | 4 000 zł | 2 dni robocze |
> | Etap 2 — rozbudowa o funkcje Magellan Bay | 2 000 zł | 4 dni robocze |
> | **Razem** | **6 000 zł netto** | **6 dni roboczych** |
>
> Prace dodatkowe 100 zł/godz. Gwarancja 12 mies. **Płatność CAŁOŚĆ PO ODBIORZE, 7 dni, bez zaliczki** — świadoma decyzja Rafała, bo pośrednik jest sprawdzony i może przyprowadzić kolejnych klientów. Termin biegnie od przekazania materiałów i dostępów. Serwer: **docelowo u klienta**, u Rafała tylko środowisko robocze na czas budowy.
>
> **6 dni to realny termin** — Rafał ma wolne i pracuje po 12 h dziennie. Nie kwestionować.
>
> **Podział 4 000 / 2 000 to decyzja Rafała** (klient sam prosił o rozbicie „kod który masz / kod do napisania"). Odradzałem rozbijanie po koszcie; kompromis: Etap 1 nazwany „licencja na silnik + wdrożenie" z widoczną listą funkcji, a nie „kod, który już mam". Temat zamknięty.
>
> **STARE, DŁUGIE OFERTY (nieaktualne, zastąpione powyższą):**
> - **A. Abonament** (2 000 zł wdrożenie + 2 400 zł/rok, sklep u nas): https://claude.ai/code/artifact/e53e0035-b8a2-40b0-bfb0-7aba3d25e9f2 — *poprzedni adres `3572ccac` PORZUCONY 31.08, zamarł na wersji sprzed doprecyzowania zakresu*
> - **B. Bez abonamentu** (6 000 zł jednorazowo, sklep na serwerze klienta): https://claude.ai/code/artifact/27b9f4c1-4dcd-456e-b84f-65ad994ff82e
>
> Wariant B powstał **31.08 na prośbę klienta — „klient starej daty", nie chce się wiązać abonamentem, chce mieć swoje i wiedzieć, że nikt mu tego nie wyłączy.** Dlatego dokument NIE jest pisany tak, żeby przegrać — akcentuje własność i niezależność, ale uczciwie nazywa, co klient bierze na siebie. Punkt równowagi kosztów: ~20 miesięcy (6 000 vs 2 000 + 2 400×n), a po stronie klienta dochodzi jeszcze jego własny hosting.
>
> **Ustalenia wariantu B (odpowiedzi Rafała):** gwarancja **12 miesięcy** (usterka = coś miało działać i nie działa); klient **dostaje kod, ale nie wolno mu go zmieniać** — ingerencja = utrata gwarancji; **zakaz odsprzedaży** sklepu i kopii; **brak prawa do przyszłych wersji** silnika; poczta i panel admina platformy wypadają (klient ma panel sklepu); wymagania serwera spisane w dokumencie, obejścia braków hostingu płatne 100 zł/h. Rafał liczy na **sprzedanie kilku takich kopii innym klientom** — stąd licencja, nie przeniesienie praw.
> - ~~Stara oferta `54e76022-b162-48a7-b8b8-f646e793aa93`~~ — **PORZUCONA 31.08.** Miała zablokowaną przypiętą wersję z cenami 6000/3000/150 i przepięcie nie działało; Rafał miał wyłączyć jej udostępnianie. **Nie używać, nie aktualizować.** Patrz [[gotcha-artifact-share-pinned-version]].
> - **Zestawienie „co mamy / co zbudujemy":** https://claude.ai/code/artifact/73d418cc-af26-4891-affb-a301e5f68427
>
> **DOKUMENTY HANDLOWE: `docs/` = PRZEPUSTKA, NIE ARCHIWUM.** Rafał nie ma dostępu FTP do `/tmp`, więc pliki dla niego kładziemy w `docs/` — ale tylko **na czas przeniesienia**; on je ściąga i kasuje (*„ten projekt to nie miejsce na takie archiwa"*). Zawsze uprzedzać, że plik jest nieśledzony i `git add .` wciągnąłby go z cenami. **Przy najbliższym CP sprawdzić, czy w `docs/` nie została jakaś oferta.**
>
> **SPRZEDAŻ IDZIE PRZEZ POŚREDNIKA** — firmę, z którą Rafał współpracuje; doliczy ok. 10% marży za klienta. Konsekwencja: dokument z cenami zaadresowany do klienta końcowego **psuje pośrednikowi marżę** (klient zobaczyłby dwie różne kwoty). Przed wysyłką ustalić z pośrednikiem: wersja bez cen dla klienta + osobna wycena dla pośrednika / ceny finalne z jego marżą / dokument zaadresowany do pośrednika. Wycięcie sekcji cen to jedna edycja.
>
> **CENY USTALONE przez Rafała 25.08 (netto):** wdrożenie **2 000 zł**, abonament **2 400 zł/rok** (200 zł/mies, płatne z góry od uruchomienia), prace dodatkowe **100 zł/godz.** Termin: do 4 miesięcy z deklaracją wcześniejszego oddania. W pliku HTML sekcja cen ma komentarz `<!-- ====== CENY — TU EDYTOWAĆ KWOTY ====== -->`.
>
> **TEMAT CENY ZAMKNIĘTY — nie wracać do niego.** Rafał świadomie traktuje to jako inwestycję: ma wolny czas, liczy na pipeline od pośrednika (dużo zapytań o sklepy), a case study jest wartością samą w sobie. Argument „za tanio" został wypowiedziany i odrzucony z uzasadnieniem.
>
> **MOJE SZACUNKI CZASU BYŁY ZAWYŻONE — korekta 25.08.** Liczyłem dni z dat commitów, a Kramio powstawało wieczorami po 2–3 h; mój „dzień" to często 3 h pracy. Rafał podał twardy kontrprzykład: projekt dla architektów wyceniony przeze mnie na 40–45 h zrobiony w **15 h mierzonych w Clockify**. **Przy szacowaniu dla Rafała dzielić moje pierwsze liczby mniej więcej na pół** i nie straszyć terminami — zgłosi sam, jeśli coś się rozjedzie.

## MODEL WDROŻENIA — USTALONY 25.08

**DWIE INSTANCJE tego samego kodu na nowym serwerze** (decyzja Rafała, doprecyzowana na końcu sesji):

| Instancja | Co tam stoi | Kto tam mieszka |
|---|---|---|
| **Kramio** | Zostaje JAK JEST — rejestracja, pakiety, landing, samoobsługa | Sprzedawcy z ulicy |
| **Sklepy dedykowane** | Ten sam kod, własna baza, domena podpinana per klient | Magellan, potem kolejni (kubki itp.) |

Panel admina i panel sprzedawcy działają w obu bez zmian. **Nowy klient dedykowany = rekord sklepu + własny front.** Model docelowy: 2 000 zł za start staje się przy kolejnych klientach głównie zarobkiem (4–5 h roboty), a abonamenty się kumulują (10 klientów = 24 000 zł/rok). Szczegóły serwera do omówienia, **gdy zlecenie „siądzie"**.

### KIERUNEK RAFAŁA (01.09): NAJPIERW PODSTAWA BEZ ABONAMENTU, POTEM ROZBUDOWA POD KLIENTA

**Nie budować Magellana jako jednorazowej roboty.** Rafał chce najpierw wydzielić **bazowy sklep bez warstwy abonamentowej** — czysty punkt wyjścia — a dopiero na nim robić rozbudowę pod konkretnego klienta.

Sens: przy kolejnym kliencie, który zechce **innej** rozbudowy, nie zaczyna się od rozplątywania Magellana ani od Kramio z pakietami, tylko od gotowej podstawy. Etap 1 oferty (4 000 zł, 2 dni: „postawienie instancji + implementacja pod klienta") to w praktyce **wydzielenie tej podstawy** — robione raz, sprzedawane wielokrotnie.

Konsekwencja dla kodu: to, co wypada przy Magellanie (rejestracja, pakiety, abonament, landing centrali, panel admina platformy), ma zostać **wyłączone konfiguracją, nie wycięte** — inaczej przy każdym kliencie robi się to od nowa. Patrz reguła „jeden kod, dwa wdrożenia" niżej.

### JAK ZROBIĆ DEDYKOWANY FRONT (zweryfikowane w kodzie 25.08)

Tanio, bo wszystkie kontrolery storefrontu wołają widoki płasko: `view('storefront.home')`, `view('storefront.product')` — 24 pliki, jedna konwencja. `shops.template` już trzyma slug per sklep.

**Mechanizm:** gdy `ResolveShop` rozpozna sklep, dokłada jego katalog widoków PRZED wspólny. `storefront.home` znajduje najpierw `storefronts/{slug}/home.blade.php`, a gdy pliku nie ma — **spada na wspólny**. Dzięki fallbackowi nadpisuje się 4–6 plików, nie 24, a reszta dziedziczy późniejsze poprawki.

**UWAGA na obietnicę „4–5 h przy kolejnym kliencie":** tyle kosztuje HYDRAULIKA. Projekt graficzny nowego frontu od zera to nadal 2–4 dni. Tanio jest tylko przy wariancie istniejącego wyglądu.

**Jeden kod, nie dwa kody. NIE najemca Kramio.**

- Magellan = osobna **instalacja** tego samego repozytorium: własna baza, własny katalog, własny `.env`. Izolacja działającego sklepu **całkowita** — wdrożenie u niego fizycznie nie może ruszyć Kramio.
- **Silnik personalizacji idzie do WSPÓLNEGO kodu, generycznie** (formatki, opcje z dopłatą, cena składana, koszyk per konfiguracja) — żeby się amortyzował na kubkach/koszulkach/grawerze jako przyszły 4. pakiet Kramio (drabinka 0/750/1500 → **3000** pasuje idealnie).
- Kartoteka licencjodawców, reguła „wyższa nie suma" i raporty rozliczeniowe zostają bespoke.

### REGUŁA, GDZIE TRAFIA KAŻDA PROŚBA KLIENTA (ustalona z góry, żeby nie improwizować)

| Klient chce | Gdzie idzie |
|---|---|
| Stripe / inna bramka | Wspólny kod, sterownik **domyślnie wyłączony**. Kramio go nie widzi (brak w pakiecie) |
| Inny wygląd, obrazki, sekcje | Jego warstwa widoków. Zero kontaktu z Kramio |
| Zmiana w kasie | Najpierw jako opcja konfiguracyjna; jeśli się nie da — jego nadpisanie widoku |
| **Zmiana w logice koszyka/zamówienia** | **CZERWONA LAMPKA** — tędy idą pieniądze wszystkich sklepów. To moment na twardy fork i osobną wycenę |

**Nadpisywać jak najmniej.** Komponenty w `resources/views/components/storefront/` (7 plików: `product-card`, `order-totals`, `delivery-summary`, `breadcrumbs`, `tag-cloud`, `account-shell`, `information-shell`) mają zostać wspólne — wtedy poprawki dziedziczą się do obu.

### DLACZEGO NIE NAJEMCA KRAMIO — dowód z kodu (zweryfikowany 25.08)

**Motywy Kramio NIE UMIEJĄ zmienić układu strony.** `config/themes.php` definiuje szablon jako paletę (4 tokeny: `brand`, `brand_ink`, `surface`, `ink`) + `chrome` + `chrome_texture` + `card_mix`, wstrzykiwane serwerowo jako zmienne CSS. Jest **dokładnie jeden komplet widoków** — 24 pliki w `resources/views/storefront/` i jeden layout `components/layouts/storefront.blade.php`. Katalogów per szablon **nie ma w ogóle**.

Czyli każdy sklep w Kramio ma identyczny układ strony głównej, karty produktu i kasy — różni się tylko kolorami i fakturą paska. Dać klientowi własny wygląd = dobudować Kramio całą warstwę widoków per szablon, dla jednego klienta. **To przesądziło sprawę.**

Argument Rafała, który był decydujący: *„za 3 miesiące Magellan przyjdzie i powie, że chce Stripe, bo ma taki kaprys. A później, że chce zmienić coś w kasie, bo ma przeświadczenie, że to jego."*

## Czego dotyczy

Klient (sklep „Magellan Bay") chce sklep z magnesami podróżniczymi. Model sprzedaży inny niż wszystko, co obsługuje Kramio:
- **personalizacja 2-poziomowa** — nadruk na awersie z „formatek" (zestawów pól tekstowych z limitami znaków) + grawer na rewersie (grafika z biblioteki ALBO tekst, nigdy oba),
- **cena składana z 4 części** widocznych klientowi przy zamawianiu: produkt / licencja za logotyp awersu / wykonanie graweru / licencja za grafikę graweru,
- **licencjodawcy** — firmy trzecie (organizatorzy biegów itp.) inkasujące opłatę za użycie logotypu; dwie licencje TEJ SAMEJ firmy na jednym produkcie **nie sumują się, liczy się wyższa**,
- trzy niezależne osie kategorii, jedna hierarchiczna (geografia).

## REKOMENDACJA: dedykowany sklep na kodzie Kramio (droga 3 z trzech rozważanych)

Rafał rozważał trzy drogi. Ocena, którą przyjął jako podstawę do decyzji:

**1. Moduł wbudowany w Kramio — NIE.** Koszt nie jest w kodzie, tylko w **ekranie ustawień produktu**: obietnica „15 minut" umiera od każdego kolejnego pola, którego 95% sprzedawców nie potrzebuje. Dodatkowo Magellan Bay jest **złym nauczycielem** tej funkcji — jego wymagania są zdominowane przez model licencyjny, którego nie ma żaden inny sprzedawca, więc generyczna personalizacja wyszłaby w kształcie cudzych tantiem. Klasyczne uogólnianie z jednego przypadku.

**2. Drugi SaaS na bazie Kramio — NAJGORSZA z trójki.** To złożoność drogi 1 plus duplikacja drogi 3, bez korzyści żadnej z nich. SaaS kosztuje w **zobowiązaniu** (rozliczenia, onboarding, pakiety, wsparcie, prawne obowiązki platformy), utrzymywanym podwójnie, dla rynku **jednego potwierdzonego klienta**, w momencie gdy Kramio ma pierwszych sprzedawców od tygodnia. „SaaS dla produktów personalizowanych" to zresztą niespójna kategoria — klient sam pisze, że ma też produkty zwykłe.

**3. Dedykowany sklep na forku — TAK.** Argument, który podał sam Rafał: *to, czego potrzebuje 1 klient, często nie idzie w parze z 20 innymi*. W dedykowanym sklepie ten konflikt **przestaje istnieć**.

## Dlaczego duplikacja kodu jest tu mniejszym problemem, niż się wydaje

Kontrargument „a `shop.kwasniak.org` był 247 commitów za produkcją" **nie przenosi się**: tamto była kopia PRZYPADKOWA, o której zapomniano. Fork z jasną datą cięcia to co innego. Kluczowe: **dedykowany sklep w pewnym momencie jest skończony**, Kramio rośnie dalej i nie musi być śledzone. Duplikacja boli tylko wtedy, gdy oba kody rosną w tę samą stronę.

Realna lista obowiązkowej synchronizacji: **poprawki bezpieczeństwa + zmiany API dostawców** (InPost, Paynow, Fakturownia) — kilka zdarzeń w roku. **Funkcji nie przenosi się w ogóle.** Dokumenty prawne i tak są per podmiot.

## Decyzje techniczne, gdyby ruszyło

- **Wielonajemczości NIE wycinać.** Zostawić uśpioną: jeden rekord sklepu, `shop_id` wszędzie jak dziś. Wyrywanie jej ze ścieżki pieniędzy to tygodnie roboty i realne ryzyko błędu przy zysku czysto kosmetycznym. Wyłączyć tylko to, co widać: rejestrację, pakiety, abonament, landing centrali.
- **Panel sprzedawcy staje się panelem admina klienta** — spory kawałek zaplecza odpada za darmo.
- **Własna domena przestaje być problemem** (w Kramio jej nie ma i to blokowałoby wariant „najemca").

## GDZIE JEST PRAWDZIWA PRACA (i prawdziwe ryzyko)

Nie w liczbie funkcji, tylko w tym, że **zmienia się kształt pozycji koszyka i pozycji zamówienia** — obiektu, przez który przechodzą wszystkie pieniądze. Stan na 25.08:
- `CartService` trzyma w sesji `[shop_id => [product_id => qty]]` — klucz to product_id, **brak miejsca na konfigurację**,
- `order_items` to płaska migawka (`name`, `unit_price_gross`, `vat_rate`, `quantity`, `line_total_gross`) — **brak miejsca na rozbicie ceny**,
- `products` ma jedno `price_gross` i jedno `vat_rate`; **wariantów/opcji nie ma w ogóle** (`grep -rn variant app/` → zero),
- kategorie to płaski `Tag`, w docblocku wprost „Bez hierarchii".

Czytają to: kody rabatowe (rozbijają rabat na pozycje i stawki VAT), faktury, zwroty, Omnibus, statystyki. **Wyceniać jako budowę aplikacji, nie konfigurację.**

## Czego NIE MA w opisie klienta, a bez czego model nie działa

- **rozliczenia z licencjodawcami** — „pasywny zysk" partnera wymaga raportu, ile sprzedano z czyim logo i ile się należy. To jest właściwy model biznesowy tego sklepu i wypłynie w drugim miesiącu.
- **arkusz produkcyjny** — eksport danych do nadruku i pliku do graweru per sztuka; bez tego zamówienia są niewykonalne.

Sprzeczności w specyfikacji (przykład 3 się nie liczy: 15 zł vs +20 vs razem 20; dwa sprzeczne kodowania „brak personalizacji"; koszt wykonania graweru nie występuje w żadnej tablicy; reguła „nie sumujemy" nie rozstrzyga przypadku DWÓCH RÓŻNYCH licencjodawców) — spisane, ale Rafał świadomie odłożył, bo nie rozstrzygają jego dylematu.

## Dokument dla klienta — JUŻ ISTNIEJE

Artifact „Sklep Magellan Bay": https://claude.ai/code/artifact/73d418cc-af26-4891-affb-a301e5f68427
Zawiera: uzasadnienie osobnego sklepu + 9 grup rzeczy gotowych + 12 pozycji do zbudowania, językiem nietechnicznym. **Aktualizować pod tym samym URL-em**, nie tworzyć drugiego.

Powiązane: [[priorities-launch-first]], [[migration-to-kramio]], [[shared-hosting-constraints]], [[legal-consumer-returns-withdrawal]].

---

## ODCIĘCIE WYKONANE 2026-09-05 — dalsze prace w repozytorium Magellana

**Kroki 1–3 (tryb dedykowany) są w Kramio na `main`** — to baza wielokrotnego użytku, nie robota pod jednego klienta: pakiet `dedicated`, przełącznik `SHOP_MODE`, sklep na domenie głównej. Suita 1728 zielona, produkcja Kramio zweryfikowana po scaleniu (centrala + 5 realnych storefrontów).

**Kramio jest posprzątane z rzeczy Magellana** — `docs_mod/` przeniesione, `.gitignore` przywrócone, `grep -i magellan` po kodzie nie zwraca nic.

### Gdzie teraz mieszka ta praca

| | |
|---|---|
| Katalog | `/home/host473413/domains/magellan.kwasniak.org` |
| Adres roboczy | https://magellan.kwasniak.org |
| Repo | `git@github.com-magellanbay:rafalkwasniak/magellanbay.git` (klucz `id_ed25519_magellanbay`) |
| Baza | `host473413_magellan` — bez dostępu do bazy Kramio |
| Właściciel | `magellan@kwasniak.org`, logowanie pod `/sprzedawca/logowanie` |
| **Pamięć** | odtworzona pod kluczem `-home-host473413-domains-magellan-kwasniak-org` (164 pliki) |

**Rafał pracuje nad Magellanem z sesji otwartej W TAMTYM KATALOGU.** Dokument wejściowy: `docs_mod/00-STAN-I-CO-DALEJ.md`. Do zrobienia tam: branding, seeder wdrożeniowy i funkcje z Etapu 2 oferty.

**W Kramio nie ma już nic do zrobienia dla Magellana.** Gdyby padło pytanie o ten projekt z sesji Kramio — odesłać do tamtego katalogu.

### Demo do testów

`lemoniady.kramio.pl` to demo Rafała — dobry punkt odniesienia przy sprawdzaniu storefrontu. `ilikemybike` z dawnych notatek **już nie istnieje**, 404 pod tym adresem jest poprawną odpowiedzią.
