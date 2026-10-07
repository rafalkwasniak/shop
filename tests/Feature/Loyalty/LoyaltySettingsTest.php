<?php

namespace Tests\Feature\Loyalty;

use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use App\Services\LoyaltyLedger;
use App\Support\LoyaltyRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Krok A punktów: sekcja w Ustawieniach (tylko z uprawnieniem, bez włącznika),
 * przeliczenie sald za potwierdzeniem i strona „Zasady punktów", której nie
 * da się usunąć, dopóki punkty są włączone.
 */
class LoyaltySettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Shop} */
    private function seller(bool $loyalty = true, array $settings = []): array
    {
        $seller = User::factory()->consented()->create();
        $factory = Shop::factory();
        $shop = ($loyalty ? $factory->withLoyalty($settings) : $factory)->create(['owner_id' => $seller->id]);

        return [$seller, $shop];
    }

    /** @return array<string, string> */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'loyalty_earn_percent' => '3,5',
            'loyalty_point_value' => '0.01',
            'loyalty_delay_days' => '30',
            'loyalty_validity_months' => '24',
            'loyalty_max_redeem_percent' => '40',
            'loyalty_min_redeem_points' => '200',
        ], $overrides);
    }

    public function test_section_is_shown_only_with_the_entitlement_and_has_no_switch(): void
    {
        [$seller] = $this->seller();

        $this->actingAs($seller)->get(route('seller.settings.edit'))
            ->assertOk()
            ->assertSee('Punkty za zakupy')
            ->assertSee('Punkty są włączone.')
            ->assertDontSee('name="loyalty_enabled"', false);

        [$other] = $this->seller(loyalty: false);

        $this->actingAs($other)->get(route('seller.settings.edit'))
            ->assertOk()
            ->assertDontSee('Punkty za zakupy');
    }

    public function test_shop_without_entitlement_cannot_save_loyalty_settings(): void
    {
        [$seller] = $this->seller(loyalty: false);

        $this->actingAs($seller)->post(route('seller.settings.loyalty'), $this->form())->assertForbidden();
    }

    public function test_settings_are_saved_and_rules_page_is_created(): void
    {
        [$seller, $shop] = $this->seller();

        $this->actingAs($seller)->post(route('seller.settings.loyalty'), $this->form())
            ->assertRedirect(route('seller.settings.edit').'#punkty');

        $shop->refresh();
        $this->assertSame('3.50', $shop->loyalty_earn_percent);
        $this->assertSame(30, $shop->loyalty_delay_days);
        $this->assertSame(24, $shop->loyalty_validity_months);
        $this->assertSame(40, $shop->loyalty_max_redeem_percent);
        $this->assertSame(200, $shop->loyalty_min_redeem_points);

        $page = $shop->pages()->where('system_key', Page::LOYALTY_RULES)->sole();
        $this->assertSame('Zasady punktów', $page->title);
        $this->assertTrue($page->published);
        $this->assertStringContainsString('3,5%', $page->content);
        $this->assertStringContainsString('24 miesiące', $page->content);
        $this->assertStringContainsString('najwyżej 40%', $page->content);
    }

    public function test_empty_optional_fields_mean_defaults(): void
    {
        [$seller, $shop] = $this->seller();

        $this->actingAs($seller)->post(route('seller.settings.loyalty'), $this->form([
            'loyalty_delay_days' => '',
            'loyalty_validity_months' => '',
            'loyalty_max_redeem_percent' => '',
            'loyalty_min_redeem_points' => '',
        ]))->assertSessionHasNoErrors();

        $shop->refresh();
        $this->assertNull($shop->loyalty_validity_months);
        $this->assertSame(20, $shop->loyaltyDelayDays());
        $this->assertStringContainsString('nie mają terminu ważności', LoyaltyRules::render($shop));
    }

    public function test_changing_point_value_with_balances_needs_confirmation_and_revalues(): void
    {
        [$seller, $shop] = $this->seller();
        app(LoyaltyLedger::class)->adjust($shop, 'klient@example.com', 300);

        $this->actingAs($seller)
            ->post(route('seller.settings.loyalty'), $this->form(['loyalty_point_value' => '0.10']))
            ->assertSessionHasErrors('confirm_revaluation');
        $this->assertSame('0.01', $shop->fresh()->loyalty_point_value);

        $this->actingAs($seller)
            ->post(route('seller.settings.loyalty'), $this->form(['loyalty_point_value' => '0.10', 'confirm_revaluation' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertSame('0.10', $shop->fresh()->loyalty_point_value);
        $this->assertSame(30, app(LoyaltyLedger::class)->balance($shop, 'klient@example.com'));
    }

    public function test_point_value_outside_the_list_is_refused(): void
    {
        [$seller] = $this->seller();

        $this->actingAs($seller)
            ->post(route('seller.settings.loyalty'), $this->form(['loyalty_point_value' => '0.05']))
            ->assertSessionHasErrors('loyalty_point_value');
    }

    public function test_rules_page_cannot_be_deleted_while_points_are_on(): void
    {
        [$seller, $shop] = $this->seller();
        $page = LoyaltyRules::ensurePage($shop);

        $this->actingAs($seller)->post(route('seller.pages.destroy', $page))->assertForbidden();

        $shop->update(['loyalty_enabled' => false]);

        $this->actingAs($seller)->post(route('seller.pages.destroy', $page))
            ->assertRedirect(route('seller.pages.index'));
        $this->assertModelMissing($page);
    }

    public function test_rules_page_keeps_its_title_and_visibility_on_save(): void
    {
        [$seller, $shop] = $this->seller();
        $page = LoyaltyRules::ensurePage($shop);

        $this->actingAs($seller)->post(route('seller.pages.update', $page), [
            'title' => 'Coś innego',
            'content' => '<div>Moje zasady</div>',
        ])->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertSame('Zasady punktów', $page->title);
        $this->assertTrue($page->published);
        $this->assertStringContainsString('Moje zasady', $page->content);
    }

    public function test_default_content_is_put_into_the_editor_without_saving(): void
    {
        [$seller, $shop] = $this->seller();
        $page = LoyaltyRules::ensurePage($shop);
        $page->update(['content' => '<div>Stara treść</div>']);

        $this->actingAs($seller)->post(route('seller.pages.loyalty.insert', $page))
            ->assertRedirect(route('seller.pages.edit', $page))
            ->assertSessionHasInput('content');

        $this->assertSame('<div>Stara treść</div>', $page->fresh()->content);
    }

    public function test_ensure_page_is_idempotent(): void
    {
        [, $shop] = $this->seller();

        $this->assertSame(LoyaltyRules::ensurePage($shop)->id, LoyaltyRules::ensurePage($shop)->id);
        $this->assertSame(1, $shop->pages()->where('system_key', Page::LOYALTY_RULES)->count());
    }

    public function test_month_words_follow_polish_grammar(): void
    {
        $this->assertSame('1 miesiąc', LoyaltyRules::months(1));
        $this->assertSame('3 miesiące', LoyaltyRules::months(3));
        $this->assertSame('12 miesięcy', LoyaltyRules::months(12));
        $this->assertSame('22 miesiące', LoyaltyRules::months(22));
        $this->assertSame('5%', LoyaltyRules::percent(5));
        $this->assertSame('2,5%', LoyaltyRules::percent(2.5));
    }

    public function test_engine_awards_nothing_without_the_entitlement(): void
    {
        [, $shop] = $this->seller(loyalty: false);
        $shop->update(['loyalty_enabled' => true, 'loyalty_earn_percent' => 5]);

        $this->assertFalse($shop->fresh()->loyaltyActive());
    }
}
