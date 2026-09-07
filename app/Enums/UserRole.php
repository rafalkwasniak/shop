<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Seller = 'seller';

    /**
     * Pracownik sklepu — osoba, którą sprzedawca dopuścił do swojego panelu w
     * wybranych działach. Osobna rola, a nie sprzedawca z pustym sklepem:
     * pracownik nigdy nie ma własnego sklepu, pakietu ani umowy z Kramio, więc
     * wszędzie, gdzie pytamy „czy to sprzedawca", odpowiedź ma brzmieć „nie".
     */
    case Employee = 'employee';

    /**
     * Czytelna nazwa roli (do UI).
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Seller => 'Sprzedawca',
            self::Employee => 'Pracownik',
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
            // Pracownik trafia tam, gdzie właściciel — pulpit sam pokazuje mu
            // tylko te działy, do których ma dostęp.
            self::Employee => 'seller.dashboard',
        };
    }
}
