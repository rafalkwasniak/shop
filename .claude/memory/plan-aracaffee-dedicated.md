---
name: plan-aracaffee-dedicated
description: "Aracaffee jako sklep dedykowany (3 000 zł) — plan z 02.11.2026, czekamy aż Rafał założy domenę aracaffee.kwasniak.org i bazę"
metadata:
  node_type: memory
  type: project
  originSessionId: 9c501d96-4768-4b9f-85ee-e238ae5cd3f1
  modified: 2026-11-02T16:05:28.028Z
---

02.11.2026 Rafał: Aracaffee (demo shop 20, patrz [[demo-shop-aracaffee]]) dostaje **własną instalację w trybie dedykowanym**, jak Magellan ([[plan-magellan-bay-separate-project]]). Zlecenie 3 000 zł (Magellan 6 000) — Kramio zarobiło 9k na wdrożeniach, zanim ma własnych płacących sprzedawców.

**Ustalenia (odpowiedzi Rafała):**
- Adres roboczy `aracaffee.kwasniak.org`; po akceptacji klienta przeprowadzka na serwer docelowy z domeną klienta.
- Przenosimy **tylko sam sklep** (ustawienia, kolory, teksty, strony, produkty, tagi, regulamin, konfiguracja punktów, konto właściciela, pliki z `storage/app`). **BEZ klientów, zamówień, historii punktów.** Nigdy zrzut całej bazy Kramio.
- Po przeniesieniu **sklep 20 w Kramio usuwamy bez śladu** (rekordy + pliki).
- Lista zmian klienta: Rafał przygotuje dopiero, gdy nowy sklep będzie działał.

**Plan techniczny:** źródło = Kramio `main` (NIE Magellan — ma bespoke funkcje magnesów). Z repo Magellana dociągnąć tylko `DeploymentSeeder` + `config/deployment.php` (w Kramio ich nie ma). Pakiet `dedicated` ma `loyalty_points` — sprawdzone. Zaszyfrowane `shop_integrations` nie przejdą (inny APP_KEY) — Aracaffee i tak ich nie ma.

**Na serwerze (Rafał):** domena z docroot `public` + SSL, pusta baza z użytkownikiem BEZ dostępu do bazy Kramio, skrzynka pocztowa do wysyłki, osobny webhook Discorda, repo GitHub; cron dopiero po uruchomieniu.

**STAN 02.11 WIECZÓR: SKLEP STOI.** https://aracaffee.kwasniak.org działa; cron dopisany; poczta = skrzynka Magellana (decyzja Rafała: „nie zakładać sztucznie dla demo"); osobny webhook Discorda; remote `github` = `git@github.com-aracaffee:rafalkwasniak/aracaffee.git` (klucz `id_ed25519_aracaffee`). **CP zrobione 02.11: commit `598249b` wypchnięty na `github/main` (repo prywatne). Suita 2012 zielonych.** CLAUDE.md w repo Aracaffee to wciąż kopia Kramio — do przepisania przed pracą z sesji otwartej w tamtym katalogu.
- Z Magellana przeniesione (cherry-pick -n, bez docs_mod/.claude/CLAUDE.md/DemoSeeder/grafik): `8d71c7c` seeder, `cd91813` Shop::host() w trybie dedykowanym + config/brand + wzór polityki, `bff745e` polityka = strona sklepu, `efb3e7d` wzory od 1. dnia + bez og Kramio, `0329ed0` zgłoszenia DSA do właściciela (w Kramio-bazie szły do wyłączonej konsoli admina = w próżnię!), `7b5f2e3` maile od sklepu. „Biały port" pominięty. Przy scalaniu `->shop` → `currentShop()` (pracownicy), trasy zgłoszeń + pozycja w menu tylko dla właściciela (`role:seller`).
- Dodane tu: wyłącznik `SEO_INDEXING` (robots `Disallow: /` na adresie roboczym — **na domenie klienta przestawić na true**), cookie banner z `config('app.name')` zamiast „Kramio", logo/favicona z logo sklepu (158 px — miękkie, poprosić o większe).
- Import: ID zachowane (shop 20, user 22, produkty 81–102), hasło właściciela przeniesione, pakiet `dedicated`+comped, `last_order_number=0`, regulamin przegenerowany z `terms_answers` (wypadła „platforma Kramio"), polityka prywatności założona. Skrypty: scratchpad `aracaffee/import.php`. Eksport JSON skasowany.
- Znane: `/regulamin` (dokument platformy) pokazuje „w przygotowaniu" — linkowane tylko z ekranu aktywacji/rejestracji.
- **AI (02.11):** w `.env` Aracaffee I Magellana wpisany klucz DeepSeek z KRAMIO (decyzja Rafała — klient ma testować jak w Kramio). **Na serwerze klienta: klucz KLIENTA, nie nasz.** Propozycja (niezrobiona): ukrywać przycisk „Popraw przez AI”, gdy AI nieskonfigurowane — dziś mylący komunikat „chwilowo niedostępna”.
- **Logowanie Rafała (02.11):** na koncie klienta `tekawa7@gmail.com` ustawione NOWE hasło (podane Rafałowi w czacie, nigdzie nie zapisane) + `LOGIN_TWO_FACTOR=false` w `.env` instalacji roboczej. **Przed przekazaniem: `LOGIN_TWO_FACTOR=true` i klient ustawia własne hasło (reset).**
- Poprawione też: zgody na dokumenty platformy pomijane w trybie dedykowanym (`User::outstandingConsents`), 3 zdania o Kramio w panelu warunkowe od trybu.
- ✅ **Sklep 20 w Kramio USUNIĘTY 02.11** (na prośbę Rafała, przed akceptacją klienta). ~~Po akceptacji klienta: skasować shop 20 w Kramio (rekordy + pliki) bez śladu.~~

**FORMULARZ, BANERY, ZDJĘCIA W STRONACH SĄ JUŻ W KRAMIO (02.11, `500459e`).**
**LISTA ZMIAN KLIENTA (02.11) — kolejność:** 1) formularz kontaktowy ✅ `e60c580` (cały ogólny, gotowy do cherry-picka do Kramio; tam zdanie o formularzu dopisać w polityce PLATFORMY), 2) banery/slider ✅ `bb246c4` (szerokość kolumny treści, wysokość = proporcje PIERWSZEGO banera — **NIE dokładać limitu wysokości/przycinania: Rafał 02.11 wytknął złamanie ustaleń; „niższy” = niższe obrazki, nie mniejsza kanwa**; zalecany 1920×540; limity Kram 1/Stragan 6/Pawilon 10/dedykowany 10; w sklepie 3 banery z produktów 90/92/100 zrobione ImageMagickiem, skrypt scratchpad `banner.sh`), 3) zdjęcia w stronach ✅ `4c4efe1` (limit na CAŁY sklep 5/20/40, dedykowany bez limitu; po zejściu z pakietu zdjęcia ZOSTAJĄ, blokada tylko nowych — świadome odstępstwo od „funkcja musi gasnąć”, decyzja Rafała 02.11). ODŁOŻONE: zamówienie „na umowę Tekawa" (opcje Rafała: wieczny kod 100% z limitami uses/kwota na miesiąc, albo opcja dla wszystkich z dopiskiem o umowie) — czeka na info od klienta. NA KONIEC: inna bramka płatności (klient nie wybrał operatora).

**STAN 02.11 (przed przerwą):** kod sklonowany do `/home/host473413/domains/aracaffee.kwasniak.org` (Kramio `f915e71`, origin = katalog Kramio, tożsamość lokalna, `core.fileMode false`), composer zrobiony, `public/build` skopiowany z Kramio, `storage:link`, `.env`: `SHOP_MODE=dedicated`, `APP_ENV=staging`, `MAIL_MAILER=log`, `BACKUP_ENABLED=false`, baza `host473413_aracaffee` podpięta (pusta, izolacja od Kramio sprawdzona). Docroot = `public` potwierdzony. **Migracji NIE puszczono.**
**NASTĘPNY KROK (Rafał chce skończyć całość w tej sesji):** cherry-pick z Magellana `8d71c7c` (seedery), `efb3e7d` (wzory dokumentów + bez og Kramio), `7b5f2e3` (maile od sklepu); `1574902` (Biały port) pomijamy → migrate → DeploymentSeeder → eksport shop 20 → testy. Brakuje jeszcze: skrzynki pocztowej (2FA!), webhooka Discorda, repo GitHub, crona.

**How to apply:** gdy Rafał wróci z „serwer gotowy" — ruszać od etapu 0 (kopia kodu), nie planować od nowa.
