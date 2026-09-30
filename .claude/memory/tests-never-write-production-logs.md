---
name: tests-never-write-production-logs
description: "Testy pisały do storage/logs produkcji — ekran „Błędy w logach\" liczył szum z suity. Garda isolateLogChannels() w TestCase; nigdy nie zdejmować."
metadata: 
  node_type: memory
  type: project
  originSessionId: 3c2f6e18-f863-48a9-95c1-69f8899a326c
  modified: 2026-09-30T13:27:48.954Z
---

**2026-09-30:** ekran „Błędy w logach" (Ustawienia admina) pokazywał 6 błędów
z 28.09. Żaden nie był awarią — wszystkie miały poziom `testing.ERROR` i szły ze
strażnika kopii w suicie. W całym zachowanym oknie **84 z 95 wpisów ERROR
pochodziło z testów**, a w dziennikach audytowych było jeszcze gorzej: ~23 000
linii testowych wobec ~110 produkcyjnych.

**Why:** `phpunit.xml` izolował bazę, cache, sesję, kolejkę i pocztę — ale nie
log. Testy chodzą w katalogu produkcyjnym (shared hosting, jedna kopia kodu),
więc domyślny kanał pisał do `storage/logs/laravel-<dziś>.log`, czyli dokładnie
tam, gdzie liczy `PlatformHealth::recentErrors()`. Ekran diagnostyczny, który
zgłasza awarie w dniach bez awarii, przestaje być czytany — i prawdziwa utonie.
Ta sama klasa błędu co [[tests-never-touch-production-files]] i
[[tests-never-hit-real-apis]].

**GOTCHA — `LOG_CHANNEL=null` NIE WYSTARCZA.** Ucisza wyłącznie kanał DOMYŚLNY.
Kanały wołane po nazwie (`Log::channel('paynow')`, `'shipx'`, `'fakturownia'`)
omijają tę wartość i piszą dalej: ~300 linii na przebieg suity, do plików
z **365-dniową retencją, bo to ślad audytowy pieniędzy**. Wymyślone kwoty
z fabryk obok realnych płatności sprzedawców psują dowód, po który sięga się
wtedy, gdy trzeba odtworzyć, co się stało z czyimiś pieniędzmi.

**How to apply:** `Tests\TestCase::isolateLogChannels()` (Warstwa 4 gard)
podmienia na `NullHandler` **każdy** kanał z kluczem `path` — nie wyliczoną
listę, bo nowy kanał dzienny ktoś kiedyś doda bez czytania komentarza. Pilnuje
tego `tests/Feature/LogIsolationTest.php` (8 testów; zweryfikowane przez
wyłączenie gardy — pada 7 z 8 i każdy nazywa kanał). **Nigdy nie zdejmować.**
`Log::spy()` / `Log::shouldReceive()` działają jak dawniej — podmieniają fasadę,
konfiguracji nie dotykają.

**Zostało otwarte:** archiwum `paynow-*`, `shipx-*`, `fakturownia-*` sprzed
30.09.2026 wciąż miesza szum z testów z realnymi wpisami. Pliki `paynow-2026-07-30`
i `fakturownia-2026-07-30` zawierają ślad po incydencie z ~46 realnymi fakturami
— to materiał dowodowy, kasować tylko po filtrowaniu linia po linii.

**Osobno:** scheduler domyślnie wyrzuca wyjście komendy do `/dev/null`.
`email:dispatch` padł pięć razy (25.08–11.09.2026) z kodem 254 i został sam
numer. Od 30.09 leci do `storage/logs/email-dispatch.log` ze stderr.
