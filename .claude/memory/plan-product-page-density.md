---
name: plan-product-page-density
description: Limity produktów 60/300/600 są wielokrotnościami sufitu drabinki motywów (20/stronę). Zmiana jednego BEZ drugiego cicho psuje równe podstrony.
metadata: 
  node_type: memory
  type: project
  originSessionId: 9c6dfd23-9431-48fe-8850-4d432d1b4fb4
  modified: 2026-09-07T17:22:28.334Z
---

**Decyzja Rafała 2026-09-07.** Limity podniesione z 24/72/240 na **Kram 60 / Stragan 300 / Pawilon 600** (powód: „z Kramio przegrywam chyba z wszystkimi darmowymi sklepami"; skok 300→600 odzwierciedla podwójną cenę Pawilonu).

## Zależność, o której łatwo zapomnieć

`config/themes.php` → `listing.steps` dobiera długość strony do wielkości katalogu. **Sufit obniżony z 24 (4×6) na 20 (4×5)** właśnie po to, żeby wszystkie trzy limity dzieliły się bez reszty:

| Pakiet | Limit | Podstrony przy pełnym katalogu |
|---|---|---|
| Kram | 60 | 3 × 20 — pełne |
| Stragan | 300 | 15 × 20 — pełne |
| Pawilon | 600 | 30 × 20 — pełne |

Sufit jest jednocześnie ostatnim szczeblem drabinki I limitem Krama, więc darmowy sklep z pełnym katalogiem mieści się dokładnie w trzech założonych podstronach (`max_pages => 3`).

**ZMIANA LIMITÓW ALBO SUFITU MUSI IŚĆ PARAMI.** Ruszenie jednego bez drugiego nie wywali żadnego testu — po prostu ostatnia podstrona zrobi się w połowie pusta i nikt tego nie zauważy. Zależność jest opisana w komentarzu w `config/themes.php`.

**Czego to NIE załatwia (mówić wprost, gdyby wróciło):** sklep, który ma 47 produktów, dalej skończy stronę niepełnym wierszem. Równa siatka zależy od liczby produktów SPRZEDAWCY; limit rozstrzyga wyłącznie przypadek katalogu pełnego.

## Konsekwencje techniczne

- Klasy siatki `lg:grid-cols-3` / `lg:grid-cols-4` muszą istnieć w buildzie Tailwinda — `columns` trzymać w zbiorze {3, 4} (patrz [[tailwind-classes-must-exist-in-build]]).
- Podniesienie limitu nie dochodzi do istniejących sklepów samo — patrz [[gotcha-entitlements-are-sticky-raise-needs-command]].
- Testy zejścia z pakietu (`SubscriptionLifecycleTest`, `PackagePaymentTest`) czytają teraz limit z configu zamiast mieć go przybitego — dawniej płonęły przy każdej zmianie cennika.

Powiązane: [[pricing-packages]], [[plan-packages]], [[storefront-theme-system]].
