<?php

namespace App\Http\Controllers;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use Illuminate\Contracts\Support\Renderable;

/**
 * Publiczne strony dokumentów prawnych (Regulamin, Polityka Prywatności).
 * Dostępne bez logowania; renderują aktualną opublikowaną wersję (treść może
 * być jeszcze pusta — pokazujemy placeholder).
 */
class LegalController extends Controller
{
    public function show(LegalDocumentType $type): Renderable
    {
        $document = LegalDocument::current($type);

        return view('legal.show', [
            'type' => $type,
            'document' => $document,
        ]);
    }
}
