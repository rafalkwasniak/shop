---
name: deepseek-ai-improve
description: "„Popraw przez AI\" (DeepSeek) — DZIAŁA; model deepseek-v4-flash + reasoning_effort=low, przełączalny na v4-pro w .env."
metadata: 
  node_type: memory
  type: project
  originSessionId: ad2d01af-7396-446a-bc53-3263cd9693a7
  modified: 2026-08-15T14:13:09.585Z
---

Funkcja „Popraw przez AI" (redakcja tekstu: ortografia, interpunkcja, styl — bez dodawania treści) przeniesiona z kociaczek.com.pl (2026-06-28). Elementy w Kramio:
- `App\Services\AiTextImprover` — wywołanie DeepSeek przez `Http` (config `services.deepseek.{key,base_url,model}`); klucz tylko server-side, treści nie logujemy.
- `App\Http\Controllers\AiController@improve` — waliduje `field` (na razie tylko `shop_description`) + `text`, zwraca JSON `{text}`, nigdy nie zapisuje; błąd usługi → 503. Trasa `POST /ai/popraw` name `ai.improve` w grupie `auth`, `throttle:30,1`.
- Komponent `<x-ai-improve-button field="..." target="...">` + moduł `resources/js/ai.js` (zero zależności, używa `window.showToast`); limit użyć/pole/stronę przez `data-ai-uses` i `config('shop.ai.max_uses_per_field')`.
- Limity/długości: `config('shop.description_max')` (2000) = jedno źródło prawdy dla walidacji opisu i limitu długości AI.
- Wpięte pod polem „Opis sklepu" w `seller/shop/edit.blade.php`.

**MIGRACJA MODELU (2026-07-26):** DeepSeek **wycofał `deepseek-chat`** — `GET /models` zwraca już tylko `deepseek-v4-flash` i `deepseek-v4-pro`. Funkcja padła z 503, aż podmieniliśmy model. Stan po migracji (zweryfikowany realnym wywołaniem):
- `DEEPSEEK_MODEL=deepseek-v4-flash` — flash wystarcza do redakcji (wynik nieodróżnialny od pro na opisie produktu, ~2× szybszy). Przełączenie na `deepseek-v4-pro` = jedna linia w `.env`, kod nie zna nazw modeli.
- **Modele v4 rozumują przed odpowiedzią** i to boli: pro bez ograniczenia = 19 s i 1282 tokeny reasoningu na krótki opis. Stąd `DEEPSEEK_REASONING_EFFORT=low` (dozwolone: `low|medium|high|max|xhigh`; **nie ma `minimal`** — API odbija 400). Pusta wartość = parametr nie leci w payloadzie.
- `DEEPSEEK_TIMEOUT=120` — było zaszyte 30 s, za mało: strona CMS ma limit 30 tys. znaków (`config('pages.content_max')`), a reasoning wydłuża odpowiedź. Realny pomiar po zmianach: opis produktu ~4 s.
- Klucz jest wklejony i działa (wcześniejsze „PUSTY — funkcja zwraca 503" nieaktualne).

**PUŁAPKA reasoning_effort (pomiar 2026-07-31):** na `deepseek-v4-flash` **BRAK parametru = domyślne, CIĘŻKIE rozumowanie**, nie „bez rozumowania". Zadanie `proofread` miało `reasoning_effort => ''` (nie wysyłaj) — ustawienie zmierzone jeszcze na `deepseek-chat`, który nie rozumował; na v4-flash dawało 1,8–6,6 tys. tokenów myślenia i 20–67 s na fragment ~1,1 tys. znaków (stąd „100 sekund" Rafała przy opisie sklepu). Z jawnym `low`: 2× mniej myślenia, 14–37 s; `medium` = 47 s lub timeout. Naprawione: `proofread.reasoning_effort = 'low'` w `config/ai.php`. **Mniejsze fragmenty NIE pomagają** (zmierzono: limit 600 zn. → 4 fragmenty → 79 s ściany vs 22 s przy 1200), bo czas dominuje myślenie per żądanie, nie długość wyjścia — `AI_CHUNK_CHARS` zostaje 1200. Rozrzut czasów (ten sam fragment: 14 s albo 37 s) jest po stronie DeepSeeka.

**Ta sama awaria ubiła INNE serwisy na tym serwerze.** Wycofanie `deepseek-chat` dotyczy wszystkiego, co go woła — przy zgłoszeniu „AI nie działa" w dowolnym projekcie Rafała sprawdź najpierw nazwę modelu. Naprawione 2026-07-26 w `pobierzgpx.pl` (`app/Http/Resources/DeepSeekResource.php`, generowanie opisów tras cronem): model wyjęty z kodu do `config/services.php` + `DEEPSEEK_MODEL`/`DEEPSEEK_REASONING_EFFORT` w `.env`, wycięte `max_tokens` (reasoning wliczał się w limit i ucinał opis). Zweryfikowane realnym wywołaniem: ~42 s, opis 1400 znaków.

**RODO — USTALONE (Rafał, 2026-08-15):** do DeepSeeka **nie idą żadne dane osobowe** — wyłącznie treść opisowa: CO jest sprzedawane, nigdy KTO sprzedaje. Że użytkownik może sobie sam coś wpisać w pole opisu, nie czyni z tego naszych danych. Konsekwencja: **DeepSeek nie jest podwykonawcą przetwarzania** i nie musi być na liście podprocesorów w Polityce Prywatności (§17 ust. 5 regulaminu). Nie podnosić tego ponownie przy audytach prawnych. Powiązane: [[plan-ai-chunked-correction]].

Przy okazji naprawiono `bootstrap/app.php`: `shouldRenderJsonWhen` teraz `api/*` LUB `$request->expectsJson()` — żeby AJAX dostawał błędy w JSON (wcześniej tylko `api/*`, więc AJAX-owe 401/422 wracały jako redirect). Formularze webowe nadal dostają redirect z błędami. Powiązane: [[frontend-stack-decision]] (brak publicznego JSON API — to wewnętrzny AJAX dla Livewire/JS, nie kontrakt).

**ADMIN BEZ SKLEPU (2026-10-01):** administrator nie ma sklepu, a redaguje wiadomości platformy (`mailing_body`). `AiClient` przyjmuje `?Shop` BEZ wartości domyślnej — `null` = praca platformy, bez puli. Kontroler przepuszcza `null` tylko dla `isAdmin()`. Front pokazuje `message` serwera przy 4xx, nie tylko 429. Patrz [[handoff-2026-10-01]].
