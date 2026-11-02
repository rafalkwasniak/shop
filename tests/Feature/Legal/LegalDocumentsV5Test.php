<?php

namespace Tests\Feature\Legal;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Wersja 5 Regulaminu i Polityki Prywatności — formularz kontaktowy sklepu.
 *
 * Kotwice tej migracji sprawdzone na treści produkcyjnej v4 (02.11.2026):
 * każda występuje dokładnie raz. Tutaj pilnujemy mechaniki: nowa wersja
 * powstaje z nowymi zapisami, migracja jest idempotentna, a rozjechana
 * kotwica wywraca ją zamiast cicho opublikować dokument bez zmian.
 */
class LegalDocumentsV5Test extends TestCase
{
    use RefreshDatabase;

    private const PRIVACY_V4 = '<p>Część II dotyczy sklepów: zakupów, kont klientów i newsletterów sklepów. Administratorem jest sprzedawca.</p>'
        .'<p>To sprzedawca — przy zakupie, zakładaniu konta klienta, zapisie na newsletter czy składaniu zwrotu.</p>'
        ."<ul>\n <li><strong>dostawa</strong> — przewoźnik;</li>\n <li><strong>wiadomości e-mail sklepu</strong> (potwierdzenia) — infrastruktura Kramio.</li>\n</ul>";

    private const TERMS_V4 = '<li><strong>Klient Sklepu</strong> — osoba dokonująca zakupów w Sklepie lub korzystająca z jego funkcji (konto klienta, newsletter).</li>'
        .'<li>Dane identyfikacyjne i kontaktowe (imię i nazwisko, adres, e-mail, telefon, dla firm NIP i nazwa), dane zamówień i rozliczeń oraz zgody — dotyczące Klientów Sklepu.</li>';

    private function migration(): object
    {
        return require database_path('migrations/2026_11_03_140000_publish_legal_documents_v5_contact_form.php');
    }

    private function seedV4(string $privacy = self::PRIVACY_V4, string $terms = self::TERMS_V4): void
    {
        foreach ([LegalDocumentType::Privacy->value => $privacy, LegalDocumentType::Terms->value => $terms] as $type => $content) {
            LegalDocument::query()->where('type', $type)->delete();
            LegalDocument::create(['type' => $type, 'version' => 4, 'content' => $content, 'published_at' => now()->subMonth()]);
        }
    }

    public function test_publishes_version_5_with_the_contact_form(): void
    {
        $this->seedV4();

        $this->migration()->up();

        $privacy = LegalDocument::current(LegalDocumentType::Privacy);
        $terms = LegalDocument::current(LegalDocumentType::Terms);

        $this->assertSame(5, (int) $privacy->version);
        $this->assertSame(5, (int) $terms->version);
        $this->assertStringContainsString('<strong>formularz kontaktowy</strong> — jeśli sklep go udostępnia: imię, adres e-mail i treść wiadomości', $privacy->content);
        $this->assertStringContainsString('najdłużej przez dobę', $privacy->content);
        $this->assertStringContainsString('newsletterów sklepów i wiadomości wysyłanych przez formularz kontaktowy.', $privacy->content);
        $this->assertStringContainsString('(konto klienta, newsletter, formularz kontaktowy)', $terms->content);
        $this->assertStringContainsString('treść korespondencji ze Sklepem (w tym wiadomości z formularza kontaktowego)', $terms->content);
        // Reszta treści nietknięta.
        $this->assertStringContainsString('<strong>wiadomości e-mail sklepu</strong>', $privacy->content);
    }

    /** Nowa wersja = sprzedawcy dostają ekran ponownej akceptacji. */
    public function test_sellers_who_accepted_v4_are_asked_again(): void
    {
        $this->seedV4();
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
        $this->seedV4();

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(1, LegalDocument::query()->where('version', 5)->where('type', LegalDocumentType::Privacy->value)->count());
    }

    public function test_missing_anchor_fails_loudly_instead_of_publishing_an_unchanged_document(): void
    {
        $this->seedV4(privacy: '<p>Zupełnie inna treść polityki.</p>');

        $this->expectException(RuntimeException::class);

        $this->migration()->up();
    }

    public function test_fresh_install_with_empty_documents_is_left_alone(): void
    {
        $this->migration()->up();

        $this->assertSame(0, LegalDocument::query()->where('version', 5)->count());
    }
}
