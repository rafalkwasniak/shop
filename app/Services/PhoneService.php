<?php

namespace App\Services;

/**
 * Normalizacja polskiego numeru telefonu do postaci kanonicznej: prefiks kraju
 * `48` + 9 cyfr, bez spacji i `+` (np. `48500600700`). Bezstanowy serwis wołany
 * w `prepareForValidation()` Form Requestu — wartość jest znormalizowana zanim
 * zobaczą ją reguły i baza. Prefiks PL hardcoded (serwis jednokrajowy).
 */
class PhoneService
{
    /**
     * Reguła walidacji znormalizowanego numeru: prefiks `48` + dokładnie 9 cyfr
     * komórki PL (finalnie `48 123 456 789`). Stosowana wszędzie, gdzie zbieramy
     * telefon — po `normalize()` w `prepareForValidation()`/przed `validate()`.
     */
    public const RULE = 'regex:/^48[0-9]{9}$/';

    /**
     * Komunikat, gdy numer po normalizacji nie pasuje do `48` + 9 cyfr.
     * Wprost o prefiksie `48`, bo po normalizacji pole pokazuje już `48…`
     * — samo „9 cyfr" myliłoby (np. `123` → w polu `48123`).
     */
    public const RULE_MESSAGE = 'Podaj numer w formacie 48 i 9 cyfr, np. 48 500 600 700.';

    public function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        // krajowy zapis z zerem wiodącym (0 500 600 700) → zdejmij trunk „0"
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        // Zdejmij prefiks kraju „48", GDY występuje — bezwarunkowo, nie tylko dla
        // 11 cyfr. Dzięki temu normalizacja jest idempotentna: ponowny przebieg na
        // gotowej wartości „48…" nie dokłada kolejnego „48" (bug: przy edycji
        // niepoprawnego numeru rosło „48484848…"). Bezpieczne, bo zbieramy tylko
        // polskie komórki, a te nigdy nie zaczynają się od „48".
        if (str_starts_with($digits, '48')) {
            $digits = substr($digits, 2);
        }

        if ($digits === '') {
            return null;
        }

        // dopnij prefiks kraju
        return '48'.$digits;
    }

    /**
     * Czytelna postać do wyświetlenia: „+48 668 196 229". Przyjmuje dowolny zapis
     * (najpierw normalizuje). Gdy rdzeń nie ma 9 cyfr — zwraca znormalizowaną
     * wartość bez grupowania (nie zgadujemy formatu).
     */
    public function format(?string $value): ?string
    {
        $normalized = $this->normalize($value);

        if ($normalized === null) {
            return null;
        }

        $core = substr($normalized, 2);

        if (strlen($core) !== 9) {
            return $normalized;
        }

        return '+48 '.substr($core, 0, 3).' '.substr($core, 3, 3).' '.substr($core, 6, 3);
    }

    /**
     * Wartość do linku `tel:` w formacie międzynarodowym: „+48500600700".
     * Kanoniczne „48…" BEZ plusa telefon wybrałby jako numer krajowy
     * zaczynający się od 48 — czyli zupełnie inny numer.
     */
    public function href(?string $value): ?string
    {
        $normalized = $this->normalize($value);

        return $normalized === null ? null : '+'.$normalized;
    }
}
