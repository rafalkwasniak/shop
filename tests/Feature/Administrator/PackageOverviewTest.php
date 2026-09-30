<?php

namespace Tests\Feature\Administrator;

use App\Models\PackagePayment;
use App\Models\Shop;
use App\Models\User;
use App\Support\PackageRevenue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konsola admina — dział „Pakiety": przekrój pieniędzy platformy.
 *
 * Sedno tych testów to rozdzielenie dwóch rzeczy, które łatwo pomylić:
 * PRZYCHÓD (co realnie wpłynęło, z rejestru opłat) i WARTOŚĆ ROCZNA (ile są
 * warte biegnące abonamenty). Sklep z pakietem Pawilon nadanym ręcznie ma
 * wartość roczną, ale nie wniósł ani złotówki przychodu.
 */
class PackageOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_revenue_and_annual_value(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('booth')->create(['subscription_ends_at' => now()->addMonths(6)]);
        PackagePayment::factory()->for($shop)->create(['amount' => 750]);

        $this->actingAs($admin)
            ->get(route('administrator.packages.index'))
            ->assertOk()
            ->assertSee('750,00 zł')
            ->assertSee('Przychód — w tym roku')
            ->assertSee('Opłaty od 1 stycznia '.now()->year)
            ->assertSee('Rozkład po pakietach')
            ->assertSee('Stragan');
    }

    public function test_sidebar_shows_empty_register_until_money_arrives(): void
    {
        // Kolumna boczna trzyma to, co się ROBI (wpłaty, sprawy), lewa — dane.
        // Ten sam podział 8/4 co u Sprzedawców, więc wzrok wie, gdzie szukać.
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->package('booth')->create(['name' => 'Kwiaciarnia Zosia']);

        $this->actingAs($admin)
            ->get(route('administrator.packages.index'))
            ->assertOk()
            ->assertSee('Rejestr opłat jest jeszcze pusty')
            ->assertSee('Zarejestruj wpłatę')
            ->assertSee('Jak to czytać');

        PackagePayment::factory()->for($shop)->create(['amount' => 750]);

        $this->actingAs($admin)
            ->get(route('administrator.packages.index'))
            ->assertOk()
            ->assertDontSee('Rejestr opłat jest jeszcze pusty')
            ->assertSee('Kwiaciarnia Zosia')
            ->assertSee('750,00 zł');
    }

    public function test_calendar_year_revenue_stops_at_new_year(): void
    {
        // „W tym roku" to rok OBROTOWY (1 stycznia – 31 grudnia), nie ruchome
        // okno: wpłata z sylwestra należy do poprzedniego roku, choć w oknie
        // 12 miesięcy wciąż siedzi. Bez tego rozróżnienia nie da się powiedzieć,
        // jak zamknął się rok.
        $shop = Shop::factory()->package('booth')->create();
        PackagePayment::factory()->for($shop)->create(['amount' => 750, 'paid_at' => now()->startOfYear()]);
        PackagePayment::factory()->for($shop)->create(['amount' => 500, 'paid_at' => now()->subYear()->endOfYear()]);

        $revenue = PackageRevenue::revenue();

        $this->assertSame(750.0, $revenue['year']);
        $this->assertSame(1250.0, $revenue['last12m']);
        $this->assertSame(1250.0, $revenue['total']);
    }

    public function test_pending_payment_is_not_counted_as_revenue(): void
    {
        // Porzucony koszyk w bramce to nie pieniądze. Gdyby wchodził do sumy,
        // przychód rósłby od samych kliknięć „Kup".
        $shop = Shop::factory()->package('booth')->create();
        PackagePayment::factory()->pending()->for($shop)->create(['amount' => 750]);

        $this->assertSame(0.0, PackageRevenue::revenue()['total']);
        $this->assertSame(0, PackageRevenue::revenue()['count']);
    }

    public function test_manually_granted_package_does_not_create_revenue(): void
    {
        // Pakiet ustawiony z konsoli sklepów nie jest dowodem wpłaty — liczy się
        // do wartości rocznej, ale przychód zostaje zerowy.
        Shop::factory()->package('pavilion')->create(['subscription_ends_at' => now()->addYear()]);

        $this->assertSame(0.0, PackageRevenue::revenue()['total']);
        $this->assertSame(1500.0, PackageRevenue::subscriptions()['annualValue']);
    }

    public function test_comped_and_free_shops_do_not_count_as_paying(): void
    {
        // Trzy sklepy, z których żaden nie płaci: darmowy pakiet, dostęp
        // gratisowy i sklep po wygaśnięciu z minioną karencją.
        Shop::factory()->package('stall')->create();
        Shop::factory()->package('pavilion')->create(['comped' => true, 'subscription_ends_at' => null]);
        Shop::factory()->package('booth')->create(['subscription_ends_at' => now()->subMonth()]);

        $summary = PackageRevenue::subscriptions();

        $this->assertSame(3, $summary['shops']);
        $this->assertSame(0, $summary['paying']);
        $this->assertSame(1, $summary['comped']);
        $this->assertSame(0.0, $summary['annualValue']);
    }

    public function test_shop_awaiting_deletion_is_excluded_from_annual_value(): void
    {
        // Sklep w karencji na usunięcie jest już niewidoczny dla klientów —
        // liczenie go do portfela obiecywałoby pieniądze, których nie będzie.
        Shop::factory()->package('booth')->create([
            'subscription_ends_at' => now()->addYear(),
            'deletion_scheduled_at' => now()->addDays(7),
        ]);

        $summary = PackageRevenue::subscriptions();

        $this->assertSame(1, $summary['shops']);
        $this->assertSame(0, $summary['paying']);
        $this->assertSame(0.0, $summary['annualValue']);
    }

    public function test_custom_per_shop_price_wins_over_price_list(): void
    {
        // Cena indywidualna ([[plan-per-shop-custom-pricing]]) to realna umowa —
        // wartość roczna musi mówić o niej, nie o cenniku.
        Shop::factory()->package('pavilion')->create([
            'price_yearly' => 900,
            'subscription_ends_at' => now()->addYear(),
        ]);

        $this->assertSame(900.0, PackageRevenue::subscriptions()['annualValue']);
    }

    public function test_empty_register_explains_itself_instead_of_showing_bare_zero(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('administrator.packages.index'))
            ->assertOk()
            ->assertSee('Rejestr opłat jest jeszcze pusty');
    }

    public function test_package_outside_the_offer_is_hidden_until_a_shop_uses_it(): void
    {
        // „Sklep dedykowany" to preset wdrożenia u klienta na jego serwerze,
        // a nie pakiet Kramio. Pusty wiersz w rozkładzie mówiłby o platformie
        // nieprawdę — pokazujemy go dopiero, gdy jest czego pilnować.
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('administrator.packages.index'))
            ->assertOk()
            ->assertSee('Rozkład po pakietach')
            ->assertDontSee('Sklep dedykowany');

        Shop::factory()->package('dedicated')->create(['comped' => true]);

        $this->actingAs($admin)
            ->get(route('administrator.packages.index'))
            ->assertOk()
            ->assertSee('Sklep dedykowany');
    }

    public function test_hidden_package_row_does_not_break_the_totals(): void
    {
        // Wycięcie wiersza nie może wycinać sklepów z podsumowania: stopka
        // „Razem" liczy wszystkie sklepy, także te spoza oferty.
        Shop::factory()->package('booth')->create(['subscription_ends_at' => now()->addYear()]);
        Shop::factory()->package('dedicated')->create(['comped' => true]);

        $summary = PackageRevenue::subscriptions();

        $this->assertSame(2, $summary['shops']);
        $this->assertSame(
            2,
            array_sum(array_column($summary['packages'], 'shops')),
            'Suma sklepów z wierszy musi się zgadzać ze stopką tabeli.'
        );
    }

    public function test_seller_cannot_view_packages(): void
    {
        $seller = User::factory()->create();

        $this->actingAs($seller)
            ->get(route('administrator.packages.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('administrator.packages.index'))
            ->assertRedirect(route('login'));
    }
}
