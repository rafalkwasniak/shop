<?php

namespace Tests\Feature\Seller;

use App\Models\EmailMessage;
use App\Models\PackagePayment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\PaynowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Zakup pakietu online: klucze PLATFORMY (nie integracja sklepu), migawka
 * wyceny w `package_payments`, webhook z podpisem platformy stosuje zakup
 * idempotentnie, a uprawnienia są lepkie (ręczne nadania przeżywają).
 */
class PackagePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-30 12:00:00'));
        config(['services.paynow.platform' => [
            'api_key' => 'platform-api',
            'signature_key' => 'platform-sign',
            'environment' => 'sandbox',
        ]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerOn(string $package, array $attributes = []): array
    {
        $seller = User::factory()->consented()->create();
        // `withInvoiceData`: bez danych do faktury zakup jest ZABLOKOWANY, więc
        // każdy test płatności musi mieć sklep gotowy do fakturowania.
        $shop = Shop::factory()->withInvoiceData()->create([
            'owner_id' => $seller->id,
            'package' => $package,
            'entitlements' => config("shop.packages.{$package}.entitlements"),
            'price_yearly' => config("shop.packages.{$package}.price_yearly"),
            ...$attributes,
        ]);

        return [$seller, $shop];
    }

    private function fakePaynow(): void
    {
        Http::fake(['*/v1/payments' => Http::response([
            'paymentId' => 'PAY-123',
            'redirectUrl' => 'https://sandbox.paynow.pl/pay/PAY-123',
            'status' => 'NEW',
        ])]);
    }

    private function webhook(string $paymentId, string $status, ?string $key = 'platform-sign'): TestResponse
    {
        $body = json_encode(['paymentId' => $paymentId, 'status' => $status], JSON_UNESCAPED_SLASHES);

        return $this->call('POST', '/platnosci/paynow/pakiety/webhook', [], [], [], [
            'HTTP_Signature' => $key !== null ? app(PaynowService::class)->sign($key, $body) : 'zly-podpis',
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    public function test_purchase_freezes_the_quote_and_redirects_to_the_gateway(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('booth', ['subscription_ends_at' => Carbon::parse('2026-09-30')]);

        $response = $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'pavilion']), ['immediate_start' => '1']);

        $response->assertRedirect('https://sandbox.paynow.pl/pay/PAY-123');

        $payment = $shop->packagePayments()->firstOrFail();
        $this->assertSame('pavilion', $payment->target_package);
        // Migawka wyceny: 1500 − (750 × 62/365 = 127,39...) → floor 1372.
        $this->assertSame('1372.00', $payment->amount);
        $this->assertSame(PackagePayment::STATUS_PENDING, $payment->status);
        $this->assertSame('PAY-123', $payment->payment_id);
        $this->assertSame('2027-07-30', $payment->new_ends_at->format('Y-m-d'));
    }

    /**
     * Bez wyraźnego żądania nie ruszamy płatności — art. 15 ust. 3 u.p.k.
     *
     * To NIE jest formalność: §9 ust. 2 Regulaminu pozwala nam przy odstąpieniu
     * zatrzymać zapłatę za wykorzystany okres, ale tylko wtedy, gdy żądanie
     * padło PRZED uruchomieniem pakietu. Gdyby ten test zaczął przechodzić bez
     * `immediate_start`, odstępujący sprzedawca odzyskiwałby całą wpłatę.
     */
    public function test_purchase_without_the_immediate_start_declaration_is_rejected(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('stall');

        $this->actingAs($seller)
            ->post(route('seller.package.purchase', ['package' => 'booth']))
            ->assertSessionHasErrors('immediate_start');

        $this->assertSame(0, $shop->packagePayments()->count());
        Http::assertNothingSent();
    }

    /**
     * Odbicie z JEDNEGO przycisku nie może zapalić czerwonego tekstu pod
     * wszystkimi zgodami na stronie.
     *
     * Worek błędów jest wspólny dla całej strony, a formularzy zakupu potrafi być
     * kilka naraz (przedłużenie + zejście niżej). Bez zawężenia po `form_package`
     * sprzedawca widział „zaznacz zgodę" pod pudełkiem, którego nie dotknął.
     */
    public function test_the_consent_error_shows_only_under_the_submitted_form(): void
    {
        $this->fakePaynow();
        // Pawilon 21 dni przed końcem: ekran pokazuje NARAZ przedłużenie i
        // zejście na Stragan, czyli dwie zgody obok siebie.
        [$seller, $shop] = $this->sellerOn('pavilion', ['subscription_ends_at' => Carbon::parse('2026-08-20')]);

        $screen = $this->actingAs($seller)->get(route('seller.package.show'));
        $this->assertSame(2, substr_count($screen->getContent(), 'name="immediate_start"'));

        // `from()` + `followingRedirects()` odtwarza to, co robi przeglądarka:
        // odbicie wraca NA TEN SAM ekran, a błąd i stare dane żyją w tym jednym
        // przeładowaniu. Osobne `get()` po `post()` już ich nie widzi.
        $content = $this->actingAs($seller)
            ->from(route('seller.package.show'))
            ->followingRedirects()
            ->post(route('seller.package.purchase', ['package' => 'booth']), ['form_package' => 'booth'])
            ->getContent();

        $this->assertSame(1, substr_count($content, 'Zaznacz zgodę na natychmiastowe uruchomienie'));
    }

    public function test_purchase_records_the_proof_of_the_immediate_start_request(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('stall');

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1']);

        $payment = $shop->packagePayments()->firstOrFail();
        $this->assertSame('2026-07-30 12:00:00', $payment->immediate_start_at->format('Y-m-d H:i:s'));
        $this->assertNotNull($payment->immediate_start_ip);
    }

    public function test_confirmed_webhook_applies_the_package_from_the_snapshot(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('stall');

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1']);

        $this->webhook('PAY-123', 'CONFIRMED')->assertOk();

        $shop->refresh();
        $this->assertSame('booth', $shop->package);
        $this->assertSame(300, $shop->entitlement('max_products'));
        $this->assertTrue($shop->entitlement('online_payments'));
        $this->assertSame('2027-07-30', $shop->subscription_ends_at->format('Y-m-d'));
        $this->assertTrue($shop->packagePayments()->first()->isApplied());
    }

    public function test_manual_grants_survive_the_purchase(): void
    {
        $this->fakePaynow();
        // Kram z ręcznie nadanym mailingiem i podbitym limitem AI.
        [$seller, $shop] = $this->sellerOn('stall', [
            'entitlements' => array_merge(config('shop.packages.stall.entitlements'), ['bulk_mail' => true, 'ai_weekly_limit' => 900]),
        ]);

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1']);
        $this->webhook('PAY-123', 'CONFIRMED')->assertOk();

        $shop->refresh();
        // Stragan nie ma mailingu (false) i ma AI 400 — ręczne nadania wygrywają.
        $this->assertTrue($shop->entitlement('bulk_mail'));
        $this->assertSame(900, $shop->entitlement('ai_weekly_limit'));
    }

    public function test_webhook_with_a_bad_signature_changes_nothing(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('stall');
        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1']);

        $this->webhook('PAY-123', 'CONFIRMED', null)->assertStatus(400);

        $this->assertSame('stall', $shop->fresh()->package);
    }

    public function test_confirmed_webhook_is_idempotent(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('stall');
        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1']);

        $this->webhook('PAY-123', 'CONFIRMED')->assertOk();
        $appliedAt = $shop->packagePayments()->first()->applied_at;

        // Ręczna zmiana terminu po zakupie NIE może zostać nadpisana dublem webhooka.
        $shop->fresh()->forceFill(['subscription_ends_at' => Carbon::parse('2028-01-01')])->save();
        $this->webhook('PAY-123', 'CONFIRMED')->assertOk();

        $this->assertSame('2028-01-01', $shop->fresh()->subscription_ends_at->format('Y-m-d'));
        $this->assertEquals($appliedAt, $shop->packagePayments()->first()->applied_at);
    }

    public function test_pending_or_rejected_status_does_not_apply(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('stall');
        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1']);

        $this->webhook('PAY-123', 'PENDING')->assertOk();
        $this->webhook('PAY-123', 'REJECTED')->assertOk();

        $this->assertSame('stall', $shop->fresh()->package);
        // Odrzucenie jest terminalne: wiersz przestaje wisieć jako „pending".
        $this->assertSame('failed', $shop->packagePayments()->first()->status);
    }

    public function test_rejected_payment_invites_a_retry_on_the_screen(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('stall');
        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1']);
        $this->webhook('PAY-123', 'REJECTED')->assertOk();

        // Baner mówi prawdę („nie doszła do skutku"), a przyciski Kup zostają.
        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('nie doszła do skutku')
            ->assertDontSee('czeka na potwierdzenie')
            ->assertSee('Kup Stragan');

        // Ponowienie tworzy NOWY wiersz, który przejmuje baner jako pending.
        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1']);
        $this->assertSame(2, $shop->packagePayments()->count());

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('czeka na potwierdzenie')
            ->assertDontSee('nie doszła do skutku');
    }

    public function test_late_rejected_after_confirmed_cannot_undo_the_purchase(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('stall');
        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1']);

        $this->webhook('PAY-123', 'CONFIRMED')->assertOk();
        $this->webhook('PAY-123', 'REJECTED')->assertOk();

        // Spóźniony REJECTED po udanym zakupie: pakiet i status zostają.
        $this->assertSame('booth', $shop->fresh()->package);
        $this->assertSame(PackagePayment::STATUS_PAID, $shop->packagePayments()->first()->status);
    }

    public function test_downgrade_cannot_be_bought(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('pavilion', ['subscription_ends_at' => Carbon::parse('2026-09-30')]);

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1'])
            ->assertRedirect(route('seller.package.show'))
            ->assertSessionHas('error');

        $this->assertSame(0, $shop->packagePayments()->count());
        Http::assertNothingSent();
    }

    public function test_renewal_of_the_same_package_extends_the_existing_term(): void
    {
        $this->fakePaynow();
        // Pawilon opłacony do 30.09 przedłużany DZIŚ (29.07) — rok dokleja się
        // do terminu, więc wychodzi 30.09.2027, a nie 29.07.2027.
        [$seller, $shop] = $this->sellerOn('pavilion', ['subscription_ends_at' => Carbon::parse('2026-09-30')]);

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'pavilion']), ['immediate_start' => '1'])
            ->assertRedirect('https://sandbox.paynow.pl/pay/PAY-123');

        $payment = $shop->packagePayments()->firstOrFail();
        $this->assertSame('pavilion', $payment->target_package);
        $this->assertSame('1500.00', $payment->amount);
        $this->assertSame('2027-09-30', $payment->new_ends_at->format('Y-m-d'));

        $this->webhook('PAY-123', 'CONFIRMED')->assertOk();

        $shop->refresh();
        $this->assertSame('pavilion', $shop->package);
        $this->assertSame('2027-09-30', $shop->subscription_ends_at->format('Y-m-d'));
    }

    public function test_renewal_mails_speak_of_extending_not_buying(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('pavilion', ['subscription_ends_at' => Carbon::parse('2026-09-30')]);

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'pavilion']), ['immediate_start' => '1']);

        $started = EmailMessage::latest('id')->firstOrFail();
        $this->assertStringContainsString('Przedłużenie pakietu Pawilon', $started->subject);
        $this->assertStringContainsString('30.09.2027', json_encode($started->intro_lines, JSON_UNESCAPED_UNICODE));

        $this->webhook('PAY-123', 'CONFIRMED')->assertOk();

        $activated = EmailMessage::latest('id')->firstOrFail();
        $this->assertStringContainsString('przedłużony do 30.09.2027', $activated->subject);
        // „Kupiłeś" byłoby nieprawdą — sprzedawca nic nowego nie dostaje.
        $this->assertStringNotContainsString('jest aktywny', $activated->subject);
    }

    public function test_renewal_does_not_overwrite_an_individual_price(): void
    {
        $this->fakePaynow();
        // Cena indywidualna (900 zamiast 1500): przedłużenie idzie na warunkach
        // sklepu i nie może po cichu podnieść ceny na kolejny rok.
        [$seller, $shop] = $this->sellerOn('pavilion', [
            'price_yearly' => 900,
            'subscription_ends_at' => Carbon::parse('2026-09-30'),
        ]);

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'pavilion']), ['immediate_start' => '1']);

        $this->assertSame('900.00', $shop->packagePayments()->firstOrFail()->amount);

        $this->webhook('PAY-123', 'CONFIRMED')->assertOk();

        $this->assertSame('900.00', $shop->fresh()->price_yearly);
    }

    public function test_downsize_drops_the_higher_package_features_and_hides_excess_products(): void
    {
        $this->fakePaynow();
        // Pawilon 4 dni przed końcem, z ręcznie nadanym dodatkiem i katalogiem
        // o 12 sztuk większym, niż mieści Stragan — inaczej zejście nie miałoby
        // czego ukryć. Liczba z configu, bo test bada MECHANIZM zejścia, a nie
        // wysokość limitu; przybita wywracała się przy każdej zmianie cennika.
        [$seller, $shop] = $this->sellerOn('pavilion', [
            'entitlements' => array_merge(config('shop.packages.pavilion.entitlements'), ['bulk_mail' => true]),
            'subscription_ends_at' => Carbon::parse('2026-08-03'),
        ]);
        $limit = (int) config('shop.packages.booth.entitlements.max_products');
        Product::factory()->count($limit + 12)->create(['shop_id' => $shop->id, 'is_active' => true]);

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1'])
            ->assertRedirect('https://sandbox.paynow.pl/pay/PAY-123');

        // 750 − (1500 × 4/365 = 16,44) = 733,56 → 733.
        $this->assertSame('733.00', $shop->packagePayments()->firstOrFail()->amount);

        $this->webhook('PAY-123', 'CONFIRMED')->assertOk();

        $shop->refresh();
        $this->assertSame('booth', $shop->package);
        $this->assertSame('750.00', $shop->price_yearly);
        // Lepkość MUSI ustąpić, inaczej zejście byłoby pozorne.
        $this->assertFalse($shop->entitlement('bulk_mail'));
        $this->assertFalse($shop->entitlement('order_editing'));
        $this->assertSame($limit, (int) $shop->entitlement('max_products'));
        // Limit zmalał, więc nadwyżka schodzi z witryny — ale nic nie ginie.
        $this->assertSame($limit, $shop->products()->where('is_active', true)->count());
        $this->assertSame(12, $shop->products()->whereNotNull('auto_hidden_at')->count());
        $this->assertSame($limit + 12, $shop->products()->count());
    }

    public function test_downsize_mail_explains_the_hidden_products(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('pavilion', ['subscription_ends_at' => Carbon::parse('2026-08-03')]);
        $limit = (int) config('shop.packages.booth.entitlements.max_products');
        Product::factory()->count($limit + 2)->create(['shop_id' => $shop->id, 'is_active' => true]);

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1']);
        $this->webhook('PAY-123', 'CONFIRMED')->assertOk();

        $mail = EmailMessage::latest('id')->firstOrFail();
        // Bez tego zdania sprzedawca szukałby, gdzie podziały się produkty.
        $this->assertStringContainsString('2 produkty zostały ukryte', json_encode($mail->outro_lines, JSON_UNESCAPED_UNICODE));
    }

    public function test_downsize_outside_the_window_is_rejected_by_the_endpoint(): void
    {
        $this->fakePaynow();
        // Nie wystarczy nie pokazywać przycisku — sam adres też musi odmówić.
        [$seller, $shop] = $this->sellerOn('pavilion', ['subscription_ends_at' => Carbon::parse('2027-07-28')]);

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1'])
            ->assertRedirect(route('seller.package.show'))
            ->assertSessionHas('error');

        $this->assertSame(0, $shop->packagePayments()->count());
        Http::assertNothingSent();
    }

    public function test_free_package_cannot_be_renewed(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('stall');

        // Kram jest darmowy — nie ma czego przedłużać za zero złotych.
        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'stall']), ['immediate_start' => '1'])
            ->assertRedirect(route('seller.package.show'))
            ->assertSessionHas('error');

        $this->assertSame(0, $shop->packagePayments()->count());
        Http::assertNothingSent();
    }

    public function test_gateway_failure_marks_the_payment_failed(): void
    {
        Http::fake(['*/v1/payments' => Http::response(['error' => 'boom'], 500)]);
        [$seller, $shop] = $this->sellerOn('stall');

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1'])
            ->assertSessionHas('error');

        $this->assertSame('failed', $shop->packagePayments()->first()->status);
    }

    public function test_screen_shows_buy_buttons_and_pending_banner(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('stall');

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Kup Stragan — 750,00 zł')
            ->assertSee('Kup Pawilon — 1 500,00 zł');

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1']);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('czeka na potwierdzenie');
    }

    public function test_start_mails_the_seller_a_payment_link(): void
    {
        $this->fakePaynow();
        [$seller] = $this->sellerOn('stall');
        EmailMessage::query()->delete();

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1']);

        $mail = EmailMessage::where('to_email', $seller->email)->firstOrFail();
        $this->assertStringContainsString('Zamówienie pakietu Stragan', $mail->subject);
        // Link ratunkowy: prowadzi z powrotem do bramki.
        $this->assertSame('https://sandbox.paynow.pl/pay/PAY-123', $mail->action_url);
        // Mail platformy — bez brandingu sklepu.
        $this->assertNull($mail->shop_id);
    }

    public function test_confirmation_mails_a_thank_you_with_the_feature_list_and_term(): void
    {
        $this->fakePaynow();
        [$seller, $shop] = $this->sellerOn('stall');
        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1']);
        EmailMessage::query()->delete();

        $this->webhook('PAY-123', 'CONFIRMED')->assertOk();

        $mail = EmailMessage::where('to_email', $seller->email)->firstOrFail();
        $body = json_encode($mail->intro_lines, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Pakiet Stragan jest aktywny', $mail->subject);
        $this->assertStringContainsString('750,00', $body);
        $this->assertStringContainsString('30.07.2027', $body);              // okres
        $this->assertStringContainsString('• Integracja płatności online (własne konto Paynow)', $body); // lista funkcji
        $this->assertStringContainsString('• Do 300 produktów', $body);
    }

    public function test_payment_history_shows_status_and_validity(): void
    {
        // Dwie płatności = dwa różne paymentId, jak w realnym Paynow.
        Http::fakeSequence('*/v1/payments')
            ->push(['paymentId' => 'PAY-1', 'redirectUrl' => 'https://s/pay/1', 'status' => 'NEW'])
            ->push(['paymentId' => 'PAY-2', 'redirectUrl' => 'https://s/pay/2', 'status' => 'NEW']);
        [$seller, $shop] = $this->sellerOn('stall');

        // Nieudana próba, potem udany zakup — obie w historii.
        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1']);
        $this->webhook('PAY-1', 'REJECTED')->assertOk();
        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1']);
        $this->webhook('PAY-2', 'CONFIRMED')->assertOk();

        // `fresh()`: actingAs trzyma jeden obiekt między requestami, więc bez
        // tego relacja shop niesie stan sprzed zakupu (w produkcji każdy request
        // ładuje użytkownika świeżo).
        $this->actingAs($seller->fresh())->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Historia pakietu')
            ->assertSee('płatność nieudana')
            ->assertSee('ważny do 30.07.2027')
            // Opłata, z której wynika bieżący pakiet — z plakietką.
            ->assertSee('obecny');
    }

    public function test_with_online_purchase_the_change_box_points_at_buy_buttons(): void
    {
        $this->fakePaynow();
        [$seller] = $this->sellerOn('stall');

        // Zakup działa online — box nie każe pisać maila, kieruje na przyciski.
        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('przyciski „Kup" znajdziesz przy pakietach powyżej', false)
            ->assertDontSee('Napisz do nas, a przestawimy');
    }

    public function test_shop_without_invoice_data_cannot_buy(): void
    {
        $this->fakePaynow();
        $seller = User::factory()->consented()->create();
        // Sklep bez adresu — faktury nie da się wystawić, więc nie bierzemy pieniędzy.
        $shop = Shop::factory()->create(['owner_id' => $seller->id, 'package' => 'stall']);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Uzupełnij nazwę i adres')
            ->assertDontSee('Kup Stragan');

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1'])
            ->assertRedirect(route('seller.package.show'))
            ->assertSessionHas('error');

        $this->assertSame(0, $shop->packagePayments()->count());
        Http::assertNothingSent();
    }

    public function test_shop_without_company_data_buys_with_a_personal_invoice(): void
    {
        $this->fakePaynow();
        $seller = User::factory()->consented()->create(['name' => 'Anna', 'surname' => 'Kowalska']);
        // Osoba fizyczna (rękodzieło): brak NIP-u nie blokuje — faktura imienna.
        $shop = Shop::factory()->withPersonalInvoiceData()->create(['owner_id' => $seller->id, 'package' => 'stall']);

        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('fakturę imienną')
            ->assertSee('Anna Kowalska')
            ->assertSee('Kup Stragan');

        $this->actingAs($seller)->post(route('seller.package.purchase', ['package' => 'booth']), ['immediate_start' => '1'])
            ->assertRedirect('https://sandbox.paynow.pl/pay/PAY-123');

        $this->assertSame(1, $shop->packagePayments()->count());
    }

    public function test_invoice_recipient_is_shown_before_paying(): void
    {
        $this->fakePaynow();
        [$seller] = $this->sellerOn('stall');

        // Sprzedawca widzi, na co pójdzie dokument, ZANIM zapłaci.
        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertSee('Dane do faktury')
            ->assertSee('Kwiaciarnia Anna Kowalska')
            ->assertSee('NIP 1234563218')
            ->assertSee('Polna 7')
            ->assertSee('00-001 Warszawa');
    }

    public function test_without_platform_keys_there_are_no_buy_buttons(): void
    {
        config(['services.paynow.platform.api_key' => null]);
        [$seller] = $this->sellerOn('stall');

        // Bez konfiguracji ekran nie może pokazywać martwego przycisku.
        $this->actingAs($seller)->get(route('seller.package.show'))
            ->assertOk()
            ->assertDontSee('Kup Stragan');
    }
}
