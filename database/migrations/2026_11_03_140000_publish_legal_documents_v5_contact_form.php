<?php

use App\Enums\LegalDocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Regulamin i Polityka Prywatności w wersji 5 — formularz kontaktowy sklepu
 * (zakładka „Kontakt", 11.2026). Decyzja Rafała 2026-11-02: nowa wersja
 * z ponowną akceptacją przez sprzedawców jest w porządku — „po to jest, żeby
 * działało".
 *
 * Formularz zbiera od kupujących imię, e-mail i treść wiadomości, a adres IP
 * trafia na krótko do limitów antyspamowych (ContactFormGuard, najdłużej doba).
 * Administratorem jest sprzedawca, Kramio przetwarza w jego imieniu — więc
 * zmiana dotyczy Części II polityki oraz definicji i §18 (powierzenie)
 * regulaminu.
 *
 * Wzorzec i uzasadnienia jak w `publish_legal_documents_v4_employees`:
 * kotwica musi trafić albo migracja pada (żadnej cichej publikacji wersji bez
 * nowych zapisów); na świeżej i testowej bazie (pusta treść) nic nie robi;
 * idempotentna.
 */
return new class extends Migration
{
    private const VERSION = 5;

    public function up(): void
    {
        $this->publish(LegalDocumentType::Privacy, [
            'zakupów, kont klientów i newsletterów sklepów.' => 'zakupów, kont klientów, newsletterów sklepów i wiadomości wysyłanych przez formularz kontaktowy.',
            'przy zakupie, zakładaniu konta klienta, zapisie na newsletter czy składaniu zwrotu.' => 'przy zakupie, zakładaniu konta klienta, zapisie na newsletter, składaniu zwrotu czy wysyłaniu wiadomości przez formularz kontaktowy.',
            ' <li><strong>wiadomości e-mail sklepu</strong>' => ' <li><strong>formularz kontaktowy</strong> — jeśli sklep go udostępnia: imię, adres e-mail i treść wiadomości. Wiadomość trafia do sprzedawcy, który odpowiada na podany adres; Tobie nie wysyłamy żadnej kopii. Adres IP jest zapamiętywany najdłużej przez dobę, wyłącznie do ochrony formularza przed spamem;</li>'."\n".' <li><strong>wiadomości e-mail sklepu</strong>',
        ]);

        $this->publish(LegalDocumentType::Terms, [
            'korzystająca z jego funkcji (konto klienta, newsletter).' => 'korzystająca z jego funkcji (konto klienta, newsletter, formularz kontaktowy).',
            'dla firm NIP i nazwa), dane zamówień i rozliczeń oraz zgody' => 'dla firm NIP i nazwa), dane zamówień i rozliczeń, treść korespondencji ze Sklepem (w tym wiadomości z formularza kontaktowego) oraz zgody',
        ]);
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

        // Świeża instalacja: wersja 1 ma treść pustą (patrz
        // `seed_initial_legal_documents`), więc nie ma czego rozszerzać. Dotyczy
        // też bazy testowej, gdzie migracje chodzą na czystym schemacie.
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
