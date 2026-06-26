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
    public function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        // sprowadź do 9-cyfrowego rdzenia
        if (strlen($digits) === 11 && str_starts_with($digits, '48')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if ($digits === '') {
            return null;
        }

        // dopnij prefiks kraju
        return '48'.$digits;
    }
}
