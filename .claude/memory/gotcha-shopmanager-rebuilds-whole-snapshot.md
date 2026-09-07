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

## Preset „Sklep dedykowany" NIE JEST pakietem Kramio

Opisuje wdrożenie na **serwerze klienta**, wykupione raz, z uprawnieniami bez limitów i ceną zero. Sklep na platformie nigdy nim nie jest — a jedno kliknięcie dawało mu wszystko za darmo i bezterminowo. **Rafał wyłapał to 07.09** patrząc na `/administrator/sklepy/{id}`: *„to mi się nie klei z całością"*.

Rozstrzygnięcie: **`ShopManager::assignablePackages()` wiąże listę presetów z TRYBEM** (`Mode::dedicated()`), a nie usuwa jej z cennika — w instalacji dedykowanej to jedyny preset, który się tam nadaje. Pakiet, który sklep już ma, zostaje na liście zawsze, inaczej walidacja `in:` odrzuca zapis bez zmiany pakietu. Brama stoi też w `applyPreset()`, bo żądanie Livewire omija widok.

Wszędzie indziej w panelu („z czego pozwalamy wybierać": rejestrator wpłat, filtry) obowiązuje **`PackageFeatures::purchasable()`**. Rejestrator był groźny nie jako dziwny wpis, tylko dlatego, że **zapis wpłaty PRZYPISUJE pakiet** — czyli ten sam skutek co przycisk presetu, bocznymi drzwiami.

**Sufit walidacji uprawnień liczbowych to 10 mln, nie 100 tys.** Preset dedykowany ma milion produktów; przy dawnym sufcie sklepu na tym presecie **nie dało się zapisać w konsoli** — walidacja odrzucała jego własny poprawny stan. W Kramio nikt tego nie widział, w instalacji dedykowanej dotyczy każdego sklepu.

Powiązane: [[plan-packages]], [[plan-per-shop-custom-pricing]], [[gotcha-entitlements-are-sticky-raise-needs-command]], [[gotcha-package-gated-feature-must-expire]].
