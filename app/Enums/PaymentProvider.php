<?php

namespace App\Enums;

/**
 * Bramki płatności online (operatorzy). Sklep może mieć ich kilka naraz — wybór
 * sprzedawcy (decyzja Rafała 2026-11-23) — a kupujący wybiera bramkę w kasie.
 * Zamówienie zapamiętuje, którą bramką płaci (`orders.payment_provider`).
 *
 * To KATALOG, nie wspólny interfejs mechaniki płatności: każda bramka ma własną
 * usługę, webhook i dziennik, bo różnią się sposobem potwierdzania zapłaty na
 * tyle, że wspólna abstrakcja przeciekłaby w kodzie przyjmującym pieniądze.
 * Kolejna bramka = nowy case tutaj + jej usługa i webhook.
 */
enum PaymentProvider: string
{
    case Paynow = 'paynow';
    case Przelewy24 = 'przelewy24';

    /**
     * Nazwa operatora, którą widzi kupujący i sprzedawca.
     */
    public function label(): string
    {
        return match ($this) {
            self::Paynow => 'Paynow',
            self::Przelewy24 => 'Przelewy24',
        };
    }

    /**
     * Wiersz `shop_integrations`, w którym sklep trzyma klucze tej bramki i jej
     * włącznik. Paynow zajmuje historyczny typ `payments` (sprzed drugiej bramki).
     */
    public function integrationType(): IntegrationType
    {
        return match ($this) {
            self::Paynow => IntegrationType::Payments,
            self::Przelewy24 => IntegrationType::Przelewy24,
        };
    }
}
