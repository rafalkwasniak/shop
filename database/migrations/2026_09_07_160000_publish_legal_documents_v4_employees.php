<?php

use App\Enums\LegalDocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Regulamin i Polityka Prywatności w wersji 4 — konta pracownicze
 * (plan-shop-employees, krok 6b). Brzmienie zatwierdzone przez Rafała 2026-09-07.
 *
 * DLACZEGO MIGRACJA, A NIE RĘCZNY ZAPIS: treść dokumentów żyje w bazie, a
 * Kramio nie ma ekranu admina do jej edycji. Wstukana z konsoli istniałaby
 * wyłącznie na produkcji — nie dałoby się jej odtworzyć po przeprowadzce ani
 * sprawdzić, kto i kiedy zmienił który zapis. Tak samo powstała wersja 1
 * (`seed_initial_legal_documents`).
 *
 * SKUTEK UBOCZNY JEST ZAMIERZONY: nowa wersja uruchamia bramę zgód
 * (`EnsureConsentsAreCurrent`), więc każdy sprzedawca przy najbliższym wejściu
 * do panelu zobaczy ekran ponownej akceptacji. Pracowników to nie dotyczy —
 * stroną umowy z Kramio jest ich pracodawca (`User::outstandingConsents()`).
 *
 * PODMIANY MUSZĄ TRAFIĆ W KOTWICĘ ALBO MIGRACJA MA PAŚĆ. Cicha publikacja
 * dokumentu bez nowych zapisów byłaby najgorszym wynikiem: wszyscy sprzedawcy
 * akceptowaliby wtedy „nową" wersję, która niczym się nie różni, a my
 * uznalibyśmy temat za załatwiony. Stąd wyjątek zamiast `str_replace` w ciemno.
 */
return new class extends Migration
{
    private const VERSION = 4;

    public function up(): void
    {
        $this->publish(LegalDocumentType::Terms, [
            // §2 — definicja, bo oba nowe ustępy używają tego pojęcia jak zdefiniowanego.
            ' <li><strong>Pakiet</strong> — wariant usługi określający zakres funkcji Sklepu, zgodnie z aktualnym Cennikiem.</li>' => ' <li><strong>Konto Pracownicze</strong> — odrębne konto osoby, której Sprzedawca powierzył obsługę swojego Sklepu, z dostępem ograniczonym do działów panelu wskazanych przez Sprzedawcę.</li>'
                    ."\n".' <li><strong>Pakiet</strong> — wariant usługi określający zakres funkcji Sklepu, zgodnie z aktualnym Cennikiem.</li>',

            // §6 — dopisane ZARAZ ZA zakazem udostępniania danych logowania, bo
            // Konta Pracownicze są legalną alternatywą dla łamania tamtego zdania.
            ' <li>Sprzedawca może w każdej chwili usunąć Konto wraz ze Sklepem (§14).</li>' => ' <li>Sprzedawca może utworzyć w Serwisie Konta Pracownicze — odrębne konta osób, którym powierza obsługę Sklepu — i określić zakres ich dostępu do poszczególnych działów panelu. Konta Pracownicze nie mają dostępu do danych rozliczeniowych Sprzedawcy, jego Pakietu ani ustawień integracji. Sprzedawca odpowiada za działania osób korzystających z Kont Pracowniczych jak za działania własne oraz za niezwłoczne odebranie dostępu osobie, która przestała z nim współpracować.</li>'
                    ."\n".' <li>Sprzedawca może w każdej chwili usunąć Konto wraz ze Sklepem (§14).</li>',

            // §18 — żeby pracownik nie stał się przypadkiem odrębnym administratorem
            // ani dalszym podmiotem przetwarzającym Operatora.
            '(w tym z wysyłki newsletterów wyłącznie do osób, które wyraziły zgodę).</li>' => '(w tym z wysyłki newsletterów wyłącznie do osób, które wyraziły zgodę).</li>'
                    ."\n".' <li><strong>Konta Pracownicze.</strong> Sprzedawca upoważnia osoby korzystające z utworzonych przez siebie Kont Pracowniczych do przetwarzania danych Klientów Sklepu w jego imieniu i w nadanym przez niego zakresie; osoby te działają w ramach jego struktury jako administratora, a nie jako odrębni administratorzy ani dalsze podmioty przetwarzające Operatora. Sprzedawca odpowiada za nadanie i odebranie tych upoważnień oraz za zobowiązanie tych osób do zachowania poufności.</li>',
        ]);

        $this->publish(LegalDocumentType::Privacy, [
            // Część II — językiem drugiej osoby, jak reszta polityki: czyta to
            // klient sklepu, nie sprzedawca.
            'ale to sprzedawca decyduje o celach wykorzystania danych swoich klientów.</p>' => 'ale to sprzedawca decyduje o celach wykorzystania danych swoich klientów.</p>'
                    ."\n".'<p>Sprzedawca może dopuścić do obsługi swojego sklepu współpracujące z nim osoby — na przykład kogoś, kto pakuje przesyłki albo prowadzi rozliczenia. Osoby te dostają w Kramio odrębne konta z dostępem wyłącznie do tych części panelu, które wskaże im sprzedawca, i działają w jego imieniu oraz na jego odpowiedzialność. <strong>Nie są odrębnymi administratorami Twoich danych.</strong></p>',
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
