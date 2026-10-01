<?php

namespace App\Services;

use App\Models\Shop;
use App\Services\Ai\AiClient;
use RuntimeException;

/**
 * Redakcja treści przez AI. Dwie ścieżki:
 * - improve(): zwykły tekst (krótki prompt) — pola bez formatowania.
 * - improveHtml(): fragment HTML z edytora (dłuższy prompt) — model poprawia
 *   wyłącznie tekst wewnątrz znaczników, nie ruszając samego HTML.
 *
 * Ta klasa odpowiada wyłącznie za instrukcje dla modelu; czym zostaną wykonane,
 * rozstrzyga zadanie `proofread` w `config/ai.php`. Poprawiane są tylko ortografia,
 * interpunkcja, styl i czytelność — bez dodawania nowych informacji.
 * Wzorzec przeniesiony z projektu kociaczek.com.pl.
 */
class AiTextImprover
{
    /** Zadanie z `config('ai.tasks')` obsługujące redakcję tekstu. */
    private const TASK = 'proofread';

    public function __construct(private readonly AiClient $ai) {}

    /**
     * Redakcja zwykłego tekstu.
     *
     * Z $onDelta odpowiedź płynie strumieniem: każdy kawałek tekstu trafia do
     * callbacka w trakcie pisania przez model (patrz AiClient::stream()).
     * Zwracana wartość jest w obu trybach ta sama — pełny poprawiony tekst.
     *
     * @param  callable(string): void|null  $onDelta
     *
     * @throws RuntimeException gdy usługa nie jest skonfigurowana lub wywołanie zawiedzie.
     */
    public function improve(string $text, ?Shop $shop, ?int $maxChars = null, ?string $taskId = null, ?callable $onDelta = null): string
    {
        $system = 'Jesteś redaktorem języka polskiego. Popraw przesłany tekst: ortografię, '
            .'interpunkcję, styl i czytelność; możesz poprawić formatowanie. Nie dodawaj żadnych '
            .'nowych informacji ani faktów, których nie ma w oryginale. Odpowiedz wyłącznie '
            .'poprawioną treścią, bez wstępu i komentarzy. Jeśli w wyrażeniu brakuje kluczowych '
            .'słów, dodaj je.';

        if ($maxChars !== null) {
            $system .= " Wynik nie może przekroczyć {$maxChars} znaków.";
        }

        return $onDelta !== null
            ? $this->ai->stream(self::TASK, $system, $text, $shop, $taskId, $onDelta)
            : $this->ai->run(self::TASK, $system, $text, $shop, $taskId);
    }

    /**
     * Redakcja fragmentu HTML — model pracuje WYŁĄCZNIE w obrębie tekstu, nie
     * zmieniając znaczników, ich kolejności ani atrybutów.
     *
     * Z $onDelta odpowiedź płynie strumieniem (jak w improve()). Kawałki lecą
     * SUROWE — ewentualne opakowanie w blok kodu zdejmujemy dopiero z pełnej
     * odpowiedzi, więc ostateczną wersją pola jest ZAWSZE wartość zwrócona,
     * nie suma kawałków.
     *
     * @param  callable(string): void|null  $onDelta
     *
     * @throws RuntimeException gdy usługa nie jest skonfigurowana lub wywołanie zawiedzie.
     */
    public function improveHtml(string $html, ?Shop $shop, ?int $maxChars = null, ?string $taskId = null, ?callable $onDelta = null): string
    {
        $system = 'Jesteś redaktorem języka polskiego pracującym na fragmencie HTML. Otrzymujesz '
            .'treść z prostymi znacznikami formatowania (m.in. <strong>, <em>, <del>, <h2>, <ul>, '
            .'<ol>, <li>, <a>, <br>, <div>). Popraw WYŁĄCZNIE tekst widoczny '
            .'wewnątrz znaczników: ortografię, interpunkcję, styl i czytelność. Jeśli w zdaniu '
            .'brakuje kluczowych słów, dodaj je. ZACHOWAJ DOKŁADNIE strukturę HTML — nie dodawaj, '
            .'nie usuwaj ani nie zmieniaj znaczników, ich kolejności i atrybutów; nie zmieniaj '
            .'adresów w atrybucie href. Nie dodawaj nowych informacji ani faktów spoza oryginału. '
            .'Nie używaj Markdown ani bloków kodu. Odpowiedz wyłącznie poprawionym HTML, bez '
            .'wstępu i komentarzy.';

        if ($maxChars !== null) {
            $system .= " Wynik nie może przekroczyć {$maxChars} znaków.";
        }

        $raw = $onDelta !== null
            ? $this->ai->stream(self::TASK, $system, $html, $shop, $taskId, $onDelta)
            : $this->ai->run(self::TASK, $system, $html, $shop, $taskId);

        // Zdejmij ewentualne opakowanie w blok kodu (```html ... ```), gdyby model je dodał.
        $result = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $raw);

        return trim((string) $result);
    }
}
