---
name: gotcha-artisan-call-from-web-exec-sandbox
description: "Artisan::call() z żądania WWW wywala się na produkcji — sandbox hostingu blokuje exec(), a harmonogram z routes/console.php go woła. Testy tego NIE łapią."
metadata:
  node_type: memory
  type: project
  originSessionId: 3544b215-9981-4f6b-8a71-4f28ea58177f
  modified: 2026-10-03T07:22:17.897Z
---

INCYDENT 2026-10-03 (2FA logowania): `defer(fn () => Artisan::call('email:dispatch'))` w żądaniu WWW → alert Discorda `exec() has been disabled for security reasons by site sandbox`. Artisan ładuje routes/console.php, `Schedule::command()` buduje komendę z `Application::phpBinary()` → `PhpExecutableFinder` → `exec()`. Sandbox hostingu blokuje `exec()` tylko na stronie WWW, w CLI działa — więc testy i tinker przechodzą.

**Why:** cała suita jest zielona, a produkcja pada; różnica jest wyłącznie w środowisku PHP strony.

**How to apply:** z kodu WWW NIGDY `Artisan::call()`. Logikę komendy wynieść do usługi i wołać ją bezpośrednio (wzór: `App\Services\EmailOutbox`, wołana przez `email:dispatch` i `LoginCodeMailer`). Test regresji: `Artisan::spy()` + `shouldNotHaveReceived('call')`. Powiązane: [[shared-hosting-constraints]], [[email-outbox-cron-pattern]].
