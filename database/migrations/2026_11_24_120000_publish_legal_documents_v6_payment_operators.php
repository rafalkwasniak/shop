<?php

use App\Enums\LegalDocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Regulamin i Polityka Prywatności w wersji 6.
 *
 * POLITYKA — operator płatności online bez nazwy. Od 23.11.2026 sprzedawca
 * może przyjmować płatności przez Paynow, Przelewy24 albo obie bramki naraz.
 * Zdanie „operatorem płatności sprzedawcy (Paynow)" mówiło kupującemu
 * nieprawdę o tym, dokąd trafiają jego dane. Decyzja Rafała (23–24.11): w
 * dokumentach bez nazw operatorów — „operator wybrany przez sprzedawcę" nie
 * zestarzeje się przy kolejnej bramce.
 *
 * OBA DOKUMENTY — data obowiązywania. Wersje 4 i 5 zostawiły w treści
 * „15.08.2026" (data wersji 3), a strona dokumentu pokazuje tylko tę datę.
 * Regulamin zmienia się wyłącznie w tym punkcie; Rafał (24.11) wybrał
 * poprawkę teraz, bo sprzedawcy i tak akceptują politykę — jeden ekran
 * akceptacji zamiast dwóch.
 *
 * Wzorzec jak w `publish_legal_documents_v5_contact_form`: kotwica musi
 * trafić albo migracja pada; na świeżej i testowej bazie (pusta treść) nic nie
 * robi; idempotentna.
 */
return new class extends Migration
{
    private const VERSION = 6;

    public function up(): void
    {
        $date = now()->format('d.m.Y');

        // Oba dokumenty albo żaden: rozjechana kotwica regulaminu nie może
        // zostawić opublikowanej samej polityki.
        DB::transaction(function () use ($date): void {
            $this->publish(LegalDocumentType::Privacy, [
                'operatorem płatności sprzedawcy (Paynow)' => 'operatorem płatności wybranym przez sprzedawcę',
                'Polityka obowiązuje od dnia: 15.08.2026.' => 'Polityka obowiązuje od dnia: '.$date.'.',
            ]);

            $this->publish(LegalDocumentType::Terms, [
                'Regulamin obowiązuje od dnia: 15.08.2026.' => 'Regulamin obowiązuje od dnia: '.$date.'.',
            ]);
        });
    }

    public function down(): void
    {
        DB::table('legal_documents')->where('version', self::VERSION)->delete();
    }

    /**
     * @param  array<string, string>  $replacements  kotwica => tekst po podmianie
     */
    private function publish(LegalDocumentType $type, array $replacements): void
    {
        $current = DB::table('legal_documents')
            ->where('type', $type->value)
            ->orderByDesc('version')
            ->first();

        // Świeża instalacja / baza testowa: pusta treść, nie ma czego zmieniać.
        if ($current === null || blank($current->content)) {
            return;
        }

        if ((int) $current->version >= self::VERSION) {
            return; // już opublikowana — migracja idempotentna
        }

        $content = $current->content;

        foreach ($replacements as $anchor => $replacement) {
            if (! str_contains($content, $anchor)) {
                throw new RuntimeException(
                    "Dokument {$type->value}: nie znalazłem kotwicy „".mb_substr($anchor, 0, 60).
                    '…”. Treść rozjechała się z tą migracją — popraw kotwicę zamiast publikować wersję bez nowych zapisów.'
                );
            }

            $content = str_replace($anchor, $replacement, $content);
        }

        $now = now();

        DB::table('legal_documents')->insert([
            'type' => $type->value,
            'version' => self::VERSION,
            'content' => $content,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
