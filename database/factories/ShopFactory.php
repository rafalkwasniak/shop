<?php

namespace Database\Factories;

use App\Enums\ShopStatus;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();
        $package = config('shop.default_package');

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'status' => ShopStatus::Draft,
            // Snapshot pakietu domyślnego — jak w produkcji (Shop::assignPackage).
            'package' => $package,
            'entitlements' => config("shop.packages.{$package}.entitlements"),
            'price_yearly' => config("shop.packages.{$package}.price_yearly"),
        ];
    }

    /**
     * Sklep aktywny (opublikowany).
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShopStatus::Active,
        ]);
    }

    /**
     * Sklep, w którym DA SIĘ dokończyć zakup: jest czym dostarczyć (odbiór
     * osobisty — jedyna metoda bez uprawnienia pakietu) i czym zapłacić
     * (płatność przy odbiorze). Bez tego storefront nie pokazuje „Do koszyka",
     * bo sklep bez dostawy i płatności jest wykazem oferty, nie sklepem —
     * patrz Shop::acceptsOrders().
     *
     * Odbiór wymaga KOMPLETNEGO adresu sklepu, więc state dokłada i adres.
     */
    public function sellable(): static
    {
        return $this->state(fn (array $attributes) => [
            'street' => 'Kwiatowa',
            'building_number' => '12',
            'postal_code' => '00-001',
            'city' => 'Warszawa',
            'province' => config('shop.provinces')[0],
            'pickup_enabled' => true,
            'pay_on_pickup_enabled' => true,
        ]);
    }

    /**
     * Sklep z danym pakietem (snapshot uprawnień z configu) — do testów tierów.
     */
    public function package(string $slug): static
    {
        return $this->state(fn (array $attributes) => [
            'package' => $slug,
            'entitlements' => config("shop.packages.{$slug}.entitlements"),
            'price_yearly' => config("shop.packages.{$slug}.price_yearly"),
        ]);
    }

    /**
     * Sklep z uprawnieniem do faktur (Fakturownia) — funkcja płatna (Stragan+).
     * Dokłada `invoices=true` do istniejącego snapshotu bez zmiany pakietu, więc
     * testy FV nie muszą znać tieru; izoluje samo uprawnienie.
     */
    public function withInvoicing(): static
    {
        return $this->state(fn (array $attributes) => [
            'entitlements' => array_merge(
                $attributes['entitlements'] ?? config('shop.packages.'.config('shop.default_package').'.entitlements'),
                ['invoices' => true],
            ),
        ]);
    }

    /**
     * Sklep z uprawnieniem do płatności online (Paynow) — funkcja płatna
     * (Stragan+). Dokłada `online_payments=true` do snapshotu bez zmiany pakietu.
     */
    public function withOnlinePayments(): static
    {
        return $this->state(fn (array $attributes) => [
            'entitlements' => array_merge(
                $attributes['entitlements'] ?? config('shop.packages.'.config('shop.default_package').'.entitlements'),
                ['online_payments' => true],
            ),
        ]);
    }

    /**
     * Sklep z uprawnieniem do wysyłki kurierem/paczkomatem (InPost) —
     * funkcja płatna (Stragan+). Dokłada `courier_shipping=true` bez zmiany pakietu.
     */
    public function withCourierShipping(): static
    {
        return $this->state(fn (array $attributes) => [
            'entitlements' => array_merge(
                $attributes['entitlements'] ?? config('shop.packages.'.config('shop.default_package').'.entitlements'),
                ['courier_shipping' => true],
            ),
        ]);
    }

    /**
     * Sklep z uprawnieniem do edycji zamówienia — funkcja TYLKO Pawilonu
     * (`order_editing`). Dokłada `order_editing=true` bez zmiany pakietu.
     */
    public function withOrderEditing(): static
    {
        return $this->state(fn (array $attributes) => [
            'entitlements' => array_merge(
                $attributes['entitlements'] ?? config('shop.packages.'.config('shop.default_package').'.entitlements'),
                ['order_editing' => true],
            ),
        ]);
    }

    /**
     * Sklep z uprawnieniem do zewnętrznej analityki Google (GA/GTM) — funkcja
     * płatna (Stragan+). Dokłada `ga_analytics=true` bez zmiany pakietu.
     */
    public function withGaAnalytics(): static
    {
        return $this->state(fn (array $attributes) => [
            'entitlements' => array_merge(
                $attributes['entitlements'] ?? config('shop.packages.'.config('shop.default_package').'.entitlements'),
                ['ga_analytics' => true],
            ),
        ]);
    }

    /**
     * Sklep z uprawnieniem do kodów rabatowych — funkcja TYLKO Pawilonu
     * (`discount_codes`). Dokłada `discount_codes=true` bez zmiany pakietu.
     */
    /**
     * Sklep naliczający punkty za zakupy: uprawnienie (dziś wyłączone we
     * wszystkich pakietach) + włącznik i domyślne ustawienia programu.
     *
     * @param  array<string, mixed>  $settings
     */
    public function withLoyalty(array $settings = []): static
    {
        return $this->state(fn (array $attributes) => array_merge([
            'entitlements' => array_merge(
                $attributes['entitlements'] ?? config('shop.packages.'.config('shop.default_package').'.entitlements'),
                ['loyalty_points' => true],
            ),
            'loyalty_enabled' => true,
            'loyalty_earn_percent' => 5,
            'loyalty_point_value' => 0.01,
            'loyalty_validity_months' => 12,
        ], $settings));
    }

    public function withDiscountCodes(): static
    {
        return $this->state(fn (array $attributes) => [
            'entitlements' => array_merge(
                $attributes['entitlements'] ?? config('shop.packages.'.config('shop.default_package').'.entitlements'),
                ['discount_codes' => true],
            ),
        ]);
    }

    /**
     * Sklep z danymi wystarczającymi do wystawienia faktury (nazwa firmy, NIP,
     * pełny adres) — warunek zakupu pakietu.
     */
    public function withInvoiceData(): static
    {
        return $this->state(fn (): array => [
            'company_name' => 'Kwiaciarnia Anna Kowalska',
            'nip' => '1234563218',
            'street' => 'Polna',
            'building_number' => '7',
            'postal_code' => '00-001',
            'city' => 'Warszawa',
            'province' => 'mazowieckie',
        ]);
    }

    /**
     * Sklep z adresem, ale BEZ danych firmowych — faktura imienna na właściciela.
     */
    public function withPersonalInvoiceData(): static
    {
        return $this->state(fn (): array => [
            'company_name' => null,
            'nip' => null,
            'street' => 'Polna',
            'building_number' => '7',
            'postal_code' => '00-001',
            'city' => 'Warszawa',
            'province' => 'mazowieckie',
        ]);
    }

    /**
     * Sklep z korespondencją seryjną (pakiet Pawilon).
     */
    public function withBulkMail(): static
    {
        return $this->state(fn (array $attributes) => [
            'entitlements' => array_merge(
                $attributes['entitlements'] ?? config('shop.packages.'.config('shop.default_package').'.entitlements'),
                ['bulk_mail' => true],
            ),
        ]);
    }
}
