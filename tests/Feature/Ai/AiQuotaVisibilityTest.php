<?php

namespace Tests\Feature\Ai;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\AiQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sprzedawca widzi, ile użyć AI mu zostało — PRZED kliknięciem, nie dopiero po
 * wyczerpaniu limitu. Uzupełnianie sklepu to moment największego zużycia, więc
 * odbicie się od komunikatu w połowie pracy byłoby najgorszym możliwym momentem
 * na tę informację.
 */
class AiQuotaVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithLimit(int $limit, int $used = 0): array
    {
        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);
        $shop->forceFill(['entitlements' => array_merge($shop->entitlements ?? [], [
            'ai_weekly_limit' => $limit,
        ])])->save();

        $quota = app(AiQuota::class);
        foreach (range(1, $used) as $i) {
            $quota->consume($shop->fresh());
        }

        return [$seller, $shop->fresh()];
    }

    public function test_product_form_shows_what_is_left(): void
    {
        [$seller, $shop] = $this->sellerWithLimit(100, used: 7);
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)->get(route('seller.products.edit', $product))
            ->assertOk()
            ->assertSee('93')
            ->assertSee('z 100 użyć AI w tym tygodniu', escape: false);
    }

    public function test_exhausted_limit_says_when_it_returns(): void
    {
        [$seller, $shop] = $this->sellerWithLimit(2, used: 2);
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        $response = $this->actingAs($seller)->get(route('seller.products.edit', $product))->assertOk();

        $response->assertSee('Limit AI na ten tydzień wykorzystany', escape: false);
        $response->assertSee('w wyższym pakiecie jest większy', escape: false);
        // Klikanie w martwy przycisk to zaproszenie do frustracji.
        $response->assertSee('disabled', escape: false);
    }

    public function test_shop_form_shows_the_counter_too(): void
    {
        [$seller] = $this->sellerWithLimit(400, used: 1);

        $this->actingAs($seller)->get(route('seller.shop.edit'))
            ->assertOk()
            ->assertSee('399');
    }

    public function test_ai_buttons_show_when_the_key_is_set(): void
    {
        [$seller, $shop] = $this->sellerWithLimit(100);
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)->get(route('seller.products.edit', $product))
            ->assertOk()
            ->assertSee('data-ai-button', escape: false)
            ->assertSee('data-ai-generate', escape: false)
            ->assertSee('data-ai-quota', escape: false);
    }

    public function test_ai_buttons_disappear_without_the_key(): void
    {
        // Bez klucza kliknięcie kończyło się komunikatem „chwilowo niedostępna",
        // choć usługa nie zadziałałaby nigdy. Lepiej nie kusić przyciskiem.
        config(['ai.providers.deepseek.key' => '']);
        [$seller, $shop] = $this->sellerWithLimit(100);
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)->get(route('seller.products.edit', $product))
            ->assertOk()
            ->assertDontSee('data-ai-button', escape: false)
            ->assertDontSee('data-ai-generate', escape: false)
            ->assertDontSee('data-ai-quota', escape: false);

        $this->actingAs($seller)->get(route('seller.shop.edit'))
            ->assertOk()
            ->assertDontSee('data-ai-button', escape: false)
            ->assertDontSee('data-ai-generate', escape: false);
    }

    public function test_counter_is_read_once_per_request(): void
    {
        [$seller, $shop] = $this->sellerWithLimit(100, used: 3);
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        // Formularz produktu ma edytor z przyciskiem AI i box SEO z drugim —
        // licznik nie może przez to odpytywać bazy kilka razy na jedno wejście.
        DB::enableQueryLog();
        $this->actingAs($seller)->get(route('seller.products.edit', $product))->assertOk();
        $queries = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'ai_usages'))
            ->count();
        DB::disableQueryLog();

        $this->assertSame(1, $queries);
    }
}
