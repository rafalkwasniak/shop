<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Seller = 'seller';

    /**
     * Czytelna nazwa roli (do UI).
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Seller => 'Sprzedawca',
        };
    }

    /**
     * Nazwa trasy pulpitu, na którą trafia użytkownik tej roli po zalogowaniu.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Admin => 'administrator.dashboard',
            self::Seller => 'seller.dashboard',
        };
    }
}
