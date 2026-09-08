---
name: gotcha-nullable-normalize-into-typed-livewire-property
description: Serwis normalizujący zwracający null przypisany wprost do typowanej właściwości Livewire = TypeError PRZED validate(); klient widzi 500 zamiast komunikatu.
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 0b3bc4a3-787c-4d79-aeba-be8022c9e677
  modified: 2026-09-08T13:37:02.711Z
---

`NipService::normalize()` i `PhoneService::normalize()` zwracają **null** dla wejścia bez cyfr (puste pole, same litery). Właściwości komponentów Livewire są typowane `public string`. Przypisanie wyniku wprost:

```php
$this->company_nip = app(NipService::class)->normalize($this->company_nip);   // ŹLE
$this->company_nip = app(NipService::class)->normalize($this->company_nip) ?? $this->company_nip;   // DOBRZE
```

**Why:** normalizacja siedzi w `place()` **przed** `validate()`, więc TypeError wywala request zanim reguła `required` zdąży zadziałać. Klient, który zaznaczył „kupuję na firmę" i zostawił NIP pusty, dostawał 500 zamiast „Podaj poprawny NIP". Reguła walidacji **istniała i wyglądała na zabezpieczenie** — dlatego wada przeżyła code review i suitę testów.

**Trafione 2×** (08.09.2026): Magellan Bay zgłosił z produkcji, Kramio miało identyczny kod w tej samej linii pliku. W obu plikach dwa pozostałe miejsca (telefon w `place()`, NIP w `lookupCompany()`) były już domknięte poprawnie — wypadła jedna linia z trzech.

**How to apply:**
- Wynik dowolnego `normalize()` (`?string`) przypisywany do typowanej właściwości Livewire **zawsze** z `?? $this->pole`. Przy null zostaje to, co wpisał klient — widzi swoją wartość razem z błędem.
- W Form Requestach (`merge()` na tablicy) null jest bezpieczny — problem dotyczy wyłącznie typowanych właściwości.
- Test regresji musi sprawdzać `assertHasErrors`, a **nie** brak wyjątku: Laravel w testach łapie TypeError i zamienia na 500, więc test wygląda wtedy na zwykłą nieudaną asercję (`assertSet` dostaje null, bo snapshot komponentu nie wraca). Bez `withoutExceptionHandling()` łatwo źle odczytać przyczynę.

Wzorzec normalizacji: [[input-normalization-conventions]]. Bliźniaczy produkt: [[plan-magellan-bay-separate-project]].
