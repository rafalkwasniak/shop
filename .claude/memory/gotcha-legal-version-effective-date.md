---
name: gotcha-legal-version-effective-date
description: Nowa wersja regulaminu/polityki = podmień też „obowiązuje od dnia” w TREŚCI. v4 i v5 tego nie zrobiły — strona pokazywała 15.08.2026 przy dokumencie z 02.11.
metadata:
  node_type: memory
  type: project
  originSessionId: 897fa409-0081-4c59-a574-33b13ac57bb9
  modified: 2026-11-24T10:20:14.459Z
---

Strona `/regulamin` i `/polityka-prywatnosci` (`legal/show`) pokazuje datę WYŁĄCZNIE z treści dokumentu („Regulamin/Polityka obowiązuje od dnia: …”), nie z `published_at`. Migracje `publish_legal_documents_v4` i `_v5` zmieniały treść, ale nie datę — przez 2,5 miesiąca obie strony mówiły „15.08.2026”. Wyłapane 24.11 przy okazji audytu tekstów po Przelewy24 ([[handoff-2026-11-23]]), naprawione w v6.

**Why:** dokument prawny z datą obowiązywania sprzed własnej zmiany wprowadza w błąd co do tego, która wersja kiedy wiązała.

**How to apply:** każda migracja `publish_legal_documents_vN` dokłada kotwicę daty (`'… obowiązuje od dnia: <stara>.' => '… obowiązuje od dnia: '.now()->format('d.m.Y').'.'`) dla KAŻDEGO publikowanego dokumentu — wzór w `2026_11_24_120000_publish_legal_documents_v6_payment_operators.php` (tam też `DB::transaction`, żeby zła kotwica drugiego dokumentu nie zostawiła pierwszego opublikowanego samotnie). Potem zsynchronizować `docs/prawne/*.html` z treścią z bazy (od v6 pliki = dokładna kopia opublikowanej wersji; wcześniej stały na v3).

Powiązane: [[legal-audit-2026-08-15]].
