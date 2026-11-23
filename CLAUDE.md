# CLAUDE.md

Specyfika projektu **Kramio** (`kramio.pl`). Plik czytany przez asystenta na starcie każdej sesji.

> **Przeniesienie (2026-06-28):** serwis przeniesiony z tymczasowego `shop.kwasniak.org` do docelowego `kramio.pl` (ten sam katalog domeny na hostingu). Baza wtedy bez zmian (`host473413_shop`; od 12.08 → `host473413_kramio`, patrz sek. 2). Pamięć Claude skopiowana do klucza nowego projektu.
>
> **Sprzątanie (2026-08-13):** stary katalog `…/domains/shop.kwasniak.org` **USUNIĘTY**. Po zmianie nazwy bazy jego `.env` (`host473413_shop`) przestał się łączyć, a że miał `SESSION_DRIVER=database`, `APP_NAME=Kramio` i ten sam webhook Discorda, każde przypadkowe wejście na starą domenę wyglądało jak awaria Kramio. Nic unikalnego tam nie było: kod na `origin/main` (247 commitów za produkcją), `storage/app` pusty, baza wspólna. **Zostało do zrobienia przez Rafała: usunąć domenę `shop.kwasniak.org` w panelu hostingu** (dziś zwraca 404 od Apache'a).

**Relacja do `FOUNDATION.md`:** `FOUNDATION.md` = uniwersalne zasady współpracy (te same w każdym projekcie). Ten plik = specyfika tego projektu (domena, stos, decyzje produktowe, stan środowiska). W kwestiach projektowych `CLAUDE.md` ma pierwszeństwo; zasady współpracy z `FOUNDATION.md` obowiązują zawsze.

`FOUNDATION.md` opisuje pracę nad **API**. Tutaj robimy **cały serwis**, więc część zasad czysto-API jest warunkowa lub wstrzymana do decyzji (patrz: Decyzje otwarte oraz Stosowanie FOUNDATION).

---

## 1. Projekt

- Domena: `kramio.pl` (centrala platformy). Wcześniej tymczasowo `shop.kwasniak.org`.
- Charakter: serwis typu sklep (pełny zakres dopiero poznajemy — szczegóły produktowe dochodzą).
- Świeża instalacja szkieletu Laravela jako punkt wyjścia; właściwy plan budujemy małymi krokami.

---

## 2. Stos i środowisko

- **Laravel Framework 13.17** (`laravel/framework: ^13.8`).
- **PHP 8.5.7** na stronie WWW (`/opt/alt/php85`). To jest runtime docelowy.
- **UWAGA na CLI:** domyślne `php` w shellu to `/usr/local/bin/php` = **PHP 8.3**, nie 8.5. Aby zachować parytet z webem, `artisan`, `composer` i skrypty PHP uruchamiamy jawnie przez:
  ```bash
  /opt/alt/php85/usr/bin/php artisan ...
  /opt/alt/php85/usr/bin/php $(which composer) ...
  ```
- **Composer** 2.9.7.
- **Baza:** MySQL, połączenie `mysql`, baza `host473413_kramio` (dostęp w `.env`). Sesje, kolejka i cache na sterowniku `database`. **Zmiana nazwy 2026-08-12:** dane przeniesione zrzutem SQL ze starej `host473413_shop` (MySQL nie umie `RENAME DATABASE`, panel też nie — to była kopia, nie zmiana etykiety). Zgodność zweryfikowana: 39 tabel, sumy kontrolne, klucze obce, indeksy i `AUTO_INCREMENT` bez różnic. Baza ćwiczebna do odtworzeń: `host473413_kramio_backup`.
- **Node 20.20 / npm 10.8** dostępne.
- **Document root** domeny `kramio.pl` musi wskazywać na `…/domains/kramio.pl/public` (jak wcześniej dla shop — ustawienie w panelu hostingu). Hosting tworzy domyślnie `public_html` jako docroot; my używamy `public` Laravela.
- Repozytorium Git: jeszcze nie zainicjowane. Setup wg `FOUNDATION.md` sek. 3 (klucz SSH, remote przez SSH, tożsamość commitów per-repo na `Rafał Kwaśniak <rafal@kwasniak.org>`, bez `--global`).

---

## 3. Decyzje produktowe i techniczne

**Ustalone:**

1. **Stos / sposób renderowania frontu** — USTALONE.
   - **Panele Admina i Sprzedawcy: Livewire dla obu.** Jeden spójny, lekki stos; pełna kontrola nad UX onboardingu „15 minut" (obietnica urealniona 31.07 — Rafał zmierzył: 5 było nierealne). Filament świadomie odrzucony na start — zostaje jako opcja na później, gdyby panel admina się rozrósł (Filament i Livewire mogą żyć obok siebie).
   - **Storefront: Blade-first** (priorytet SEO + szybkość, czysty HTML od razu) **+ Livewire punktowo** tam, gdzie zarabia (koszyk itp.) **+ warstwa motywów** na wierzchu. Motywy to osobny, późniejszy temat — patrz pamięć „storefront-theme-system" (i zwykły Blade, i komponenty Livewire muszą respektować aktywny motyw).
   - Wspólny fundament: Laravel + Blade + Livewire dla całości; storefront używa go tylko inaczej (Blade-first), panele — w pełni.

2. **Czy serwis wystawia publiczne JSON API** — USTALONE: **brak publicznego JSON API.** Interaktywność robimy przez Livewire/kontrolery, nie formalny kontrakt API. Konsekwencje dla `FOUNDATION.md`: koperta JSON `{ success, message, data? }`, OpenAPI/Scramble i `api-guide.html` **odpadają**. `code-map.html` (mapa kodu) utrzymujemy niezależnie.

3. **Locale** — USTALONE (2026-06-25): **pl-first, sklep jednojęzyczny.** `APP_LOCALE=pl`, `APP_FAKER_LOCALE=pl_PL`. `APP_FALLBACK_LOCALE=en` zostaje wyłącznie jako techniczna siatka bezpieczeństwa (gdyby brakło klucza), nie jako drugi język produktu. Polskie tłumaczenia frameworka w `lang/pl/` (validation, auth, passwords, pagination).

4. **Konwencja nazewnictwa** — USTALONE (2026-06-25): **„Adres i interfejs — po polsku. Kod — po angielsku."**
   - **Po polsku (warstwa widoczna):** segmenty URL i slugi (`/produkt/132-kwiatki-komunijne`, `/koszyk`, `/sprzedawca/...`, `/administrator/...`), teksty UI. URL produktu w stylu PrestaShop: `id-slug`, szukamy po `id`, zły slug → 301 na kanoniczny.
   - **Po angielsku (warstwa kodu):** modele (`Product`), tabele (`products`), kolumny, wartości enumów (rola `seller`), **nazwy tras** (`products.show`, `seller.dashboard`), kontrolery, zmienne. URL jest odpięty od nazwy trasy — polski adres + angielska nazwa trasy to standard (`Route::get('/produkt/{product}', …)->name('products.show')`).
   - W testach i linkach odwołujemy się do tras przez `route('nazwa')`, nie sztywne ścieżki.

5. **Architektura wielonajemcza (subdomena-per-sklep)** — USTALONE (2026-06-25):
   - **Centrala** = domena platformy (`config('tenancy.central_domain')` ← `APP_DOMAIN`): zarządzanie, logowanie/rejestracja, panel sprzedawcy i administratora. **Sprzedawca zarządza sklepem w centrali, NIE loguje się do swojej subdomeny, by nim zarządzać** (na subdomenie może co najwyżej zostać własnym klientem).
   - **Storefront** = subdomena `{shop}.{central_domain}` (np. `bukiety.kramio.pl`): publiczny sklep jednego sprzedawcy. `{shop}` = slug sklepu = etykieta subdomeny; middleware rozwiązuje `Shop` i scope'uje wszystko do niego.
   - **Jedna baza + `shop_id`** na tabelach najemcy (nie baza-per-sklep — przerost na shared-hoście).
   - **Stan (zweryfikowany 2026-07-25): DZIAŁA na produkcji.** Wildcard DNS i wildcard SSL `*.kramio.pl` są na serwerze; w aplikacji storefront żyje w grupie `Route::domain('{shop}.'.config('tenancy.central_domain'))` z middleware `tenant` (`App\Http\Middleware\ResolveShop`) w `routes/web.php`. Zweryfikowane na `ilikemybike.kramio.pl`. Wcześniejsze zdanie „wyłączony szkielet, włączymy później" było NIEPRAWDĄ przepisywaną z czasów sprzed wdrożenia — raz wprowadziło asystenta w błąd przy podsumowaniu zaległości.

---

## 4. Stosowanie FOUNDATION w tym projekcie

Zweryfikowane pod kątem „cały serwis, nie tylko API":

**Obowiązuje już teraz (uniwersalne):**
- Komunikacja (sek. 1), tryb pracy „najpierw omawiamy, potem robimy", małe kroki (sek. 2).
- Git/GitHub (sek. 3).
- Walidacja wyłącznie w Form Requestach, cienkie kontrolery (sek. 5).
- Stałe biznesowe w `config/`, parytet `.env.example` z `.env`, `APP_DEBUG=false` na produkcji (sek. 5).
- Logi dzienne + kanał alertów Discord (webhook) (sek. 5).
- Edycja i upload przez POST (sek. 5).
- Stringi przez warstwę tłumaczeń, oba locale w synchronizacji, realne testy (sek. 5).
- Ciągłość między sesjami: handoff na koniec, log prac poza specyfikacją (sek. 6).

**Rozstrzygnięte przez decyzję „brak JSON API" (sek. 3 pkt 2):**
- Dokumentacja API: OpenAPI/Scramble + `api-guide.html` oraz dynamiczna trasa `docs/` (`FOUNDATION` sek. 4) — **odpada** (nie wystawiamy JSON API). `code-map.html` (mapa kodu) utrzymujemy niezależnie, bo opisuje, gdzie co mieszka w kodzie.
- Kształt odpowiedzi API `{ success, message, data?, pagination? }` (sek. 5) — **nie dotyczy** kontrolerów serwerowych (zwracają widoki/Livewire). Gdyby pojawił się punktowy endpoint AJAX dla Livewire, trzymamy spójny, prosty kształt — ale to nie jest publiczny kontrakt.
- Serializacja encji „dla frontu, żeby nie dociągał `GET /{id}`" (sek. 5) — to było myślenie API. Sama zasada „jedno źródło serializacji encji + pełny CRUD tam, gdzie ma sens" zostaje jako dobra praktyka także w monolicie.

---

## 5. Środowiskowe TODO (konfiguracja przed produkcją)

Zrobione:
- `APP_NAME=Kramio`, `APP_URL=https://kramio.pl`, `APP_DOMAIN=kramio.pl`, `APP_ENV=production`, `APP_DEBUG=false`. (Storefronty pod `*.kramio.pl` — wildcard DNS i SSL ustawione, subdomeny działają.)
- `MAIL_FROM_ADDRESS=sklep@kramio.pl`.
- Parytet `.env` ↔ `.env.example` wyrównany (48 kluczy, blok DB pod MySQL z pustymi wartościami w example).
- Logowanie (`FOUNDATION` sek. 5): logi dzienne (kanał `daily`, retencja 14 dni w `config/logging.php`).
- Monitoring wolnych zapytań SQL: serwis `App\Services\SlowQueryLogger` wpięty w `AppServiceProvider::boot()` przez `DB::listen` (tylko gdy próg > 0). Zapytania wolniejsze niż `config('monitoring.slow_query_ms')` (domyślnie 200 ms) trafiają do osobnego dziennego kanału `slow_queries` (`storage/logs/slow-query-*.log`) z czasem, SQL, bindings i miejscem w kodzie (plik:linia, pierwszy frame aplikacji za warstwą vendor). Pokryte testem `tests/Feature/SlowQueryLoggingTest.php`.
- Baza: migracje startowe wykonane na MySQL (wtedy `host473413_shop`, dziś `host473413_kramio` — patrz sek. 2) (`migrate --force`). Uwaga historyczna: instalator odpalił migracje na SQLite; po przełączeniu na MySQL trzeba było je wykonać ponownie, inaczej `SESSION_DRIVER=database` wywracał każdy request (brak tabeli `sessions`).
- Alerty Discord: serwis `App\Services\DiscordErrorReporter` (natywny embed przez `Http::post`, nie hack `/slack`), wpięty w `bootstrap/app.php` przez `$exceptions->report()` — każdy reportowalny wyjątek leci na kanał obok logu. No-op gdy brak webhooka; błąd dostarczenia jest połykany (nie może zapętlić handlera). Webhook w `config/services.php` (`services.discord.webhook` ← `DISCORD_WEBHOOK_URL`), sekret tylko w `.env`. Wzorzec przeniesiony z projektu `kociaczek.com.pl`. Pokryte testem `tests/Feature/DiscordErrorReportingTest.php`.

Wciąż do zrobienia (notatka, nie robimy z automatu):
- `APP_LOCALE` / `APP_FALLBACK_LOCALE` — po decyzji o locale (sek. 3).
- Każda zmiana w `.env` = aktualizacja `.env.example` w tym samym kroku.

---

## 6. Przeprowadzka na nowy serwer

Ta sekcja jest po to, żeby pierwsza sesja na nowym serwerze — nawet z pustą pamięcią asystenta — od razu wiedziała, czego brakuje i skąd to wziąć.

### 6.1. Pamięć asystenta (robić najpierw)

Pamięć Claude'a (handoffy, gotchy, decyzje produktowe — ok. 160 plików) mieszka w **katalogu domowym konta**, poza projektem: `~/.claude/projects/<klucz>/memory/`. Git jej nie widzi, więc sama by nie pojechała. Dlatego repozytorium wozi jej kopię w `.claude/memory/`, a `.claude/memory-sync.sh` przenosi ją w obie strony:

```bash
.claude/memory-sync.sh status     # co gdzie leży i czy się różni
.claude/memory-sync.sh save       # $HOME -> repozytorium (przed commitem)
.claude/memory-sync.sh restore    # repozytorium -> $HOME (po przeprowadzce)
```

**Nazwa katalogu w `$HOME` to ścieżka bezwzględna projektu z ukośnikami i kropkami zamienionymi na myślniki** (`/home/host473413/domains/kramio.pl` → `-home-host473413-domains-kramio-pl`). Po przeprowadzce na konto o innej nazwie skopiowanie katalogu 1:1 **nic nie da** — klucz się zmienia i asystent szuka gdzie indziej. Skrypt liczy klucz z bieżącej ścieżki, więc trafia poprawnie także na nowym serwerze.

**Zasada dla asystenta:** kopia starzeje się cicho. Przy każdym „CP" po sesji, w której powstał handoff lub nowa notatka — najpierw `save`, potem commit. Kopia sprzed trzech miesięcy jest niewiele lepsza od jej braku.

Czego NIE trzeba przenosić: transkryptów sesji (`*.jsonl`, ~120 MB obok pamięci — potrzebne tylko do wznawiania starych rozmów) ani `~/.claude/settings.json` (dwie preferencje, szybciej ustawić od nowa).

### 6.2. Czego nie ma w repozytorium

- **`.env`** — odtworzyć z `.env.example` i uzupełnić sekrety (baza, `DISCORD_WEBHOOK_URL`, Paynow, Fakturownia, InPost, DeepSeek, GUS). Każda zmiana `.env` = aktualizacja `.env.example` w tym samym kroku.
- **`storage/app/`** (~19 MB) — zdjęcia produktów, logotypy, pliki sklepów. Bez tego storefronty stracą grafiki. Przenieść razem z uprawnieniami.
- **Baza danych** — zrzut SQL, nie kopia katalogu MySQL. Po odtworzeniu sprawdzić liczbę tabel, klucze obce i `AUTO_INCREMENT` (tak robiliśmy przy zmianie nazwy bazy 12.08, sek. 2).
- **`vendor/`, `node_modules/`, `public/build/`** — odtwarzane komendami, nie przenoszone.

### 6.3. Konfiguracja do sprawdzenia po przeprowadzce

1. **Ścieżka do PHP 8.5** — dziś `/opt/alt/php85/usr/bin/php`, na nowym serwerze prawie na pewno inna. Zaktualizować sek. 2 tego pliku. Domyślne `php` w shellu bywa starsze niż runtime WWW — sprawdzić `php -v` i porównać z wersją na stronie.
2. **Document root** domeny musi wskazywać na `…/kramio.pl/public` (nie `public_html`).
3. **Wildcard DNS i wildcard SSL `*.kramio.pl`** — bez tego padają wszystkie storefronty, a nie tylko centrala.
4. **Jeden wpis crona:** `* * * * * php artisan schedule:run` (pełną ścieżką do PHP 8.5). Wszystko resztę — kopie zapasowe 04:00 i 16:00, strażnik kopii 09:00, wysyłka maili, odświeżanie przesyłek, abonamenty, kasowanie sklepów po karencji — planuje `routes/console.php`. Bez tego jednego wpisu nie działa nic z tej listy, po cichu.
5. **Klucz SSH i remote gita** — `FOUNDATION.md` sek. 3, tożsamość commitów per-repo (`--local`, nie `--global`).
6. **Adres IP serwera dla Przelewy24.** P24 wpuszcza API tylko z IP wpisanych w panelu sprzedawcy. Nowy serwer = nowe IP: ustawić `SERVER_OUTBOUND_IP` w `.env` (sprawdzić `curl -4 https://api.ipify.org`) i **przed przełączeniem** poprosić każdego sprzedawcę z włączonymi Przelewy24, żeby dopisał nowy adres w swoim panelu (stary zostawić do końca przenosin). Bez tego płatności P24 staną we wszystkich takich sklepach naraz.
7. **Katalog kopii zapasowych** i jego wolne miejsce (patrz ekran Ustawień w panelu admina).
8. `composer install`, `npm ci && npm run build`, `php artisan migrate --force`, `storage:link`, uprawnienia do `storage/` i `bootstrap/cache/`.

### 6.4. Sprawdzian, że wszystko wróciło

`php artisan test` — pełna suita. Zielona suita jest tu najtańszym dowodem, że baza, pliki i konfiguracja dojechały w komplecie. Potem wejść na centralę, na dowolny storefront pod subdomeną i na `/administrator/panel`.

### 6.5. Stary serwer zostaje żywy — wyłączyć na nim crona

**Decyzja Rafała (2026-08-30):** stara maszyna nie znika, zostaje pod inne projekty. Komplet plików i danych Kramio zostaje na niej nietknięty; przenosimy tylko domenę. To świetna polisa na odtworzenie — i jednocześnie powtórka sytuacji, przez którą 13.08 kasowaliśmy katalog `shop.kwasniak.org` (patrz nagłówek tego pliku): druga żywa kopia tej samej aplikacji.

**Sedno: przekierowanie domeny dotyczy wyłącznie ruchu HTTP. Cron nie pyta o DNS.** Stary serwer dalej co minutę odpali `schedule:run` na swojej kopii bazy, a te komendy sięgają na zewnątrz:

- `email:dispatch` (co minutę) — **realne maile** do realnych sprzedawców i klientów z outboxu starej bazy.
- `queue:work` (co minutę) — faktury w Fakturowni; ta usługa **nie ma sandboxa**, każde żądanie tworzy realny dokument.
- `shipments:refresh` (co minutę) — realne wywołania InPost ShipX.
- `subscriptions:check` (06:10) — drugi komplet przypomnień o wygasającym pakiecie.
- `backup:check` (09:00) — alarmy o kopiach martwej już instalacji.
- Każdy błąd starej kopii leci na **ten sam webhook Discorda** → alert nie do odróżnienia od awarii produkcji.

**Do zrobienia przez admina zaraz po przełączeniu domeny: usunąć wpis crona `* * * * * php artisan schedule:run` dla starego katalogu Kramio.** To zdejmuje całą powyższą listę naraz. Pas i szelki: wyczyścić `DISCORD_WEBHOOK_URL` i ustawić `BACKUP_ENABLED=false` w starym `.env`.

**Okno propagacji DNS.** Przez kilka godzin część ruchu pójdzie jeszcze na stary serwer. Zamówienie złożone w tym czasie wyląduje w STAREJ bazie i zniknie z pola widzenia. Sprawdzenie jest darmowe: po przenosinach porównać liczbę zamówień w starej bazie z migawką sprzed przeprowadzki (stan 2026-08-30: 9 zamówień, 9 sklepów, 47 produktów, 40 tabel, 139 plików w `storage/app`, 90 migracji, 1713 zielonych testów). Jeśli urosła — ktoś kupił na starym serwerze i trzeba to przenieść ręcznie.
