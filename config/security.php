<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Throttling logowania
    |--------------------------------------------------------------------------
    |
    | Po przekroczeniu liczby nieudanych prób logowania (liczonych per
    | e-mail + IP) konto jest tymczasowo blokowane. `decay_seconds` to czas
    | blokady liczony od pierwszej nieudanej próby w oknie.
    |
    */

    'login' => [
        'max_attempts' => 5,
        'decay_seconds' => 300, // 5 minut
    ],

];
