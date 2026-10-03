---
name: plan-login-two-factor
description: "WDROŻONE 2026-10-03: 2FA kodem z maila przy logowaniu do centrali (admin, sprzedawca, pracownik); klienci sklepów BEZ 2FA."
metadata:
  node_type: memory
  type: project
  originSessionId: 3544b215-9981-4f6b-8a71-4f28ea58177f
  modified: 2026-10-03T08:03:13.270Z
---

Logowanie do centrali = hasło + 6-cyfrowy kod z maila. Decyzje Rafała (03.10):
- **Kogo:** każdy, kto zarządza sklepem (admin, sprzedawca, pracownik). Klienci sklepów NIE — kupują nawet bez konta.
- **Same cyfry** (Rafał proponował a-z0-9, zgodził się na cyfry): tylko takie Safari pewnie wyłapuje z Maila. Potwierdzone na żywo — podpowiada.
- **Kod przy każdym logowaniu**; „Zapamiętaj mnie" skrócone do 30 dni (`auth.guards.web.remember`). Migracja wyzerowała stare `remember_token`.
- **Linki z maila** (aktywacja, zaproszenie pracownika) logują BEZ kodu.
- **Jedna ścieżka wysyłki:** outbox High + natychmiastowe `EmailOutbox::dispatch()` po odpowiedzi (`defer`). Rafał nie chciał dwóch scenariuszy wysyłki. Wspólna blokada `email-dispatch` z cronem. NIE `Artisan::call` — [[gotcha-artisan-call-from-web-exec-sandbox]].
- **Wyjścia awaryjne:** `LOGIN_TWO_FACTOR=false` w .env; `artisan auth:login-code {email}` (po podaniu hasła na stronie).
- **Treść maila:** Rafał nie lubi krótkich maili — chce dłuższe, „profesjonalne", z wyjaśnieniem. Nagłówek = `Vocative::headline()` jak w wiadomościach platformy. Podgląd: `/administrator/podglad-maila/kod-logowania` (ta sama metoda `content()`).

**Magellan (03.10):** przeniesione (`a94a1b1`), migracja wykonana, ale **UŚPIONE** — `LOGIN_TWO_FACTOR=false`. Włączenie = jedna wartość w jego .env. Testy mają `LOGIN_TWO_FACTOR=true` przypięte w phpunit.xml (oba repo).

Kod: `LoginChallenges`, `LoginCodeMailer`, `LoginCodeController`, tabela `login_challenges`, config `security.two_factor`. Powiązane: [[email-outbox-cron-pattern]], [[feedback-marketing-tone-kramio]].
