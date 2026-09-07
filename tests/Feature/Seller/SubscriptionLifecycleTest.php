<?php

namespace Tests\Feature\Seller;

use App\Models\EmailMessage;
use App\Models\Product;
use App\Models\Shop;
use App\Models\SubscriptionNotice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Codzienny przegląd abonamentów: przypomnienia 14/7/1 dni przed terminem,
 * karencja, a po niej zamek produktów i mail o tym, co się stało.
 *
 * Sedno: komenda chodzi CODZIENNIE, więc wszystko musi być idempotentne —
 * ani drugiego maila, ani drugiego zamka.
 */
class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Limit produktów pakietu darmowego — czytany z configu, nie przybity.
     *
     * Po wygaśnięciu abonamentu sklep spada na uprawnienia Krama, więc to ta
     * liczba rozstrzyga, ile produktów zostaje na witrynie. Wpisana na sztywno
     * zamieniała każdą zmianę cennika w serię czerwonych testów o zejściu z
     * pakietu — a te testy badają MECHANIZM zamka, nie wysokość limitu.
     */
    private function freeLimit(): int
    {
        return (int) config('shop.packages.'.config('shop.default_package').'.entitlements.max_products');
    }

    private function paidShop(array $attributes = []): Shop
    {
        $seller = User::factory()->consented()->create();

        return Shop::factory()->create([
            'owner_id' => $seller->id,
            'package' => 'pavilion',
            'entitlements' => config('shop.packages.pavilion.entitlements'),
            'price_yearly' => 1500,
            ...$attributes,
        ]);
    }

    private function check(): void
    {
        $this->artisan('subscriptions:check')->assertSuccessful();
    }

    public function test_reminder_goes_out_at_each_threshold(): void
    {
        foreach ([14, 7, 1] as $days) {
            $shop = $this->paidShop(['subscription_ends_at' => now()->addDays($days)]);

            $this->check();

            $this->assertDatabaseHas('subscription_notices', [
                'shop_id' => $shop->id,
                'kind' => 'reminder_'.$days,
            ]);
        }

        $this->assertSame(3, EmailMessage::count());
    }

    public function test_reminder_names_the_date_not_the_countdown(): void
    {
        // Jedna treść dla wszystkich progów (decyzja Rafała) — dlatego mail mówi
        // datę, a nie „za 7 dni". Trzy spójne wiadomości zamiast trzech różnych.
        $shop = $this->paidShop(['subscription_ends_at' => now()->addDays(7)]);

        $this->check();

        $mail = EmailMessage::firstOrFail();
        $this->assertStringContainsString($shop->subscription_ends_at->format('d.m.Y'), $mail->subject);
        $this->assertStringNotContainsString('za 7 dni', json_encode($mail->intro_lines, JSON_UNESCAPED_UNICODE));
        // Karencja wymieniona wprost, żeby spóźniony przelew nie budził paniki.
        $this->assertStringContainsString('na opłatę', json_encode($mail->intro_lines, JSON_UNESCAPED_UNICODE));
    }

    public function test_the_same_reminder_is_not_sent_twice(): void
    {
        $this->paidShop(['subscription_ends_at' => now()->addDays(7)]);

        $this->check();
        $this->check();
        $this->check();

        $this->assertSame(1, EmailMessage::count());
    }

    public function test_only_the_most_urgent_threshold_fires_when_the_cron_was_down(): void
    {
        // Cron nie chodził przez dwa tygodnie: nadrabianie nie może wysłać trzech
        // maili naraz — zostaje ten najbardziej naglący.
        $this->paidShop(['subscription_ends_at' => now()->addDay()]);

        $this->check();

        $this->assertSame(1, EmailMessage::count());
        $this->assertDatabaseHas('subscription_notices', ['kind' => 'reminder_1']);
        $this->assertDatabaseMissing('subscription_notices', ['kind' => 'reminder_14']);
    }

    public function test_renewal_lets_the_reminders_start_over(): void
    {
        $shop = $this->paidShop(['subscription_ends_at' => now()->addDays(7)]);
        $this->check();

        // Odnowienie = nowy termin, więc przypomnienia mają ruszyć od nowa.
        $shop->forceFill(['subscription_ends_at' => now()->addYear()->addDays(7)])->save();
        $this->travel(1)->years();
        $this->check();

        $this->assertSame(2, EmailMessage::count());
        $this->assertSame(2, SubscriptionNotice::where('kind', 'reminder_7')->count());
    }

    public function test_nothing_happens_when_the_date_is_far_away(): void
    {
        $this->paidShop(['subscription_ends_at' => now()->addMonths(6)]);

        $this->check();

        $this->assertSame(0, EmailMessage::count());
        $this->assertDatabaseCount('subscription_notices', 0);
    }

    public function test_grace_period_holds_the_lock_back(): void
    {
        $shop = $this->paidShop(['subscription_ends_at' => now()->subDay()]);
        $count = $this->freeLimit() + 6;
        Product::factory()->count($count)->create(['shop_id' => $shop->id, 'is_active' => true]);

        $this->check();

        // Termin minął, ale karencja trwa: nic nie gaśnie, nic nie znika.
        $this->assertDatabaseMissing('subscription_notices', ['kind' => 'locked']);
        $this->assertSame($count, $shop->products()->where('is_active', true)->count());
    }

    public function test_lock_hides_the_excess_and_explains_it_in_a_mail(): void
    {
        $grace = (int) config('shop.subscription.grace_days');
        $shop = $this->paidShop(['subscription_ends_at' => now()->subDays($grace + 1)]);
        Product::factory()->count($this->freeLimit() + 6)->create(['shop_id' => $shop->id, 'is_active' => true]);

        $this->check();

        // Sześć produktów ponad darmowy limit schodzi z witryny.
        $this->assertSame($this->freeLimit(), $shop->products()->where('is_active', true)->count());
        $this->assertSame(6, $shop->products()->whereNotNull('auto_hidden_at')->count());

        $mail = EmailMessage::where('subject', 'like', '%wygasł%')->firstOrFail();
        $body = json_encode($mail->intro_lines, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('6 produktów zostało ukrytych', $body);
        $this->assertStringContainsString('Nic nie zostało usunięte', $body);
        // Lista „co wróci po opłacie" musi opisywać ZAKUP, nie stan po wygaśnięciu.
        $this->assertStringContainsString('Wiadomości do klientów', $body);
    }

    public function test_lock_and_its_mail_happen_only_once(): void
    {
        $grace = (int) config('shop.subscription.grace_days');
        $shop = $this->paidShop(['subscription_ends_at' => now()->subDays($grace + 1)]);
        Product::factory()->count($this->freeLimit() + 2)->create(['shop_id' => $shop->id, 'is_active' => true]);

        $this->check();
        $this->check();

        $this->assertSame(1, EmailMessage::where('subject', 'like', '%wygasł%')->count());
        $this->assertSame(2, $shop->products()->whereNotNull('auto_hidden_at')->count());
    }

    public function test_free_and_comped_shops_are_skipped(): void
    {
        Shop::factory()->create([
            'owner_id' => User::factory()->consented()->create()->id,
            'package' => 'stall',
            'subscription_ends_at' => now()->addDays(7),
        ]);
        $this->paidShop(['comped' => true, 'subscription_ends_at' => now()->addDays(7)]);

        $this->check();

        $this->assertSame(0, EmailMessage::count());
    }

    public function test_paying_after_the_lock_brings_the_products_back(): void
    {
        $grace = (int) config('shop.subscription.grace_days');
        $shop = $this->paidShop(['subscription_ends_at' => now()->subDays($grace + 1)]);
        Product::factory()->count($this->freeLimit() + 6)->create(['shop_id' => $shop->id, 'is_active' => true]);

        $this->check();
        $this->assertSame(6, $shop->products()->whereNotNull('auto_hidden_at')->count());

        // Odnowienie to zmiana daty — a przywrócenie robi zamek, tak samo jak po
        // płatności online.
        $shop->forceFill(['subscription_ends_at' => now()->addYear()])->save();
        app(\App\Services\ProductLimitLock::class)->restore($shop->fresh());

        $this->assertSame($this->freeLimit() + 6, $shop->products()->where('is_active', true)->count());
        $this->assertSame(0, $shop->products()->whereNotNull('auto_hidden_at')->count());
    }
}
