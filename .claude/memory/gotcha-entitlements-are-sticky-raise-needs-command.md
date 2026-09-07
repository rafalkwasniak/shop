---
name: gotcha-entitlements-are-sticky-raise-needs-command
description: "Podniesienie limitu w config/shop.php NIE DOCHODZI do istniejących sklepów — snapshot na sklepie wygrywa. Trzeba puścić `packages:sync-entitlements --apply`."
metadata: 
  node_type: memory
  type: project
  originSessionId: 9c6dfd23-9431-48fe-8850-4d432d1b4fb4
  modified: 2026-09-07T17:22:09.691Z
---

`Shop::entitlement()` czyta **snapshot zapisany na sklepie**, nie config. To celowe (ręczne nadania przeżywają odnowienie abonamentu), ale ma drugą stronę: **hojność też nie dochodzi**.

Zmiana `config/shop.php` daje nowe wartości wyłącznie sklepom, które dopiero powstaną albo zmienią pakiet. Istniejące zostają z tym, co miały w dniu zakupu — a cennik i landing pokazują już nowe liczby. Sprzedawca widzi wtedy w panelu „3 / 24", podczas gdy strona główna obiecuje 60.

**Wyłapane 07.09** przy podnoszeniu limitów produktów: wszystkie 8 sklepów na produkcji miało `max_products: 24` mimo configu 60.

**How to apply:** po KAŻDEJ zmianie uprawnień w `config/shop.php` uruchomić:

```bash
/opt/alt/php85/usr/bin/php artisan packages:sync-entitlements          # podgląd
/opt/alt/php85/usr/bin/php artisan packages:sync-entitlements --apply  # zapis
```

Komenda działa **wyłącznie w górę** — snapshot hojniejszy od cennika (ręczne nadanie, pozostałość po lepszej ofercie) zostaje nietknięty. Obniżka ma własną świadomą ścieżkę: zakup niższego pakietu, który ukrywa nadwyżkę i tłumaczy to mailem. **Komendy NIE MA w harmonogramie** i nie wolno jej tam wstawiać — cykliczna kasowałaby ręczne nadania przy pierwszej korekcie configu w dół.

Powiązane: [[plan-packages]], [[pricing-packages]], [[plan-product-page-density]].
