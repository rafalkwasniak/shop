---
name: idea-loyalty-points
description: "Punkty za zakupy — PLAN GOTOWY 06.10, ZERO kodu. Start na kolejnej sesji od P1 (5 checkpointów). Wszystkie pytania rozstrzygnięte, import poza zakresem."
metadata:
  node_type: memory
  type: project
  originSessionId: 7092e827-f5de-4490-9ede-6b446045c5b5
  modified: 2026-10-07T06:04:34.156Z
---

**Stan 2026-10-07: SILNIK WDROŻONY, NIEPODPIĘTY.** Rafał chciał krok, który „sam w sobie nic nie zepsuje”. Zrobione: `config/loyalty.php`, kolumny `shops.loyalty_*` (wszystko domyślnie wyłączone), tabele `loyalty_entries` + `loyalty_entry_usages`, `App\Services\LoyaltyLedger` (award / reconcile / spend / restore / adjust / balance / pending / expire), `LoyaltyEntryType`, `LoyaltyException`, testy `tests/Feature/Loyalty/LoyaltyLedgerTest.php` (15). Migracja ODPALONA na produkcji (0/12 sklepów z punktami). Suita 1852→1867.
**NIC tego jeszcze nie wywołuje:** brak klucza uprawnienia, brak widoków, brak podpięcia pod `changeStatus`/`OrderReturnService`/cron. Następny krok = P1 widoki (uprawnienie + sekcja w Ustawieniach), potem podpięcia z P2.
Decyzje z implementacji (do potwierdzenia przez Rafała przy P1): ważność liczona od DOSTĘPNOŚCI, do końca dnia; ułamek punktu przepada (floor); zwrot odbiera proporcjonalnie do spadku `base_amount` (nie od bieżącego %); wygasające punkty najpierw spłacają dług; dodatnia korekta dostępna od razu.

**Kontekst:** funkcja jest dla Kramio ogólnie. Impulsem był klient ze starym sklepem z punktami, ale gdyby kupił, dostanie osobną kopię jak Magellan Bay ([[plan-magellan-bay-separate-project]]) i tam robimy jego ustępstwa. **Nie projektować pod tego klienta.**

## Ustalenia (Rafał, 06.10)
1. **Ekonomia:** sprzedawca ustawia „% wraca w punktach” zamiast dwóch kursów. Do tego limit „punktami najwyżej X% zamówienia” i minimum punktów do wydania.
2. **Wartość punktu:** sprzedawca konfiguruje ją sam, np. z listy 1 gr / 10 gr / 1 zł („to już konfiguracja”).
3. **W koszyku:** klient sam wybiera „Użyj punktów”, rabat nie wchodzi automatycznie. Punkty łączą się z kodem rabatowym, pod tym samym limitem.
4. **Karencja:** OSOBNE pole w dniach. Domyślnie równa oknu zwrotu (20 dni = `legal.withdrawal.days` + `delivery_buffer_days`), sprzedawca może ją zmienić, bo bywa dłuższa.
5. **Podstawa naliczania:** wartość produktów faktycznie zapłacona (po rabacie, po odjęciu części opłaconej punktami), BEZ dostawy.
6. **Kiedy przyznajemy:** porcja powstaje przy statusie Completed, dostępna po karencji. Anulowanie zabiera punkty. Zwrot odejmuje proporcjonalnie: najpierw z porcji oczekujących, potem saldo może zejść pod zero (Rafał: „nic się nie stanie”).
7. **Bez konta:** gość ZBIERA punkty, wydaje je dopiero po rejestracji. Maile zachęcają do założenia konta. Po rejestracji historia i saldo są widoczne od razu.
   - FAKT Z KODU: zakup bez „Załóż konto” NIE tworzy rekordu `customers` (`OrderService::resolveCustomer()` → null). Dlatego **księga kluczowana po (shop_id, e-mail)**, tak jak `CustomerDirectory`.
8. **Księga:** porcje z kolumnami „przyznane” i „pozostałe”, wydawanie zdejmuje od najstarszych (pomysł Rafała). Osobny zapis, która porcja poszła na które zamówienie, żeby anulowanie mogło je oddać.
9. **Zejście z pakietu:** naliczanie staje, ale zebrane punkty można wydać do końca ich ważności. Świadomy wyjątek od [[gotcha-package-gated-feature-must-expire]].
10. **Regulamin:** warunkowy paragraf we wzorze regulaminu sprzedawcy (`resources/views/seller/legal/templates/regulamin.blade.php`), liczony w górnym bloku `@php`, tak jak paragraf o pobraniu.
11. **Import sald ze starych sklepów:** POZA ZAKRESEM.

## Checkpointy
- **P1 fundament:** uprawnienie `loyalty_points` (pavilion + dedicated = true); sekcja „Punkty” w `seller/settings/edit.blade.php` (ustawienia jako kolumny na `shops`, takie są dzisiejsze ustawienia sklepu); migracje tabel porcji i wykorzystań.
- **P2 naliczanie:** przy Completed (`Order::changeStatus` / `OrderStatusChanger`), anulowanie, zwrot (`OrderReturnService::register`, w tej samej transakcji), informacja „dostaniesz X pkt” na `storefront/product.blade.php`.
- **P3 Moje konto:** zakładka „Punkty” w `components/storefront/account-shell.blade.php` (saldo, oczekujące z datą wpadnięcia, historia, wygasanie) + zachęta w mailach do gości.
- **P4 wydawanie:** koszyk i kasa, rabat rozłożony przez `DiscountAllocation` w `OrderTotals`, migawka na `orders` (punkty i kwota), faktura, maile, panel sprzedawcy; anulowanie oddaje punkty.
- **P5 domknięcie:** wygaszanie w `routes/console.php` + mail przed wygaśnięciem (outbox), paragraf w regulaminie, saldo klienta i ręczna korekta w panelu sprzedawcy, wpis w `PackageFeatures` i na landingu.

**Ryzyko przyjęte:** sprzedawca, który nie ustawia statusu Completed, będzie miał punkty wiszące jako oczekujące. To też zachęta do prowadzenia statusów.

**Gotcha:** nowy klucz uprawnienia trzeba wpisać w `ShopManager::booleanEntitlements()`, `PackageFeatures` (`$keys` i etykiety) oraz `SyncPackageEntitlements` ([[gotcha-shopmanager-rebuilds-whole-snapshot]]). Pokrewne: [[plan-discount-codes]], [[legal-consumer-returns-withdrawal]], [[plan-customer-accounts]], [[email-outbox-cron-pattern]].
