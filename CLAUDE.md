# CLAUDE.md

Specyfika projektu **shop.kwasniak.org**. Plik czytany przez asystenta na starcie każdej sesji.

**Relacja do `FOUNDATION.md`:** `FOUNDATION.md` = uniwersalne zasady współpracy (te same w każdym projekcie). Ten plik = specyfika tego projektu (domena, stos, decyzje produktowe, stan środowiska). W kwestiach projektowych `CLAUDE.md` ma pierwszeństwo; zasady współpracy z `FOUNDATION.md` obowiązują zawsze.

`FOUNDATION.md` opisuje pracę nad **API**. Tutaj robimy **cały serwis**, więc część zasad czysto-API jest warunkowa lub wstrzymana do decyzji (patrz: Decyzje otwarte oraz Stosowanie FOUNDATION).

---

## 1. Projekt

- Domena: `shop.kwasniak.org`.
- Charakter: serwis typu sklep (pełny zakres dopiero poznajemy — szczegóły produktowe dochodzą).
- Świeża instalacja szkieletu Laravela jako punkt wyjścia; właściwy plan budujemy małymi krokami.

---

## 2. Stos i środowisko

- **Laravel Framework 13.17** (`laravel/framework: ^13.8`).
- **PHP 8.5.6** na stronie WWW (`/opt/alt/php85`). To jest runtime docelowy.
- **UWAGA na CLI:** domyślne `php` w shellu to `/usr/local/bin/php` = **PHP 8.3**, nie 8.5. Aby zachować parytet z webem, `artisan`, `composer` i skrypty PHP uruchamiamy jawnie przez:
  ```bash
  /opt/alt/php85/usr/bin/php artisan ...
  /opt/alt/php85/usr/bin/php $(which composer) ...
  ```
- **Composer** 2.9.7.
- **Baza:** MySQL, połączenie `mysql`, baza `host473413_shop` (dostęp w `.env`). Sesje, kolejka i cache na sterowniku `database`.
- **Node 20.20 / npm 10.8** dostępne.
- **Document root** ustawiony na `…/shop.kwasniak.org/public` (potwierdzone przez Rafała).
- Repozytorium Git: jeszcze nie zainicjowane. Setup wg `FOUNDATION.md` sek. 3 (klucz SSH, remote przez SSH, tożsamość commitów per-repo na `Rafał Kwaśniak <rafal@kwasniak.org>`, bez `--global`).

---

## 3. Decyzje otwarte (DO USTALENIA)

**Ustalone (2026-06-24):**

1. **Stos / sposób renderowania frontu** — USTALONE.
   - **Panele Admina i Sprzedawcy: Livewire dla obu.** Jeden spójny, lekki stos; pełna kontrola nad UX onboardingu „5 minut". Filament świadomie odrzucony na start — zostaje jako opcja na później, gdyby panel admina się rozrósł (Filament i Livewire mogą żyć obok siebie).
   - **Storefront: Blade-first** (priorytet SEO + szybkość, czysty HTML od razu) **+ Livewire punktowo** tam, gdzie zarabia (koszyk itp.) **+ warstwa motywów** na wierzchu. Motywy to osobny, późniejszy temat — patrz pamięć „storefront-theme-system" (i zwykły Blade, i komponenty Livewire muszą respektować aktywny motyw).
   - Wspólny fundament: Laravel + Blade + Livewire dla całości; storefront używa go tylko inaczej (Blade-first), panele — w pełni.

2. **Czy serwis wystawia publiczne JSON API** — USTALONE: **brak publicznego JSON API.** Interaktywność robimy przez Livewire/kontrolery, nie formalny kontrakt API. Konsekwencje dla `FOUNDATION.md`: koperta JSON `{ success, message, data? }`, OpenAPI/Scramble i `api-guide.html` **odpadają**. `code-map.html` (mapa kodu) utrzymujemy niezależnie.

**Nadal DO USTALENIA:**

3. **Locale** — obecnie `APP_LOCALE=en`; docelowe locale (najpewniej `pl`) do potwierdzenia przy decyzji produktowej.

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
- `APP_NAME=Shop`, `APP_URL=https://shop.kwasniak.org`, `APP_ENV=production`, `APP_DEBUG=false`.
- `MAIL_FROM_ADDRESS=noreply@shop.kwasniak.org`.
- Parytet `.env` ↔ `.env.example` wyrównany (48 kluczy, blok DB pod MySQL z pustymi wartościami w example).
- Logowanie (`FOUNDATION` sek. 5): logi dzienne (kanał `daily`, retencja 14 dni w `config/logging.php`).
- Monitoring wolnych zapytań SQL: serwis `App\Services\SlowQueryLogger` wpięty w `AppServiceProvider::boot()` przez `DB::listen` (tylko gdy próg > 0). Zapytania wolniejsze niż `config('monitoring.slow_query_ms')` (domyślnie 200 ms) trafiają do osobnego dziennego kanału `slow_queries` (`storage/logs/slow-query-*.log`) z czasem, SQL, bindings i miejscem w kodzie (plik:linia, pierwszy frame aplikacji za warstwą vendor). Pokryte testem `tests/Feature/SlowQueryLoggingTest.php`.
- Baza: migracje startowe wykonane na MySQL `host473413_shop` (`migrate --force`). Uwaga historyczna: instalator odpalił migracje na SQLite; po przełączeniu na MySQL trzeba było je wykonać ponownie, inaczej `SESSION_DRIVER=database` wywracał każdy request (brak tabeli `sessions`).
- Alerty Discord: serwis `App\Services\DiscordErrorReporter` (natywny embed przez `Http::post`, nie hack `/slack`), wpięty w `bootstrap/app.php` przez `$exceptions->report()` — każdy reportowalny wyjątek leci na kanał obok logu. No-op gdy brak webhooka; błąd dostarczenia jest połykany (nie może zapętlić handlera). Webhook w `config/services.php` (`services.discord.webhook` ← `DISCORD_WEBHOOK_URL`), sekret tylko w `.env`. Wzorzec przeniesiony z projektu `kociaczek.com.pl`. Pokryte testem `tests/Feature/DiscordErrorReportingTest.php`.

Wciąż do zrobienia (notatka, nie robimy z automatu):
- `APP_LOCALE` / `APP_FALLBACK_LOCALE` — po decyzji o locale (sek. 3).
- Każda zmiana w `.env` = aktualizacja `.env.example` w tym samym kroku.
