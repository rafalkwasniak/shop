<?php

use App\Enums\LegalDocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Wstawia wersję 1 każdego wymaganego dokumentu (treść pusta — dochodzi
 * później), żeby LegalDocument::current() miało co zwracać, a rejestracja
 * miała do czego dowiązać zgodę. Idempotentne: pomija typ, który już ma
 * jakąkolwiek wersję (bezpieczne przy ponownym migrate).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (LegalDocumentType::cases() as $type) {
            $exists = DB::table('legal_documents')->where('type', $type->value)->exists();

            if ($exists) {
                continue;
            }

            DB::table('legal_documents')->insert([
                'type' => $type->value,
                'version' => 1,
                'content' => null,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        foreach (LegalDocumentType::cases() as $type) {
            DB::table('legal_documents')
                ->where('type', $type->value)
                ->where('version', 1)
                ->delete();
        }
    }
};
