---
name: gotcha-package-gated-feature-must-expire
description: "Funkcja za pakietem musi GASNĄĆ przy zejściu z pakietu — i trzeba to sprawdzić DWA razy: w bramie dostępu I w widoku, który listę chowa."
metadata:
  type: feedback
---

Zamykanie ekranu bramą `entitlement()` chroni tylko **wejście do tworzenia**. Nie gasi tego, co już powstało, gdy sklep zejdzie z pakietu albo nie odnowi abonamentu.

**Wyłapane 07.09** przy pracownikach, już PO uznaniu funkcji za skończoną. Sklep schodził z Pawilonu na Kram: `allowsEmployees()` → false, `max_employees` → 0, ekran „Pracownicy" pokazywał zachętę — a **pracownicy dalej wchodzili do panelu (200)**. Wystarczyłoby opłacić Pawilon raz, żeby mieć zespół na zawsze.

**Why:** brama dostępu (`User::canAccess()`) pytała wyłącznie o członkostwo, nie o pakiet pracodawcy. To dwa różne pytania i tylko jedno było zadane.

**How to apply:** przy KAŻDEJ funkcji zamkniętej pakietem zadać trzy pytania, nie jedno:
1. Czy da się ją **utworzyć** bez pakietu? (brama na trasie / w kontrolerze)
2. Czy to, co **już istnieje**, przestaje działać po zejściu z pakietu? (tu leżała luka)
3. Czy po **odnowieniu** wraca samo, bez odtwarzania przez sprzedawcę? (wygaszać, NIE kasować)

**Druga połowa tej samej pułapki: WIDOK.** Po naprawie bramy lista pracowników dalej była schowana za `@unless ($allowed)`, więc właściciel po zejściu z pakietu widział samą zachętę i ani jednego nazwiska — wyglądałoby, jakby konta zniknęły. Poprawka w kontrolerze bez poprawki w widoku to pół roboty; test na widok złapał to dopiero za drugim podejściem.

Wzorzec z Kramio: `ShopEmployee::isEffective()` = `isActive() && shop->allowsEmployees()`, wołane z `currentShop()` i `canAccess()`. Stan w UI nazwany wprost („Wygaszony — pakiet"), bo „Aktywny" byłoby nieprawdą, którą właściciel odkryłby dopiero telefonem od pracownika.

Powiązane: [[plan-shop-employees]], [[plan-packages]], [[gate-order-edit-behind-paid]], [[gotcha-entitlements-are-sticky-raise-needs-command]].
