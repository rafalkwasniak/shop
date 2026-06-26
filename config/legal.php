<?php

use App\Enums\LegalDocumentType;

return [

    /*
    |--------------------------------------------------------------------------
    | Dokumenty wymagające zgody
    |--------------------------------------------------------------------------
    |
    | Typy dokumentów prawnych, których aktualną wersję każdy użytkownik musi
    | zaakceptować — przy rejestracji oraz ponownie po zmianie (middleware
    | EnsureConsentsAreCurrent). Stała biznesowa: rozszerzenie listy = jeden
    | wpis tutaj, bez ruszania kodu.
    |
    */

    'required_types' => [
        LegalDocumentType::Terms,
        LegalDocumentType::Privacy,
    ],

];
