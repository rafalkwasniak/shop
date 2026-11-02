<?php

namespace App\Support;

/**
 * Minimalne, bezpieczne formatowanie inline dla treści maili. Wspiera dokładnie
 * dwa zapisy z Markdowna: pogrubienie `**tekst**` i odnośnik `[tekst](adres)`.
 * Nic więcej — treść maili budujemy sami w kodzie, więc nie potrzebujemy
 * pełnego parsera.
 *
 * Kolejność jest kluczowa dla bezpieczeństwa: NAJPIERW escapujemy cały tekst
 * (nazwy produktów, notatki i dane firmowe pochodzą od użytkownika), DOPIERO
 * potem zamieniamy nasze własne znaczniki na HTML. Dzięki temu żadne dane
 * wejściowe nie mogą wstrzyknąć HTML — najwyżej pokażą się dosłowne `**`.
 *
 * Odnośniki przyjmują WYŁĄCZNIE adresy http(s) — `javascript:` i spółka nie
 * przejdą przez wzorzec, więc nawet adres z nieoczekiwanego źródła nie zamieni
 * się w wykonywalny link.
 */
class MailMarkup
{
    public static function inline(string $text): string
    {
        $escaped = e($text);

        // Odnośnik przed pogrubieniem: tekst linku też ma prawo być pogrubiony.
        $withLinks = preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
            fn (array $m) => '<a href="'.$m[2].'" style="color:inherit; text-decoration:underline;">'.$m[1].'</a>',
            $escaped
        );

        return preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $withLinks);
    }

    /**
     * Tekst wpisany przez OSOBĘ Z ZEWNĄTRZ (np. formularz kontaktowy), który
     * ma przejść przez `inline()` dosłownie — bez pogrubień i bez odnośników.
     *
     * Escapowanie w `inline()` chroni przed HTML-em, ale nie przed naszymi
     * własnymi znacznikami: `[Twoje zamówienie](https://obcy.adres)` wpisane
     * w formularz zamieniłoby się w mailu do sprzedawcy w klikalny link
     * z dowolnym podpisem. Wstawiamy znak zerowej szerokości w środek znaczników
     * — tekst wygląda identycznie, a wzorce przestają pasować.
     */
    public static function literal(string $text): string
    {
        return str_replace(['**', ']('], ["*\u{200B}*", "]\u{200B}("], $text);
    }
}
