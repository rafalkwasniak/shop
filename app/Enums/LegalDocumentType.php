<?php

namespace App\Enums;

/**
 * Typy dokumentów prawnych wymagających zgody użytkownika. Wartość enumu to
 * kolumna `type` w `legal_documents` (kod po angielsku). Slug i etykieta są po
 * polsku — warstwa widoczna (URL/UI).
 */
enum LegalDocumentType: string
{
    case Terms = 'terms';
    case Privacy = 'privacy';

    /**
     * Czytelna nazwa dokumentu (UI, treść zgody).
     */
    public function label(): string
    {
        return match ($this) {
            self::Terms => 'Regulamin',
            self::Privacy => 'Polityka Prywatności',
        };
    }

    /**
     * Segment URL publicznej strony dokumentu (po polsku).
     */
    public function slug(): string
    {
        return match ($this) {
            self::Terms => 'regulamin',
            self::Privacy => 'polityka-prywatnosci',
        };
    }

    /**
     * Nazwa trasy publicznej strony dokumentu (kod po angielsku).
     */
    public function routeName(): string
    {
        return match ($this) {
            self::Terms => 'legal.terms',
            self::Privacy => 'legal.privacy',
        };
    }
}
