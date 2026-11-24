<?php

namespace Tests\Feature\Legal;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

/**
 * Regulamin i Polityka Prywatności w wersji 6: operator płatności online bez
 * nazwy (od 23.11.2026 sklep może mieć Paynow, Przelewy24 albo obie bramki)
 * oraz data obowiązywania, której wersje 4 i 5 nie zaktualizowały.
 *
 * Kotwice sprawdzone na treści produkcyjnej v5 (24.11.2026): każda występuje
 * dokładnie raz. Pilnujemy mechaniki: nowe zapisy i data publikacji, jeden
 * ekran akceptacji, idempotencja, a rozjechana kotwica wywraca CAŁĄ migrację.
 */
class LegalDocumentsV6Test extends TestCase
{
    use RefreshDatabase;

    private const PRIVACY_V5 = "<ul>\n <li><strong>płatności online</strong> — realizowane bezpośrednio między Tobą a operatorem płatności sprzedawcy (Paynow); środki trafiają na rachunek sprzedawcy i nie przechodzą przez Kramio;</li>\n"
        ." <li><strong>płatności za pakiety</strong> — operator płatności Paynow (mBank S.A.);</li>\n</ul>"
        .'<p>Polityka obowiązuje od dnia: 15.08.2026.</p>';

    private const TERMS_V5 = '<p>Operator nie odpowiada za awarie u dostawców zewnętrznych (operator płatności).</p>'
        .'<p>Regulamin obowiązuje od dnia: 15.08.2026.</p>';

    private function migration(): object
    {
        return require database_path('migrations/2026_11_24_120000_publish_legal_documents_v6_payment_operators.php');
    }

    private function seedV5(string $privacy = self::PRIVACY_V5, string $terms = self::TERMS_V5): void
    {
        foreach ([LegalDocumentType::Privacy->value => $privacy, LegalDocumentType::Terms->value => $terms] as $type => $content) {
            LegalDocument::query()->where('type', $type)->delete();
            LegalDocument::create(['type' => $type, 'version' => 5, 'content' => $content, 'published_at' => now()->subMonth()]);
        }
    }

    public function test_publishes_version_6_without_operator_name_and_with_todays_date(): void
    {
        Carbon::setTestNow('2026-11-24 12:00:00');
        $this->seedV5();

        $this->migration()->up();

        $privacy = LegalDocument::current(LegalDocumentType::Privacy);
        $this->assertSame(6, (int) $privacy->version);
        $this->assertStringContainsString('między Tobą a operatorem płatności wybranym przez sprzedawcę; środki trafiają', $privacy->content);
        $this->assertStringNotContainsString('sprzedawcy (Paynow)', $privacy->content);
        $this->assertStringContainsString('Polityka obowiązuje od dnia: 24.11.2026.', $privacy->content);
        // Opłaty za pakiety Kramio NADAL idą przez Paynow — tego zdania nie ruszamy.
        $this->assertStringContainsString('operator płatności Paynow (mBank S.A.)', $privacy->content);

        // Regulamin: wyłącznie data.
        $terms = LegalDocument::current(LegalDocumentType::Terms);
        $this->assertSame(6, (int) $terms->version);
        $this->assertSame(
            str_replace('15.08.2026', '24.11.2026', self::TERMS_V5),
            $terms->content,
        );
    }

    /** Jeden ekran akceptacji: sprzedawca akceptuje oba dokumenty naraz. */
    public function test_sellers_are_asked_to_accept_both_documents_again(): void
    {
        $this->seedV5();
        $seller = User::factory()->create();
        foreach (LegalDocument::query()->get() as $document) {
            $seller->consents()->create(['legal_document_id' => $document->id, 'accepted_at' => now()]);
        }
        $this->assertTrue($seller->fresh()->outstandingConsents()->isEmpty());

        $this->migration()->up();

        $this->assertCount(2, $seller->fresh()->outstandingConsents());
    }

    public function test_running_twice_publishes_once(): void
    {
        $this->seedV5();

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(2, LegalDocument::query()->where('version', 6)->count());
    }

    public function test_missing_privacy_anchor_fails_loudly(): void
    {
        $this->seedV5(privacy: '<p>Zupełnie inna treść polityki.</p>');

        $this->expectException(RuntimeException::class);

        $this->migration()->up();
    }

    public function test_missing_terms_anchor_does_not_leave_privacy_published_alone(): void
    {
        $this->seedV5(terms: '<p>Regulamin bez daty.</p>');

        try {
            $this->migration()->up();
            $this->fail('Migracja powinna paść na brakującej kotwicy regulaminu.');
        } catch (RuntimeException) {
            // oczekiwane
        }

        $this->assertSame(0, LegalDocument::query()->where('version', 6)->count());
    }

    public function test_rollback_restores_version_5(): void
    {
        $this->seedV5();
        $this->migration()->up();

        $this->migration()->down();

        $this->assertSame(5, (int) LegalDocument::current(LegalDocumentType::Privacy)->version);
        $this->assertSame(5, (int) LegalDocument::current(LegalDocumentType::Terms)->version);
    }

    public function test_fresh_install_with_empty_documents_is_left_alone(): void
    {
        $this->migration()->up();

        $this->assertSame(0, LegalDocument::query()->where('version', 6)->count());
    }
}
