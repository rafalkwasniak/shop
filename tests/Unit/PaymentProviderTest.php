<?php

namespace Tests\Unit;

use App\Enums\IntegrationType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Models\Order;
use PHPUnit\Framework\TestCase;

/**
 * Katalog bramek płatności. Pilnuje dwóch rzeczy, na których stoi model „kilka
 * bramek naraz": każda bramka ma własny wiersz integracji (unikat shop_id+type
 * nie pozwoli dwóm bramkom dzielić jednego), a Paynow zostaje na historycznym
 * typie `payments` — inaczej istniejące sklepy straciłyby swoje klucze.
 */
class PaymentProviderTest extends TestCase
{
    public function test_every_provider_has_its_own_integration_row(): void
    {
        $types = array_map(fn (PaymentProvider $p) => $p->integrationType(), PaymentProvider::cases());

        $this->assertSame(count($types), count(array_unique(array_map(fn ($t) => $t->value, $types))));
    }

    public function test_paynow_keeps_the_historic_integration_type(): void
    {
        $this->assertSame(IntegrationType::Payments, PaymentProvider::Paynow->integrationType());
    }

    public function test_przelewy24_has_its_own_integration_type(): void
    {
        $this->assertSame(IntegrationType::Przelewy24, PaymentProvider::Przelewy24->integrationType());
    }

    public function test_order_label_names_the_gateway_only_for_online_payments(): void
    {
        $order = new Order;

        $order->payment_method = PaymentMethod::Online;
        $order->payment_provider = PaymentProvider::Przelewy24;
        $this->assertSame('Płatność online (Przelewy24)', $order->paymentMethodLabel());

        // Zamówienie online bez zapamiętanej bramki — sama metoda, bez nawiasu.
        $order->payment_provider = null;
        $this->assertSame('Płatność online', $order->paymentMethodLabel());

        $order->payment_method = PaymentMethod::BankTransfer;
        $this->assertSame('Przelew na konto', $order->paymentMethodLabel());
    }

    public function test_every_provider_has_a_human_label(): void
    {
        foreach (PaymentProvider::cases() as $provider) {
            $this->assertNotSame('', $provider->label());
        }
    }
}
