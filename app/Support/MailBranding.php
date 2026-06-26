<?php

namespace App\Support;

/**
 * Branding maili. Domyślnie nasz system (znak ◐ + nazwa, gradient amber→rose).
 * Docelowo (SaaS) każdy sklep dostarczy własny zestaw — stąd zwracamy tablicę
 * gotową do wstrzyknięcia w szablon, a nie zaszywamy wartości w widoku.
 *
 * Per-sklep podłączymy przez MailBranding::for($shop) gdy powstanie model Shop;
 * teraz mamy tylko domyślny system.
 */
class MailBranding
{
    /**
     * Branding dla danego sklepu. Dziś zawsze system (model Shop jeszcze nie
     * istnieje) — gdy powstanie, rozwiążemy logo/kolory sklepu po `$shopId`.
     *
     * @return array<string, string|null>
     */
    public static function for(?int $shopId): array
    {
        // TODO: gdy powstanie model Shop — pobrać i zmapować jego branding.
        return self::system();
    }

    /**
     * @return array<string, string|null>
     */
    public static function system(): array
    {
        return [
            'name' => config('app.name'),
            'glyph' => '◐',             // znak marki, gdy brak pliku logo
            'logo_url' => null,          // docelowo URL logo sklepu
            'gradient_from' => '#f59e0b', // amber-500
            'gradient_to' => '#f43f5e',   // rose-500
            'accent' => '#b45309',        // amber-700 — linki, akcenty
            'text' => '#1c1917',          // stone-900
            'muted' => '#78716c',         // stone-500
            'page_bg' => '#f5f5f4',       // stone-100
        ];
    }
}
