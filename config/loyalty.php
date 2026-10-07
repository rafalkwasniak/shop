<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Punkty za zakupy (program lojalnościowy sklepu)
    |--------------------------------------------------------------------------
    |
    | Sprzedawca ustawia JEDEN parametr ekonomiczny — „ile % wartości zakupu
    | wraca w punktach" — i wybiera wartość punktu z listy poniżej. Dwa wolne
    | kursy (zł za punkt przy zakupie i przy wydawaniu) pozwalały niechcący dać
    | 50% zwrotu; procent sprzedawca od razu przeliczy na marżę.
    |
    | `point_values` = dozwolone wartości jednego punktu w złotych. Lista, nie
    | dowolna liczba: klient ma rozumieć saldo („300 pkt = 3,00 zł").
    |
    | `validity_months` = domyślna ważność punktów, liczona od chwili, gdy
    | punkty stają się DOSTĘPNE (po karencji) — klient ma pełny okres na ich
    | wydanie, karencja go nie zjada.
    |
    | Karencja (po ilu dniach od realizacji punkty wpadają) ma domyślnie długość
    | okna zwrotu z `config/legal.php` (14 dni + zapas na dostawę), żeby punkty
    | za zwrócony towar nie zdążyły zostać wydane. Sprzedawca może ją wydłużyć
    | albo skrócić — `shops.loyalty_delay_days` NULL znaczy „jak okno zwrotu".
    |
    */

    'point_values' => [0.01, 0.10, 1.00],

    'default_point_value' => 0.01,

    'validity_months' => 12,

];
