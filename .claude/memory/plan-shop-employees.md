---
name: plan-shop-employees
description: "PLAN (07.09, zero kodu): pracownicy sklepu z uprawnieniami per dział, pakiet Pawilon. Nazewnictwo employee (NIE staff, NIE employer). Kramio pierwsze, potem cherry-pick do Magellana."
metadata: 
  node_type: memory
  type: project
  originSessionId: 9c6dfd23-9431-48fe-8850-4d432d1b4fb4
  modified: 2026-09-07T17:21:53.377Z
---

**Stan 2026-09-07: KROKI 1–2 WDROŻONE w Kramio, w toku krok 3.** Suita 1728 → 1748.

- **Krok 1 ZROBIONY** (commit `168b7ec`): `User::currentShop()`, 77 wywołań w 40 plikach. **Do przeniesienia do Magellana `cherry-pick`iem — jeszcze NIE zrobione.**
- **Krok 2 ZROBIONY**: `PanelSection`, `shop_employees` (migracja PRZESZŁA na produkcji), `ShopEmployee`, `UserRole::Employee`, `max_employees` (Pawilon 5).
- **Krok 3 W TOKU**: middleware `section:`, filtr menu, `role:seller,employee`, powrót zakupu pakietu i kasowania sklepu do wyłącznej gestii właściciela.

## Po co to jest

Potrzeba, którą ma każdy właściciel sklepu na pewnym etapie. Dodatkowy powód: Rafał chce **podnieść limity produktów** w pakietach (obserwacja z 07.09: „z Kramio przegrywam chyba z wszystkimi darmowymi sklepami"), a większy katalog to praca dla więcej niż jednej osoby. Drugi powód: **Magellan ma być sprzedawany dalej kolejnym klientom** i brak tej funkcji wyjdzie u każdego z nich.

## NAZEWNICTWO — rozstrzygnięte

**`employee`.** Rafał odrzucił `staff`, zaproponował `employer` — to jednak „pracodawca", czyli właściciel, który już jest w bazie. Ustalone: `UserRole::Employee`, model `ShopEmployee` (jak `ShopIntegration`, `ShopStat`), tabela `shop_employees`, `$shop->employees()`, `$user->employments()`, URL `/sprzedawca/pracownicy`, trasy `seller.employees.*`, UI „Pracownicy". Alias middleware **`section:orders`** — aliasy to kod, więc po angielsku (obok `role:`, `saas`, `tenant`).

## Fakty z kodu ustalone przy planowaniu (07.09)

- **Magellan to fork z żywą wspólną historią.** `origin` w `/home/host473413/domains/magellan.kwasniak.org` wskazuje na katalog Kramio. Punkt rozejścia `c2a49e1` (05.09). Cherry-pick działa i będzie działał tym gorzej, im dłużej się zwleka.
- Magellan ruszył: `routes/web.php` (+93), `Seller/ProductController.php`, `Seller/OrderController.php`. **NIE ruszył `app/Models/User.php`** ani middleware — pliki identyczne w obu repo.
- W Kramio **nie ma żadnej infrastruktury autoryzacji**: `app/Policies/` PUSTY, `UserRole` ma tylko `admin` i `seller`, autoryzacja to powtarzane `abort_unless($x->shop_id === $request->user()->shop?->id, 403)`.
- **52 wystąpienia `user()->shop` w 15 plikach** `app/Http/Controllers/Seller/`.
- Nawigacja panelu = tablica `$nav` w `resources/views/components/layouts/panel.blade.php` — jedno miejsce do filtrowania.
- **`OrderStatusEvent` NIE zapisuje autora** (`Fillable` = `from_status`, `to_status`, `note`). Kolumna `user_id` jest częścią TEJ funkcji — bez niej dziennik zmian traci sens, gdy panel ma więcej niż jednego użytkownika.
- Pakiet `dedicated` (preset Magellana, `available => false`) ma wszystkie uprawnienia — dopisanie `staff_accounts` tam i w `pavilion` wystarczy.

## Model danych

**Tabela członkostw `shop_employees`** (`shop_id`, `user_id`, `permissions` json, `invited_by`, `invited_at`, `accepted_at`, `revoked_at`) — NIE kolumna `shop_id` na `users`. Powód: e-mail w `users` jest unikalny, a księgowa obsługująca dwa sklepy to realny przypadek. Na start **bez przełącznika sklepu**: `User::currentShop()` bierze jedyne członkostwo.

## Uprawnienia = DZIAŁY, nie trasy

`products`, `orders`, `customers`, `content`, `marketing`, `analytics` — 1:1 z pozycjami menu, żeby sprzedawca zaznaczał to, co widzi.

**Pracownik NIGDY nie dostaje (nie jest checkboxem):** pakiet i zakup abonamentu, usunięcie sklepu, zarządzanie pracownikami, **integracje** (klucze Paynow / Fakturownia / InPost = cudze pieniądze i realne faktury), dane firmy.

`orders` na start **jedno, nierozbite** — rozbicie na `orders.view` / `.handle` / `.edit` dokłada się później bez migracji, bo `permissions` to lista kluczy.

## Egzekwowanie — trzy warstwy

1. Trasa: `->middleware('section:products')`. Właściciel przechodzi zawsze.
2. Menu: `$nav` filtrowane TĄ SAMĄ funkcją (żeby nie było linków prowadzących w 403).
3. `User::currentShop()` zamiast `user()->shop`.

Middleware czyta członkostwo z bazy przy każdym żądaniu → odebranie dostępu działa przy następnym kliknięciu, bez kombinowania z unieważnianiem sesji.

## Zaproszenie mailem — wzorzec JUŻ ISTNIEJE

Nie wymyślać nowego. Rejestracja sprzedawcy już tworzy konto z losowym hasłem (`Str::password(32)`, kolumna `NOT NULL`) i wysyła link aktywacyjny; `isActivated()` czyta `email_verified_at`. Ten sam mechanizm obsługuje pracownika.

Szczegóły do zapamiętania: zaproszenie **wygasa po 7 dniach**, da się wysłać ponownie i cofnąć; **właściciel NIGDY nie ustawia hasła pracownikowi** (gdyby je znał, „kto zmienił status" przestaje cokolwiek znaczyć); e-mail już zajęty w `users` → **jasna odmowa**, nie doklejanie roli (klienci sklepów siedzą w osobnej tabeli `customers`, tam kolizji nie ma).

## Kolejność wdrożenia

1. `User::currentShop()` + mechaniczna wymiana 52 wystąpień + testy — **niczego nie zmienia funkcjonalnie, cherry-pick do Magellana OD RAZU**, póki konfliktów nie ma.
2. Rola `employee`, tabela `shop_employees`, `staff_accounts` w `pavilion` i `dedicated`.
3. Middleware `section:` + filtr menu + `role:seller,employee` na grupie tras.
4. Ekran „Pracownicy": lista, zaproszenie, edycja działów, cofnięcie.
5. Mail + aktywacja + wygasanie zaproszeń.
6. Autor w `OrderStatusEvent`, cennik przez `PackageFeatures`, dokumenty prawne.

## Rzeczy uboczne

- **Limit liczby pracowników** (np. 5 w Pawilonie) — żeby konto nie stało się sposobem na obsłużenie trzech firm za jedną opłatę.
- **Zejście z pakietu**: uprawnienia są lepkie (snapshot), ale gdy `staff_accounts` znika — dostępy **wygasić, nie kasować**, inaczej powrót na Pawilon to wpisywanie ludzi od nowa.
- **Zmiana regulaminu KONIECZNA** (sprzedawca dopuszcza osoby trzecie do danych klientów i za nie odpowiada). **Rafał 07.09: „to nie stanowi problemu"** — nie rozważać ponownie, nie traktować jako blokady. Konsekwencja: nowa wersja dokumentu = ponowna akceptacja u wszystkich sprzedawców.
- Pula AI liczona per sklep — pracownik zużywa pulę sklepu.

## Magellan: handlowo POZA zakresem

Oferta mówi „brak prawa do przyszłych wersji silnika", a Etap 2 to konkretne funkcje Magellan Bay. Przeniesienie pracowników to **rozszerzenie Etapu 2 do dogadania albo prace dodatkowe 100 zł/h**. Nie wrzucać po cichu. To także pierwszy **świadomy wyjątek** od zasady z [[plan-magellan-bay-separate-project]] („funkcji się nie przenosi, tylko bezpieczeństwo i API dostawców").

Powiązane: [[plan-packages]], [[pricing-packages]], [[gate-order-edit-behind-paid]], [[naming-and-locale-convention]], [[priorities-launch-first]].
