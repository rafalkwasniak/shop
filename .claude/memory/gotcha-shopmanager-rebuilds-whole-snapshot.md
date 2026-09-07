---
name: gotcha-shopmanager-rebuilds-whole-snapshot
description: "Nowe uprawnienie w config/shop.php MUSI trafić do ShopManagera, inaczej znika ze snapshotu przy każdym „Zapisz" w konsoli admina. Trafione 2×."
metadata:
  type: feedback
---

`App\Livewire\Administrator\ShopManager::save()` **odbudowuje cały snapshot uprawnień od zera** i zapisuje go przez `forceFill`. Uprawnienie, które nie ma pola w tym formularzu, nie tylko nie da się ustawić — **znika ze snapshotu przy każdym zapisie** i kasuje ręczne nadanie.

**Trafione dwa razy:** najpierw `ai_weekly_limit` (w pliku stoi komentarz opisujący tamten wypadek), potem `max_employees` — dołożone do `config/shop.php` bez dopisania do konsoli, przez co admin nie mógł nadać kont pracowniczych sklepowi z Krama. **Wyłapał to Rafał pytaniem**, nie test.

**How to apply:** dokładając uprawnienie do `config/shop.php`, dopisz je od razu:
- **liczbowe** → `ShopManager::numericEntitlements()` + publiczna właściwość `public int $klucz = 0;`. Mount, preset, walidacja, zapis i widok czytają z tej listy, więc nic więcej nie trzeba.
- **boolowskie** → `ShopManager::booleanEntitlements()` + `public bool $klucz = false;`.

Test `test_saving_preserves_every_numeric_entitlement` chodzi po tej samej liście, więc złapie każdy następny klucz — ale tylko jeśli został do listy dopisany.

**Zasada ogólna, którą to potwierdza:** uprawnienie jest nadawalne GESTEM, poza pakietem. Sklep z Krama może dostać konta pracownicze albo wysyłkę kurierską i zostać Kramem — to nadanie, nie awans. Każda funkcja bramkowana pakietem musi się w ten sposób dać nadać ręcznie.

Powiązane: [[plan-packages]], [[plan-per-shop-custom-pricing]], [[gotcha-entitlements-are-sticky-raise-needs-command]], [[gotcha-package-gated-feature-must-expire]].
