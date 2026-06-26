<?php

namespace Tests\Feature\Consent;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentReacceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_consented_seller_is_not_gated(): void
    {
        $seller = User::factory()->consented()->create();

        $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk();
    }

    public function test_new_document_version_forces_reacceptance(): void
    {
        $seller = User::factory()->consented()->create();

        // Wydanie nowej wersji Regulaminu = nowy rekord.
        LegalDocument::create([
            'type' => LegalDocumentType::Terms,
            'version' => 2,
            'content' => null,
            'published_at' => now(),
        ]);

        // Brama przekierowuje na stronę zgód, zamiast wpuścić do panelu.
        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertRedirect(route('consents.show'));

        $this->actingAs($seller)->get(route('consents.show'))->assertOk();
    }

    public function test_seller_can_accept_new_version_and_proceed(): void
    {
        $seller = User::factory()->consented()->create();

        $newTerms = LegalDocument::create([
            'type' => LegalDocumentType::Terms,
            'version' => 2,
            'content' => null,
            'published_at' => now(),
        ]);

        $this->actingAs($seller)->post(route('consents.store'), [
            'documents' => [$newTerms->id => '1'],
        ])->assertRedirect();

        $this->assertTrue($seller->fresh()->hasConsentedToCurrent(LegalDocumentType::Terms));

        // Po akceptacji panel znów dostępny.
        $this->actingAs($seller->fresh())->get(route('seller.dashboard'))->assertOk();
    }

    public function test_store_requires_accepting_all_outstanding_documents(): void
    {
        $seller = User::factory()->consented()->create();

        $newTerms = LegalDocument::create([
            'type' => LegalDocumentType::Terms,
            'version' => 2,
            'content' => null,
            'published_at' => now(),
        ]);

        $this->actingAs($seller)->post(route('consents.store'), [
            'documents' => [$newTerms->id => '0'],
        ])->assertSessionHasErrors("documents.{$newTerms->id}");

        $this->assertFalse($seller->fresh()->hasConsentedToCurrent(LegalDocumentType::Terms));
    }
}
