<?php

namespace Tests\Feature\Seller;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ekran „Mój pakiet" w panelu sprzedawcy: co mam wykupione, do kiedy, ile
 * zużyłem z limitów i co dostanę w wyższym pakiecie. Dotąd sprzedawca nie
 * widział nawet nazwy swojego pakietu ani terminu jego końca.
 */
class PackageScreenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWith(string $package, array $attributes = []): array
    {
        $seller = User::factory()->consented()->create();

        $shop = Shop::factory()->withInvoiceData()->create([
            'owner_id' => $seller->id,
            'package' => $package,
            'entitlements' => config("shop.packages.{$package}.entitlements"),
            'price_yearly' => config("shop.packages.{$package}.price_yearly"),
            ...$attributes,
        ]);

        return [$seller, $shop];
    }

    public function test_free_shop_sees_its_package_and_upgrade_paths(): void
    {
        [$seller] = $this->sellerWith('stall');

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Kram')
            ->assertSee('Za darmo, bez limitu czasu')
            // Wyżej są dwa pakiety — pokazujemy oba z tym, co dochodzi.
            ->assertSee('Co dostaniesz wyżej')
            ->assertSee('Stragan')
            ->assertSee('Pawilon');
    }

    public function test_paid_shop_sees_the_amount_and_the_due_date(): void
    {
        [$seller] = $this->sellerWith('booth', ['subscription_ends_at' => now()->addMonths(6)]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Stragan')
            ->assertSee('750,00')
            ->assertSee('Opłacony do')
            ->assertSee(now()->addMonths(6)->format('d.m.Y'))
            ->assertSee('Aktywny');
    }

    public function test_top_package_has_nothing_to_upsell(): void
    {
        [$seller] = $this->sellerWith('pavilion', ['subscription_ends_at' => now()->addYear()]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Pawilon')
            ->assertDontSee('Co dostaniesz wyżej');
    }

    public function test_expired_shop_is_told_what_changed_and_that_it_comes_back(): void
    {
        // Poza karencją, więc funkcje faktycznie zgasły.
        [$seller] = $this->sellerWith('pavilion', ['subscription_ends_at' => now()->subDays(30)]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            // Stan czyta się jako Kram (decyzja Rafała), a co wygasło mówi plakietka.
            ->assertSee('Pakiet Pawilon wygasł')
            ->assertSee('Kram')
            // Ton bez straszenia: sklep działa, a po opłacie wraca stan sprzed.
            ->assertSee('Sklep i zamówienia działają dalej');
    }

    public function test_shop_just_past_the_deadline_is_in_grace_with_full_features(): void
    {
        // Abonament roczny płacony przelewem znaczy, że spóźnienie o dzień jest
        // normalne — funkcje jeszcze działają, ale ekran mówi o tym wprost.
        [$seller, $shop] = $this->sellerWith('pavilion', ['subscription_ends_at' => now()->subDay()]);

        $this->assertTrue($shop->inSubscriptionGrace());
        $this->assertTrue($shop->entitlement('bulk_mail'), 'w karencji funkcje działają');

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('czeka na opłatę')
            ->assertSee('Termin minął')
            // Karencja ma WŁASNY kolor: nie amber (to „zbliża się termin") i nie
            // róż (to „wygasło") — trzeci stan, inna akcja.
            ->assertSee('border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900', escape: false)
            // Data wyłączenia podana wprost, żeby nie było niespodzianki.
            ->assertSee(now()->subDay()->addDays((int) config('shop.subscription.grace_days'))->format('d.m.Y'))
            ->assertDontSee('wygasł');
    }

    public function test_expiring_soon_shows_a_reminder(): void
    {
        [$seller] = $this->sellerWith('booth', ['subscription_ends_at' => now()->addDays(9)]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Abonament kończy się za 9 dni');
    }

    public function test_the_last_week_turns_the_reminder_red(): void
    {
        // Żółty przez cały miesiąc przestaje cokolwiek znaczyć — ostatni tydzień
        // musi wyglądać inaczej.
        [$seller] = $this->sellerWith('booth', ['subscription_ends_at' => now()->addDays(5)]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Abonament kończy się za 5 dni')
            ->assertSee('bg-rose-50 text-rose-900', escape: false)
            ->assertSee('dni karencji');
    }

    public function test_a_month_ahead_the_reminder_is_still_calm(): void
    {
        [$seller] = $this->sellerWith('booth', ['subscription_ends_at' => now()->addDays(20)]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Abonament kończy się za 20 dni')
            ->assertSee('bg-amber-50 text-amber-900', escape: false);
    }

    public function test_the_top_package_can_be_renewed_instead_of_upgraded(): void
    {
        // Dotąd Pawilon dostawał tylko „napisz do nas" — płatność istniała, ale
        // nie dla przedłużenia. Teraz kupuje je z ekranu.
        [$seller] = $this->sellerWith('pavilion', ['subscription_ends_at' => now()->addMonths(6)]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Przedłużenie')
            ->assertSee('Przedłuż o rok — 1 500,00 zł')
            // Rok dokleja się do terminu, a nie liczy od dziś.
            ->assertSee('Opłacone do '.now()->addMonths(6)->addYear()->format('d.m.Y'))
            ->assertDontSee('Napisz do nas, żeby przedłużyć');
    }

    public function test_the_expiry_reminder_carries_the_renewal_button(): void
    {
        // Blisko terminu przycisk jest TAM, gdzie sprzedawca patrzy — w boksie
        // z przypomnieniem. Osobna karta „Przedłużenie" wtedy nie dubluje akcji.
        [$seller] = $this->sellerWith('booth', ['subscription_ends_at' => now()->addDays(5)]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Przedłuż o rok — 750,00 zł')
            ->assertDontSee('Przedłużenie</h2>', escape: false);
    }

    public function test_grace_and_expired_shops_are_offered_the_renewal_too(): void
    {
        foreach ([now()->subDay(), now()->subDays(30)] as $endsAt) {
            [$seller] = $this->sellerWith('pavilion', ['subscription_ends_at' => $endsAt]);

            $this->actingAs($seller)->get(route('seller.package.show'))
                ->assertOk()
                ->assertSee('Przedłuż o rok — 1 500,00 zł');
        }
    }

    public function test_free_shop_is_not_offered_a_renewal(): void
    {
        // Kram nie ma czego przedłużać — dla niego drogą wyjścia jest zakup wyżej.
        [$seller] = $this->sellerWith('stall');

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertDontSee('Przedłuż o rok');
    }

    public function test_reminder_stays_quiet_when_the_date_is_far_away(): void
    {
        [$seller] = $this->sellerWith('booth', ['subscription_ends_at' => now()->addMonths(6)]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertDontSee('Abonament kończy się');
    }

    public function test_comped_shop_is_marked_as_free_forever(): void
    {
        [$seller] = $this->sellerWith('pavilion', ['comped' => true, 'subscription_ends_at' => now()->subYear()]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Dostęp bezpłatny')
            ->assertSee('bezterminowo')
            ->assertDontSee('Abonament wygasł');
    }

    public function test_usage_counters_show_products_and_ai(): void
    {
        [$seller, $shop] = $this->sellerWith('stall');
        Product::factory()->count(3)->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Wykorzystanie')
            ->assertSee('3 / 60')      // produkty
            ->assertSee('0 / 100');    // zadania AI w Kramie
    }

    public function test_manual_grant_shows_up_in_the_feature_list(): void
    {
        // Stragan z korespondencją seryjną nadaną poza pakietem — lista musi
        // czytać uprawnienia SKLEPU, nie preset pakietu z configu.
        [$seller] = $this->sellerWith('booth', [
            'entitlements' => array_merge(config('shop.packages.booth.entitlements'), ['bulk_mail' => true]),
            'subscription_ends_at' => now()->addYear(),
        ]);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Wiadomości do klientów');
    }

    public function test_change_box_matches_the_purchase_configuration(): void
    {
        [$seller] = $this->sellerWith('booth', ['subscription_ends_at' => now()->addYear()]);

        // Z kluczami platformy (są w .env) box kieruje na przyciski „Kup"…
        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Zmiana pakietu')
            ->assertSee('Kupisz od razu');

        // …a bez nich wraca ścieżka kontaktowa — żadnych martwych przycisków.
        config(['services.paynow.platform.api_key' => null]);
        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('mailto:', false)
            ->assertDontSee('Kupisz od razu');
    }
}
