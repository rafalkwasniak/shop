<?php

namespace App\Enums;

/**
 * Typy integracji per-sklep (kolumna `type` na shop_integrations). Na razie
 * jedna: Google Analytics / Tag Manager. Kolejne (PayU, InPost, …) dokładamy
 * jako nowe case'y — bez migracji („rozbudowa bez przebudowy").
 */
enum IntegrationType: string
{
    case GoogleAnalytics = 'google_analytics';
    case Invoicing = 'invoicing';
    // Historycznie jedyna bramka, dlatego ogólna nazwa — to wiersz PAYNOW.
    // Każda bramka ma własny wiersz (unikat shop_id+type), patrz PaymentProvider.
    case Payments = 'payments';
    case Przelewy24 = 'przelewy24';
    case SearchConsole = 'search_console';
    case Shipping = 'shipping';

    /**
     * Czytelna nazwa (do UI).
     */
    public function label(): string
    {
        return match ($this) {
            self::GoogleAnalytics => 'Google Analytics',
            self::Invoicing => 'Fakturownia',
            self::Payments => 'Płatności online (Paynow)',
            self::Przelewy24 => 'Płatności online (Przelewy24)',
            self::SearchConsole => 'Google Search Console',
            self::Shipping => 'Nadawanie przesyłek InPost',
        };
    }
}
