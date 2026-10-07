---
name: idea-loyalty-points
description: "Punkty za zakupy — PLAN GOTOWY 06.10, ZERO kodu. Start na kolejnej sesji od P1 (5 checkpointów). Wszystkie pytania rozstrzygnięte, import poza zakresem."
metadata:
  node_type: memory
  type: project
  originSessionId: 7092e827-f5de-4490-9ede-6b446045c5b5
  modified: 2026-10-07T10:57:33.336Z
---

**Stan 2026-10-07: SILNIK WDROŻONY, NIEPODPIĘTY.** Rafał chciał krok, który „sam w sobie nic nie zepsuje”. Zrobione: `config/loyalty.php`, kolumny `shops.loyalty_*` (wszystko domyślnie wyłączone), tabele `loyalty_entries` + `loyalty_entry_usages`, `App\Services\LoyaltyLedger` (award / reconcile / spend / restore / adjust / balance / pending / expire), `LoyaltyEntryType`, `LoyaltyException`, testy `tests/Feature/Loyalty/LoyaltyLedgerTest.php` (15). Migracja ODPALONA na produkcji (0/12 sklepów z punktami). Suita 1852→1867.
**07.10 (krok 2) PODPIĘCIA WDROŻONE:** `App\Observers\OrderObserver` (`#[ObservedBy]` na Order). Zrealizowane → `award`, Anulowane → `restore` + `reconcile`, spadek `items_total`/`discount_amount` (zwrot, edycja) → `reconcile`. Wszystko po commicie (`DB::afterCommit`), błąd tylko przez `report()`, NIGDY nie blokuje zamówienia. Komenda `loyalty:expire` codziennie o 06:30. Suita 1867→1874. W testach silnika zamówienie zapisywać `updateQuietly()`, inaczej obserwator wyprzedza jawne wywołanie.
**Lemoniady (demo) mają punkty WŁĄCZONE** na prośbę Rafała: 5%, 1 pkt = 1 gr. Stare zamówienia NIE dostają punktów wstecz; porcja powstaje dopiero przy nowym przejściu w Zrealizowane (to wysyła klientowi maila o statusie, więc statusów nie ruszałem bez pytania).
**07.10 pytanie Rafała o regulamin — FAKTY Z KODU:** wzór regulaminu NIE ma paragrafu o punktach. Regulamin sklepu to zamrożony HTML w `pages.content` (zapis z kreatora, `PageController::update`), NIE odświeża się po zmianie ustawień, nie ma wersji. Akceptacja w kasie (`Checkout::$accept_terms`) jest wymagana, ale NIGDZIE niezapisywana. Brak maili do klientów sklepu o zmianie regulaminu.
**DECYZJE Rafała 07.10:**
- **Regulaminu sklepu NIE ruszamy.** Aktualność to obowiązek właściciela sklepu; dajemy wzór bez gwarancji. Paragraf o punktach we wzorze ODPADA.
- **Po wyłączeniu punktów** naliczanie staje, zebrane punkty można wydać do końca ważności („dokładnie tak”).
- **Strona „Zasady punktów”** działa jak systemowa strona Regulaminu: nie da się jej usunąć przy włączonych punktach, tylko edytować. Domyślną treść generujemy z ustawień sklepu; Rafał prosi, żebym napisał „fajną treść domyślną”.
- **Zmiana wartości punktu przy istniejących saldach:** dozwolona po zaznaczeniu potwierdzenia, że salda klientów zostaną przeliczone (scenariusz: sprzedawca najpierw powiadamia klientów, potem zmienia). USTALONE: przeliczenie ZACHOWUJE WARTOŚĆ W ZŁ, zmienia się liczba punktów (300 pkt × 1 gr → 30 pkt × 10 gr). W historii klienta wpis „Przeliczenie”. Zaokrąglamy na korzyść klienta (porcje w górę, dług w stronę zera).
  **WDROŻONE 07.10 (krok 3):** `LoyaltyLedger::revalue(Shop, float)` + typ `Revaluation` + kolumna `loyalty_entries.point_value` (wartość punktu z chwili wpisu). `reconcile` liczy w GROSZACH, bo historia trzyma liczby w jednostkach ze swojego dnia; przeliczenie skaluje też `loyalty_entry_usages`, żeby `restore` oddał właściwą liczbę. Suita 1874→1880, migracja na produkcji. Checkbox „rozumiem, że salda zostaną przeliczone” to już UI (krok Ustawień).
- **Maili do klientów o zmianie zasad NIE robimy**, to obowiązek sprzedawcy.
- **Akceptacja dokumentów:** Rafał myślał, że klient akceptuje regulamin przy logowaniu. NIEPRAWDA: okno `/zgody` (`EnsureConsentsAreCurrent`, `legal_documents` + `user_consents`) dotyczy WYŁĄCZNIE sprzedawców i dokumentów platformy. Klienci sklepu przy rejestracji nie akceptują niczego; w kasie zaznaczają regulamin i politykę przy KAŻDYM zamówieniu, ale bez zapisu wersji. Pomysł Rafała „checkboxy w kasie tylko po zmianie dokumentów” wymaga wersjonowania stron sklepu, a tego dziś nie ma. Osobny temat, poza punktami.
**07.10 Rafał: „wdrażamy całość”, ALE BEZ włącznika w panelu sprzedawcy; punkty tylko w Lemoniadach.** Lemoniady dostały punkty WSTECZ (jednorazowo, tylko do podglądu): #1 bez punktów (celowo), #2 anulowane, #3–#8 z datą realizacji → saldo 1094 pkt. Uprawnienie `loyalty_points` nadane Lemoniadom w snapshocie.
- **Krok A ZROBIONY:** `Shop::loyaltyActive()` (włącznik + uprawnienie; bramka w `award`), `ShopFactory::withLoyalty()`, sekcja „Punkty za zakupy” w Ustawieniach (osobny formularz `seller.settings.loyalty`, `LoyaltySettingsRequest`, checkbox `confirm_revaluation` tylko przy saldach, zmiana wartości przez `revalue()`), strona zasad: `pages.system_key = loyalty_rules` (NIE `is_system`, bo ta flaga = Regulamin w kreatorze i kasie), `Page::isLocked()/isDeletable()`, `App\Support\LoyaltyRules` + szablon `seller/legal/templates/zasady-punktow.blade.php`, przycisk „Wstaw treść domyślną” (`pages.loyalty.insert`). Strona Lemoniad: `/informacje/27-zasady-punktow`.

- **Poprawki po odbiorze A (Rafał 07.10):** dwa formularze w Ustawieniach ZOSTAJĄ (świadomie), dodane ostrzeżenie o niezapisanych zmianach w drugiej sekcji: komunikat w stronie + drugie kliknięcie, NIE `confirm()` (test `PanelConfirmationsTest` zabrania okienek przeglądarki w panelu). **Wartość punktu = dowolna kwota 0,01–10,00 zł** (`loyalty.point_value_min/max`, NIE lista); `revalue()` sprawdza zakres. Przelicznik na żywo (Alpine `loyaltyPreview` w widoku Ustawień): „za zakupy za [100] zł klient dostanie X pkt = Y zł”, liczy jak `pointsFromBase` — zmieniając silnik, zmieniaj i JS.

**PLAN DALSZY (zaproponowany 07.10, czeka na start):** uprawnienie `loyalty_points` dodane od razu, ale FALSE we WSZYSTKICH pakietach (także Pawilonie); Rafał włącza je ręcznie tylko Lemoniadom. Funkcja rośnie po cichu, sprzedawcy zobaczą ją dopiero kompletną.
- **A** (1 sesja): uprawnienie w ShopManager (+ PackageFeatures, SyncPackageEntitlements) + sekcja „Punkty” w Ustawieniach (z checkboxem przeliczenia przy zmianie wartości) + strona „Zasady punktów” (domyślna treść z ustawień, edytowalna, nieusuwalna przy włączonych). Włączenie wymaga zasad.
- **B** (1): karta produktu „dostaniesz X pkt”, zakładka Punkty w Moim koncie, mail o realizacji z punktami i zachętą do konta dla gościa. Potem Rafał składa prawdziwe zamówienie w Lemoniadach.
- **C** (1–2): wydawanie w koszyku i kasie, VAT przez `DiscountAllocation`, faktura, maile, panel zamówienia.
- **D** (1): saldo i korekta w kartotece klientów; Pawilon = true + `packages:sync-entitlements --apply`; opis w pakietach i na landingu DOPIERO TERAZ.
Dalej brak: widoków. Następny krok = P1 widoki (uprawnienie + sekcja w Ustawieniach) albo paragraf regulaminu.
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
