<?php

namespace App\Enums;

use App\Support\OrderFlow;

/**
 * Metody płatności. Przelew tradycyjny na konto, płatność przy odbiorze oraz
 * płatność online przez operatora (Paynow/mBank). Metoda jest świadomie
 * operator-agnostyczna: „online" to jeden sposób płatności, a KTÓRA bramka i
 * czyimi kluczami — to szczegół integracji w `shop_integrations`, nie enuma.
 */
enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case PayOnPickup = 'pay_on_pickup';
    case Online = 'online';
    case CashOnDelivery = 'cash_on_delivery';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Przelew na konto',
            self::PayOnPickup => 'Płatność przy odbiorze',
            // Bez nazwy operatora: sklep może mieć kilka bramek. Nazwę bramki
            // dokleja zamówienie (`Order::paymentMethodLabel()`) i kasa.
            self::Online => 'Płatność online',
            self::CashOnDelivery => 'Płatność za pobraniem',
        };
    }

    /**
     * Czy klient WYBIERA tę metodę w kasie. Pobranie — nie: wynika z metody
     * dostawy ({@see DeliveryMethod::isCashOnDelivery()}), więc pokazywanie go
     * jako osobnej opcji kazałoby klientowi zaznaczyć drugi raz to, co już
     * zaznaczył. Metoda istnieje na zamówieniu, bo ścieżka statusów
     * ({@see OrderFlow}) i maile pytają o płatność, nie o dostawę.
     */
    public function isChosenByCustomer(): bool
    {
        return $this !== self::CashOnDelivery;
    }

    /**
     * Czy pieniądze mają wpłynąć PRZED wydaniem towaru. To rozstrzyga o ścieżce
     * statusów (`OrderFlow`): przedpłata zaczyna od „Oczekuje na płatność" i ma
     * krok „Opłacone", płatność przy odbiorze nie ma czego potwierdzać. Płatności
     * online (operator) dojdą jako przedpłata z auto-przejściem z webhooka.
     */
    public function isPrepaid(): bool
    {
        return match ($this) {
            self::BankTransfer => true,
            self::PayOnPickup => false,
            // Online = przedpłata: reużywa ścieżki „Oczekuje na płatność → Opłacone
            // → …", ale do „Opłacone" przenosi webhook operatora, nie sprzedawca.
            self::Online => true,
            // Pobranie: pieniądze inkasuje InPost przy wydaniu paczki, więc
            // ścieżka jest ta sama co przy płatności przy odbiorze — bez kroku
            // „Opłacone". Fakt zapłaty niesie `delivered_at` (bez zapłaty klient
            // paczki nie odbierze), więc osobny status byłby mailem do kogoś,
            // kto trzyma już przesyłkę w ręku (decyzja Rafała 17.08).
            self::CashOnDelivery => false,
        };
    }
}
