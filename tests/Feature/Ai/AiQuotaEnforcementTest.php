<?php

namespace Tests\Feature\Ai;

use App\Models\Shop;
use App\Models\User;
use App\Services\AiQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Egzekwowanie limitu w punkcie, przez który przechodzi KAŻDE wywołanie modelu.
 * Sklep jest obowiązkowym argumentem `AiClient::run()` właśnie po to, żeby nowe
 * miejsce wołające AI nie ominęło limitu przez zapomnienie. Jedyny świadomy
 * `null` to administrator — redaguje wiadomości platformy i nie ma sklepu.
 */
class AiQuotaEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithLimit(int $limit): array
    {
        config(['ai.providers.deepseek.key' => 'test-key']);
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Poprawiony tekst.']]],
            ]),
        ]);

        $seller = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $seller->id]);
        $shop->forceFill(['entitlements' => array_merge($shop->entitlements ?? [], [
            'ai_weekly_limit' => $limit,
        ])])->save();

        return [$seller, $shop->fresh()];
    }

    private function improve(User $seller, ?string $taskId = null): TestResponse
    {
        return $this->actingAs($seller)->postJson(route('ai.improve'), array_filter([
            'field' => 'shop_description',
            'text' => 'tekst do poprawy',
            'task_id' => $taskId,
        ]));
    }

    public function test_each_correction_consumes_one_unit(): void
    {
        [$seller, $shop] = $this->sellerWithLimit(5);

        $this->improve($seller)->assertOk();
        $this->improve($seller)->assertOk();

        $this->assertSame(2, app(AiQuota::class)->used($shop));
    }

    public function test_response_carries_the_remaining_count(): void
    {
        [$seller] = $this->sellerWithLimit(5);

        // Bez tego przeglądarka pokazywałaby starą liczbę aż do odświeżenia
        // strony — licznik wyglądałby na zepsuty.
        $this->improve($seller)->assertOk()->assertJsonPath('remaining', 4);
        $this->improve($seller)->assertOk()->assertJsonPath('remaining', 3);
    }

    public function test_exhausted_limit_returns_429_with_the_reset_date(): void
    {
        [$seller] = $this->sellerWithLimit(1);

        $this->improve($seller)->assertOk();

        $response = $this->improve($seller)->assertStatus(429);

        // Ton informacyjny, nie karcący — i odpowiedź na jedyne pytanie, jakie
        // sprzedawca ma w tej chwili: „to kiedy znów mogę kliknąć?".
        $this->assertStringContainsString('pulę AI', $response->json('message'));
        $this->assertStringContainsString('Nowa czeka już w', $response->json('message'));
        $this->assertStringContainsString('wyższym pakiecie', $response->json('message'));
    }

    public function test_fragments_of_one_click_consume_one_unit(): void
    {
        [$seller, $shop] = $this->sellerWithLimit(2);

        // Długi opis dzieli przeglądarka na fragmenty — wszystkie niosą ten sam
        // identyfikator zadania, więc dla limitu to jedno kliknięcie.
        foreach (range(1, 8) as $fragment) {
            $this->improve($seller, 'klikniecie-1')->assertOk();
        }

        $this->assertSame(1, app(AiQuota::class)->used($shop));
    }

    public function test_second_click_consumes_another_unit(): void
    {
        [$seller, $shop] = $this->sellerWithLimit(3);

        $this->improve($seller, 'klikniecie-1')->assertOk();
        $this->improve($seller, 'klikniecie-2')->assertOk();

        $this->assertSame(2, app(AiQuota::class)->used($shop));
    }

    public function test_no_model_call_happens_once_the_limit_is_gone(): void
    {
        [$seller] = $this->sellerWithLimit(1);

        $this->improve($seller)->assertOk();
        $this->improve($seller)->assertStatus(429);

        // Jednostkę pobieramy PRZED żądaniem do modelu — po odpowiedzi koszt
        // już by powstał. Stąd dokładnie jedno wyjście na zewnątrz.
        Http::assertSentCount(1);
    }

    public function test_seo_button_shares_the_same_pool(): void
    {
        [$seller, $shop] = $this->sellerWithLimit(5);
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Opis SEO produktu.']]],
            ]),
        ]);

        $this->actingAs($seller)->postJson(route('ai.seo-description'), [
            'text' => str_repeat('Opis produktu wystarczająco długi, by mieć co streszczać. ', 4),
        ])->assertOk();

        // Jeden licznik dla WSZYSTKICH funkcji AI (ustalenie Rafała).
        $this->assertSame(1, app(AiQuota::class)->used($shop));
    }

    public function test_user_without_a_shop_is_told_what_to_do(): void
    {
        config(['ai.providers.deepseek.key' => 'test-key']);
        $user = User::factory()->consented()->create();

        // Limit wisi na sklepie, więc bez sklepu nie ma z czego go pobrać —
        // lepiej powiedzieć to wprost niż udawać awarię usługi.
        $this->actingAs($user)->postJson(route('ai.improve'), [
            'field' => 'shop_description',
            'text' => 'tekst',
        ])->assertStatus(403)->assertJsonPath('message', 'Najpierw dokończ zakładanie sklepu.');
    }

    public function test_admin_without_a_shop_can_improve_a_mailing(): void
    {
        config(['ai.providers.deepseek.key' => 'test-key']);
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '<div>Poprawiona wiadomość.</div>']]],
            ]),
        ]);

        $admin = User::factory()->admin()->consented()->create();

        // Panel admina → Wiadomości używa tego samego pola co newsletter
        // sprzedawcy. Wcześniej kończyło się to 403 („dokończ zakładanie
        // sklepu"), a przeglądarka pokazywała „usługa chwilowo niedostępna".
        $this->actingAs($admin)->postJson(route('ai.improve'), [
            'field' => 'mailing_body',
            'text' => '<div>wiadomosc do poprawy</div>',
        ])
            ->assertOk()
            ->assertJsonPath('text', '<div>Poprawiona wiadomość.</div>')
            ->assertJsonPath('remaining', null);

        // Praca platformy idzie bez puli — żaden sklep niczego nie traci.
        $this->assertDatabaseCount('ai_usages', 0);
    }

    public function test_admin_without_a_shop_can_improve_a_mailing_by_stream(): void
    {
        config(['ai.providers.deepseek.key' => 'test-key', 'ai.streaming' => true]);
        Http::fake([
            '*/chat/completions' => Http::response(
                'data: '.json_encode(['choices' => [['delta' => ['content' => 'Gotowe.']]]])."\n\n"
                ."data: [DONE]\n\n",
                200,
                ['Content-Type' => 'text/event-stream'],
            ),
        ]);

        $admin = User::factory()->admin()->consented()->create();

        $body = $this->actingAs($admin)->postJson(route('ai.improve'), [
            'field' => 'mailing_body',
            'text' => '<div>wiadomosc</div>',
            'stream' => true,
        ])->assertOk()->streamedContent();

        $this->assertStringContainsString('"done":true', $body);
        $this->assertStringNotContainsString('"error":true', $body);
        $this->assertDatabaseCount('ai_usages', 0);
    }
}
