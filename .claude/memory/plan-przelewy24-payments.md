---
name: plan-przelewy24-payments
description: "Przelewy24 jako DRUGI operator obok Paynow — osobna ścieżka, nie wspólna abstrakcja. Rozeznane 2026-08-09, ZERO kodu."
metadata: 
  node_type: memory
  type: project
  originSessionId: 759ad61b-8139-4389-af85-1ced3e332f53
  modified: 2026-11-23T18:32:24.747Z
---

ROZEZNANIE 2026-08-09 (rozmowa, nie kod). Rafał: „P24 są bardzo popularne, może przyjść klient". Nie jest to plan ani zobowiązanie — notatka, żeby nie zaczynać od zera.

**Decyzja kształtu (Rafał):** P24 to **kolejna opcja na liście, OSOBNA ścieżka** — `Przelewy24Service` obok `PaynowService`, własna trasa webhooka, własne logi, własne pola integracji. **NIE łączyć z Paynow** wspólnym interfejsem `PaymentGateway`. Uzasadnienie: bramki różnią się sposobem potwierdzania płatności na tyle, że abstrakcja przeciekłaby — a przeciekająca abstrakcja w kodzie przyjmującym pieniądze jest gorsza niż dwa podobne serwisy. Jedyny styk = zwrotnica w checkoucie (`match` na wybranego operatora). Dla kupującego dalej jedna pozycja „Płatność online".

**ZMIANA DECYZJI 2026-11-23 (Rafał): sklep może mieć WIELE bramek naraz** („jedna, dwie, kiedyś trzy — jego wybór"). Kupujący WYBIERA bramkę w kasie, więc zdanie wyżej o „jednej pozycji" jest NIEAKTUALNE. Kształt: lekka lista bramek (enum `PaymentProvider`: etykieta, typ integracji, czy aktywna), BEZ wspólnego interfejsu mechaniki płatności (ten zakaz zostaje). Zamówienie zapamiętuje bramkę w kolumnie `payment_provider` (istniejące zamówienia online → `paynow`). Każda bramka = własny wiersz `shop_integrations` (unikat shop_id+type) i własny włącznik w Ustawieniach. Rafał ma sandbox P24 z innego serwisu — czy przyjmie adresy kramio, sprawdzimy w teście. Nikt nie czeka; robimy, bo P24 popularne.

**POSTĘP 2026-11-23 (niecommitowane na koniec kroku 3):** krok 1 (enum `PaymentProvider`, `IntegrationType::Przelewy24`, kolumna `orders.payment_provider` — migracja WYKONANA na prod), krok 2 (karta Integracji + włącznik/auto-FV w Ustawieniach, `SERVER_OUTBOUND_IP` w .env), krok 3 (`Przelewy24Service` + test połączenia po zmianie danych P24) — ZROBIONE. Sandbox Rafała = sklep `lemoniady` (id 7), merchant 431164; na REALNYM sandboxie potwierdzone: `testAccess` OK i podpis rejestracji OK. Niepotwierdzone na żywo: podpis verify i notyfikacji (dopiero E2E). Krok 4 ZROBIONY: `Przelewy24WebhookController`, `POST /platnosci/przelewy24/webhook` (żyje na prod, CSRF wyjęty) — zamówienie z `sessionId` (`kramio-{id}-…`, łapie też starsze próby), podpis+konto, kwota = zamówienie, verify, Paid pod lockForUpdate; anulowane też weryfikujemy (pieniądze nie wiszą). Krok 5 ZROBIONY: `Shop::paymentProviderReady()/activePaymentProviders()/payableProvidersForUnfinishedOrders()`, `onlinePaymentsEnabled()` = jakakolwiek bramka; kasa: opcje `online:{bramka}` (`Checkout::onlineOption()`), rozdzielane przed `OrderService`; strona płatności: bramka zamówienia albo pozostałe aktywne. lemoniady: aktywne tylko P24. Krok 6 ZROBIONY: `PaymentMethod::Online` = „Płatność online", `Order::paymentMethodLabel()` dokleja bramkę (maile, panel, admin, Moje konto, podziękowanie); karta produktu „(Paynow lub Przelewy24)". Pełna suita 2079 zielona. DECYZJE Rafała 23.11: teksty sprzedażowe (PackageFeatures, landing) BEZ nazw operatorów — ogólnie „płatności online / własne konto u operatora"; zapłata za ANULOWANE zamówienie = weryfikujemy, status zostaje (zostawić). Dalej: E2E Rafała, potem commit. Zostało: kasa z wyborem bramki + strona płatności (5), teksty (6), E2E (7). W kasie: e-mail > 50 znaków → P24 niedostępne (usługa zwraca null).

**Cztery różnice techniczne wobec Paynow:**
1. Auth: Basic (posId:apiKey) + pole `sign` w ciele, zamiast nagłówków `Api-Key`/`Signature`.
2. Podpis: SHA-384 z JSON-a WYBRANYCH pól + klucz CRC. **GOTCHA: zestaw pól jest INNY dla register, inny dla verify, inny dla notyfikacji.** Zły podpis = gołe `401` bez opisu.
3. **Obowiązkowy `PUT /transaction/verify` po webhooku** — sam webhook NIE potwierdza zapłaty (Paynow tak). Pominięcie = przyjmowanie fałszywych potwierdzeń.
4. Rejestracja zwraca `token`, URL przekierowania (`secure.przelewy24.pl/trnRequest/{token}`) składamy sami; Paynow daje gotowy `redirectUrl`.

**Wycena zgrubna:** bramka ~1 dzień; dołożenie wyboru operatora (UI integracji, etykiety, zwrotnica, testy) ~2–3 dni. Statusy zamówień i koszyk BEZ ZMIAN — `PaymentMethod::Online` jest operator-agnostyczne od początku (patrz komentarz w `app/Enums/PaymentMethod.php`).

**Hamulec formalny:** sandbox P24 zakłada się z panelu konta PRODUKCYJNEGO — nie da się zacząć od piaskownicy. Do developmentu trzeba dostępu do sandboxa klienta albo własnego konta produkcyjnego P24. Umowa i weryfikacja sklepu przez P24 są po stronie klienta (klucze per sklep, jak przy Paynow).

**ZWERYFIKOWANE NA OFICJALNYM YAML-u 2026-11-23** (`https://developers.przelewy24.pl/yaml/pl_documentation_1.0.yaml`, v1.0.18 — curl z UA przeglądarki przechodzi, 403 z sierpnia minęło):
- Auth Basic: login = `posId`, hasło = klucz API („klucz do raportów"). Dane sprzedawcy: merchantId, posId (domyślnie = merchantId), CRC, klucz API.
- Podpis = `hash('sha384', json_encode($pola, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))`, KOLEJNOŚĆ kluczy jak niżej, liczby jako int:
  - register: sessionId, merchantId, amount, currency, crc
  - verify: sessionId, orderId, amount, currency, crc
  - notyfikacja: merchantId, posId, sessionId, amount, originAmount, currency, orderId, methodId, statement, crc
- Notyfikacja idzie TYLKO dla opłaconych (brak statusów porażki). Ponawiana po 3/5/15/30/60/150/450 min, dopóki nie zrobimy udanego `verify`. Bez verify pieniądze NIE trafiają do sprzedawcy.
- `urlStatus` i `urlReturn` per transakcja. Redirect: `{base}/trnRequest/{token}`; base sandbox `https://sandbox.przelewy24.pl`, prod `https://secure.przelewy24.pl`. `testAccess` = GET do sprawdzenia kluczy.
- **GOTCHA: P24 wymaga wpisania w panelu („Moje dane → Dane API i konfiguracja → Adres IP") IP serwera, który woła API.** Karta integracji musi pokazać IP Kramio; **po przeprowadzce na nowy serwer każdy sprzedawca z P24 musi zmienić IP.**
- Limity: email max 50 znaków (wymagany), sessionId max 100, orderId int64.

Powiązane: [[plan-online-payments-mbank]] (Paynow per-sklep, wzorzec do skopiowania), [[plan-package-payments]] (opłaty za Kramio = OSOBNE konto platformy, nie ruszać).
