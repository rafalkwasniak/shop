<?php

namespace App\Enums;

/**
 * Działy panelu sprzedawcy, na które da się nadać dostęp pracownikowi.
 *
 * Granulacja jest CELOWO gruba i pokrywa się z pozycjami menu: sprzedawca
 * zaznacza to, co widzi, a nie listę tras. Uprawnienia per trasa dawałyby
 * ekran nie do wypełnienia bez czytania kodu i rozjeżdżałyby się przy każdym
 * nowym adresie.
 *
 * Czego tu NIE MA i mieć nie będzie — rzeczy zastrzeżone dla właściciela:
 * pakiet i jego zakup, usunięcie sklepu, zarządzanie pracownikami, integracje
 * (klucze Paynow, Fakturownia, InPost to cudze pieniądze i realne dokumenty)
 * oraz dane firmy. To nie jest checkbox, który ktoś mógłby zaznaczyć —
 * takich działów po prostu nie ma na tej liście.
 *
 * Rozbicie działu na poziomy (podgląd / obsługa / edycja) da się dołożyć bez
 * migracji, bo `permissions` na członkostwie to lista kluczy, a nie kolumny.
 * Dlatego `Orders` startuje jako JEDEN dział, mimo że osoba od rozliczeń i
 * osoba od wysyłki robią w nim co innego.
 */
enum PanelSection: string
{
    case Products = 'products';
    case Orders = 'orders';
    case Customers = 'customers';
    case Content = 'content';
    case Marketing = 'marketing';
    case Analytics = 'analytics';

    public function label(): string
    {
        return match ($this) {
            self::Products => 'Produkty',
            self::Orders => 'Zamówienia',
            self::Customers => 'Klienci',
            self::Content => 'Treści sklepu',
            self::Marketing => 'Marketing',
            self::Analytics => 'Analityka',
        };
    }

    /**
     * Zdanie pod nazwą działu na ekranie nadawania uprawnień. Sprzedawca ma
     * wiedzieć, co dokładnie oddaje, ZANIM kliknie — „Zamówienia" nie mówi mu,
     * że razem z nimi idą dane adresowe jego klientów.
     */
    public function description(): string
    {
        return match ($this) {
            self::Products => 'Dodawanie i edycja produktów, zdjęcia, ceny i stany magazynowe.',
            self::Orders => 'Podgląd zamówień, zmiana statusów, etykiety i nadawanie paczek. Obejmuje dane adresowe klientów.',
            self::Customers => 'Kartoteka kupujących: adresy e-mail, historia zakupów.',
            self::Content => 'Strony informacyjne i wygląd sklepu.',
            self::Marketing => 'Kody rabatowe i wiadomości do klientów.',
            self::Analytics => 'Odwiedziny, sprzedaż i bestsellery.',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
