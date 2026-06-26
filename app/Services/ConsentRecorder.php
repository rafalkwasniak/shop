<?php

namespace App\Services;

use App\Models\LegalDocument;
use App\Models\User;

/**
 * Zapis zgód użytkownika na konkretne wersje dokumentów prawnych. Wspólne
 * źródło dla rejestracji i strony ponownej akceptacji. Idempotentne —
 * powtórna zgoda na tę samą wersję nie tworzy duplikatu (unikat w bazie).
 */
class ConsentRecorder
{
    /**
     * @param  iterable<LegalDocument>  $documents
     */
    public function record(User $user, iterable $documents, ?string $ip): void
    {
        foreach ($documents as $document) {
            $user->consents()->firstOrCreate(
                ['legal_document_id' => $document->getKey()],
                ['accepted_at' => now(), 'ip_address' => $ip],
            );
        }
    }
}
