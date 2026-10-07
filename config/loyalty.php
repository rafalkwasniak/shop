<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Punkty za zakupy (program lojalnościowy sklepu)
    |--------------------------------------------------------------------------
    |
    | Sprzedawca ustawia JEDEN parametr ekonomiczny — „ile % wartości zakupu
    | wraca w punktach" — i wartość punktu z zakresu poniżej. Dwa wolne
    | kursy (zł za punkt przy zakupie i przy wydawaniu) pozwalały niechcący dać
    | 50% zwrotu; procent sprzedawca od razu przeliczy na marżę.
    |
    | `point_value_min` / `point_value_max` = zakres wartości jednego punktu
    | w złotych. Sprzedawca wpisuje dowolną kwotę z groszami (np. 0,05 zł) —
    | pod polem widzi na żywo, ile punktów dostanie klient (Rafał 07.10).
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

    'point_value_min' => 0.01,

    'point_value_max' => 10.00,

    'default_point_value' => 0.01,

    'validity_months' => 12,

];
