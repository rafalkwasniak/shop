---
name: gotcha-form-inside-paragraph
description: "<form> w <p> = niepoprawny HTML; przeglądarka zamyka akapit przed formularzem i sąsiednie elementy tracą styl akapitu (np. wyśrodkowanie). Dotyczy x-cookie-settings-link."
metadata:
  type: project
---

`x-cookie-settings-link` („Ciasteczka”) renderuje **`<form>`**, nie odnośnik (zmiana stanu przez POST). Formularz w akapicie `<p>` to niepoprawny HTML: parser przeglądarki zamyka akapit tuż przed formularzem, więc formularz i wszystko po nim wypada poza akapit i traci jego klasy (`text-center`, rozmiar, kolor). W Bladzie i w odpowiedzi serwera wygląda poprawnie, psuje się dopiero w DOM przeglądarki.

**Incydent 07.10:** linki „Ciasteczka · Zgłoś nielegalną treść” pod boxem logowania stały przy lewej krawędzi mimo `text-center`. Poprawka: `<div>` zamiast `<p>` w `components/layouts/guest.blade.php` i `public.blade.php`.

**How to apply:** kontener z formularzem (cookie link, każdy `<form>`) → `div`/`span`/`li`, nigdy `p`. Pilnuje tego `tests/Feature/CookieLinkMarkupTest.php` (skan wszystkich widoków). Uwaga: test łapie też DOSŁOWNY napis „<p>” w komentarzu Blade przed linkiem — w komentarzach pisać „akapit”, nie znacznik.
