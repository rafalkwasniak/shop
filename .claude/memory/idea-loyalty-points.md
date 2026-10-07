---
name: idea-loyalty-points
description: "Punkty za zakupy — PLAN GOTOWY 06.10, ZERO kodu. Start na kolejnej sesji od P1 (5 checkpointów). Wszystkie pytania rozstrzygnięte, import poza zakresem."
metadata:
  node_type: memory
  type: project
  originSessionId: 7092e827-f5de-4490-9ede-6b446045c5b5
  modified: 2026-10-07T12:11:20.405Z
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

- **Krok B ZROBIONY (07.10):** karta produktu „Za ten zakup dostaniesz X pkt (Y zł) · zasady” (tylko `loyaltyActive`, przy wadze „za 1 kg”); zakładka „Punkty” w Moim koncie (`storefront.account.points`, widok `storefront/account/points.blade.php`, w menu `account-shell` gdy `LoyaltyLedger::visibleFor()` = sklep nalicza ALBO klient ma historię) + na stronie Mojego konta TE SAME trzy kafelki (wspólny komponent `x-storefront.loyalty-summary`, bez odnośnika do historii — Rafał 07.10); karta „Punkty” na stronie zamówienia klienta (`LoyaltyLedger::forOrder`, tylko gdy są wpisy); blok w mailu o „Zrealizowane” (`OrderMailer::loyaltyBlock`: aktywne konto → link do punktów, gość → łączne saldo + link `/rejestracja`). **Blok maila w try/catch:** awaria punktów nie może zablokować maila o statusie (wyłapał to test z mockiem). GOTCHA testów: `json_encode` maila bez `JSON_UNESCAPED_SLASHES` zamienia `/` na `\/`.

- **ZASADA PREZENTACJI (Rafał 07.10, OBOWIĄZUJE też w krokach C/D):** klientowi pokazujemy SAME PUNKTY. Bez wartości w zł, bez procentu zwrotu, bez wyliczeń „ile odzyskasz” (zwrot jest mały, konkurencja go nie wykłada). Wyjątki: (1) Zasady punktów: jedna stawka w pkt na złotówkę (`LoyaltyRules::earnPhrase`: 1/10/100 zł → pełna liczba) + jeden przelicznik „100 pkt = X zł” (`redeemPhrase`), zostawiony świadomie, bo zasady programu muszą mówić, ile punkty są warte (zatajenie = ryzyko nieuczciwej praktyki); (2) w KOSZYKU kwota rabatu w zł musi być widoczna (cena końcowa), ale dopiero tam. Odnośników do Zasad na karcie produktu i w Moim koncie NIE dajemy, bo strona jest w menu i stopce. Panel sprzedawcy pokazuje zł normalnie.

- **Krok C ZROBIONY (07.10):** płacenie punktami. Decyzje Rafała: **po punktach zostaje ≥ 1 zł** (`loyalty.min_payable`, bo zamówienie za 0 zł nie przejdzie przez Paynow ani pobranie); **przy zwrocie części punkty za zwróconą część WRACAJĄ**. Budowa:
  - `orders.points_discount` (kwota w zł, osobno od `discount_amount`);
  - `LoyaltyLedger::redeemable()` → `App\Support\LoyaltyRedemption` (saldo → minimum → limit % → min_payable; powody `empty`/`minimum`/`cart`);
  - w sesji tylko przełącznik (`CartService::usesPoints/usePoints/stopUsingPoints`, czyści go `clear()`);
  - karta „Twoje punkty” w koszyku (gościowi „Masz punkty? Zaloguj się” tylko przy `loyaltyActive`), wiersz „Punkty” w koszyku, kasie, `order-totals`, mailach (`amountLines`), panelu sprzedawcy i admina;
  - `OrderService::resolvePoints` + `spend()` w transakcji zamówienia (odmowa → `CartNeedsReviewException`); płacić może TYLKO zalogowany (`$authCustomer`), nie konto dopięte po e-mailu;
  - `OrderTotals`: najpierw kod, potem punkty od reszty, jeden `spread()` na VAT; faktura: ceny pozycji po rabacie + adnotacja „rabat za punkty”;
  - zwrot: pieniądze od części zapłaconej (spread kod+punkty), `points_discount` maleje o udział zwracanych sztuk → `OrderObserver` (spadek `points_discount`) → `syncSpent()` → `restore($order, $n)` częściowo, od ostatnio zużytych; to samo przy edycji w dół. Anulowanie = `restore()` całości;
  - punkty naliczane od części zapłaconej pieniędzmi (`baseAmount` odejmuje `points_discount`).
  Testy `tests/Feature/Loyalty/LoyaltyRedemptionTest.php`. Suita 1909→1924. **Punkty na FV = rabat obniżający cenę pozycji** (jak kod rabatowy) — potwierdzone przez Rafała 07.10, pytanie do księgowego ZAMKNIĘTE.

- **Krok D ZROBIONY (07.10 wieczór):** kartoteka klientów: plakietka salda na liście (`LoyaltyLedger::balances()`, jedno zapytanie GROUP BY e-mail, ta sama definicja co `balance()`), na karcie klienta box „Punkty” (saldo + wartość w zł dla sprzedawcy, oczekujące) z formularzem korekty (`seller.customers.points`, `LoyaltyAdjustmentRequest`: liczba ze znakiem, akceptuje „+500” i „−200”, opis OBOWIĄZKOWY, bo klient go widzi) + „Historia punktów” pod zamówieniami. Korekta tylko gdy `visibleFor()`, inaczej 404.

- **Kartoteka, poprawki (07.10):** box „Punkty” w układzie wierszy jak „Podsumowanie” (Wartość / Oczekujące / „DD.MM.RRRR wygasa → X pkt”); przy zerze tylko „0 pkt” bez wierszy; przy korekcie przelicznik pkt → zł na żywo (Alpine).
- **Mail „punkty wkrótce wygasną” ZROBIONY (07.10):** `App\Services\LoyaltyExpiryReminder` + `loyalty:remind` codziennie o 10:00; `loyalty.reminder_days` = 14 (moja propozycja, Rafał przyjął); kolumna `loyalty_entries.expiry_notified_at` (znacznik na PORCJI, każda najwyżej raz); **jeden mail na klienta i DZIEŃ wygaśnięcia** (reguła Rafała: porcje z tego samego dnia sumujemy, różne dni = osobne maile, nawet w jednym przebiegu); okno liczone w dniach kalendarzowych (`endOfDay`), bo porcja wygasa o 23:59:59 i porównanie co do sekundy dawało 13 dni; bez maila, gdy dług zje wygasające punkty (`min(suma, balance)`); gość → link `/rejestracja`, konto → sklep. Treść informacyjna, bez zł i bez zgody marketingowej.

- **START 07.10 wieczór:** włącznik `loyalty_enabled` w sekcji Ustawień (wyłączenie zatrzymuje naliczanie, strona zasad zostaje); sklep bez uprawnienia widzi w Ustawieniach `x-seller.locked-feature` („Punkty za zakupy w pakiecie Pawilon”); `config/shop.php`: **pavilion + dedicated = true**, stall/booth false; `PackageFeatures`: klucz w `forShop`, etykieta „Punkty za zakupy dla klientów” (z `?? false` dla starych snapshotów), kafelek 🪙 w `highlights()`. **Na produkcji WSZYSTKIE 12 sklepów są na Kramie** → realnie punkty ma tylko Lemoniady (ręcznie). `packages:sync-entitlements` w podglądzie chce dopisać 11 sklepom `false` (efekt zerowy) → NIE uruchamiane, za zgodą Rafała do decyzji. Suita 1941.

- **Karta „Punkty” na stronie zamówienia w panelu sprzedawcy (07.10):** te same wpisy co u klienta (`LoyaltyLedger::forOrder`) + „Saldo klienta: X pkt” z odnośnikiem do kartoteki; tylko gdy zamówienie ma wpisy. Statyczny Blade — po zmianie statusu w Livewire odświeży się dopiero po przeładowaniu strony.

- **Własna liczba punktów w koszyku (07.10):** „Wykorzystaj wszystkie (X pkt)” albo pole „wpisz, ile chcesz wykorzystać” + „Zastosuj”; po zastosowaniu „Zmień · Nie wykorzystuj”. W sesji `cart_points` = liczba albo `'all'` (`CartService::pointsChoice()`). `redeemable(..., ?int $requested)`: więcej niż wolno → PRZYCINAMY z informacją (decyzja Rafała), mniej niż minimum → `below_minimum`. `LoyaltyRedemption` ma `maximum`, `requested`, `canRedeem()`, `capped()`. Wydawanie od najwcześniej wygasających (Rafał: „od najstarszych — wiadomo”).

**PLAN (historyczny, wykonany):** uprawnienie `loyalty_points` dodane od razu, ale FALSE we WSZYSTKICH pakietach (także Pawilonie); Rafał włącza je ręcznie tylko Lemoniadom. Funkcja rośnie po cichu, sprzedawcy zobaczą ją dopiero kompletną.
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
