---
name: demo-shop-aracaffee
description: "Sklep demo Aracaffee (shop 20, aracaffee.kramio.pl) założony 14.10 dla klienta — uprawnienia Straganu+punkty przy pakiecie Kram"
metadata:
  node_type: memory
  type: project
  originSessionId: c618c1df-18ce-4b5f-8801-c52884901a75
  modified: 2026-10-15T17:14:57.646Z
---

14.10.2026 założony z oferty `sklep.aracaffee.com` (za zgodą właściciela, FHU TEKAWA Maciej Kaczkowski, Tychy) jako **demo dla klienta**.

- Konto user 22. **15.10 przekazane klientowi:** login zmieniony z `aracaffee@kramio.pl` na `sklep@aracaffee.com`, losowe hasło wysłane mailem Rafałowi (rafal@kwasniak.org) — nigdzie indziej nie zapisane. Kod 2FA idzie teraz do skrzynki KLIENTA. Zgody na regulamin NIE zapisane celowo: zaakceptuje je pierwszy logujący się.
- Shop 20, `aracaffee.kramio.pl`, pakiet `stall` (Kram, 0 zł), ale **snapshot uprawnień = Stragan + `loyalty_points`** (decyzja Rafała: klient ma od razu poznać możliwości). **Zakup Straganu przez `assignPackage('booth')` nadpisze snapshot i ZGASI punkty** — patrz [[gotcha-entitlements-are-sticky-raise-needs-command]].
- Dostawa: tylko odbiór osobisty + płatność przy odbiorze (brak konta bankowego, Paynow, ShipX). Regulamin z naszego wzoru — po zmianie metod wstawić wzór ponownie.
- 18 produktów, 4 dostępne; punkty 5% / 0,05 zł = 1 pkt za 1 zł. Strony: Dzierżawa urządzeń, Program poleceń (zgłoszenie polecenia mailem — dopisane przez nas, Rafał zatwierdził).
- Sklep sam przeszedł na ACTIVE po dodaniu produktów — jest publiczny.

**Why:** demo sprzedażowe; nietypowa konfiguracja (pakiet ≠ uprawnienia) wygląda jak błąd, jeśli nie wiadomo skąd.
**How to apply:** nie „naprawiać” rozjazdu pakiet/uprawnienia; przy zmianie pakietu tego sklepu pamiętać o punktach.
