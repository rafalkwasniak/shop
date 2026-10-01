<?php

namespace App\Services\Ai;

use App\Exceptions\AiQuotaExceededException;
use App\Models\Shop;
use App\Services\AiQuota;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Jedyne miejsce w aplikacji, które rozmawia z API modelu językowego.
 *
 * Wołający podaje ZADANIE, nie model — czym zostanie obsłużone, rozstrzyga
 * `config/ai.php`. Treści użytkowników nie trafiają do logów, klucz zostaje
 * po stronie serwera.
 */
class AiClient
{
    public function __construct(private readonly AiQuota $quota) {}

    /**
     * Wykonaj zadanie: instrukcja systemowa + treść użytkownika → odpowiedź modelu.
     *
     * Sklep jest argumentem OBOWIĄZKOWYM (bez wartości domyślnej), choć
     * technicznie do wywołania modelu niepotrzebny: to jedyne miejsce w aplikacji
     * rozmawiające z API, więc o limicie ma tu rozstrzygać każde wywołanie.
     * Gdyby parametr miał domyślną wartość, nowe miejsce wołające AI ominęłoby
     * limit przez zwykłe zapomnienie. `null` trzeba więc wpisać ŚWIADOMIE — to
     * praca platformy, nie sklepu (administrator redagujący wiadomość do
     * sprzedawców), i nie ma z czyjej puli jej pobrać.
     *
     * `$taskId` scala fragmenty jednego kliknięcia w jedno zadanie (patrz AiQuota).
     *
     * @param  string  $task  Nazwa zadania z `config('ai.tasks')`.
     *
     * @throws AiQuotaExceededException gdy sklep wyczerpał tygodniowy limit
     * @throws RuntimeException gdy zadanie nie jest skonfigurowane lub wywołanie zawiedzie
     */
    public function run(string $task, string $system, string $content, ?Shop $shop, ?string $taskId = null): string
    {
        $profile = AiProfile::forTask($task);

        if (! $profile->isConfigured()) {
            throw new RuntimeException("Usługa AI nie jest skonfigurowana (zadanie: {$task}).");
        }

        // Limit pobieramy PRZED wysłaniem żądania — po odpowiedzi byłoby za późno,
        // bo koszt już powstał.
        if ($shop !== null) {
            $this->quota->consume($shop, $taskId);
        }

        $payload = [
            'model' => $profile->model,
            'temperature' => $profile->temperature,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $content],
            ],
        ];

        if ($profile->reasoningEffort !== null) {
            $payload['reasoning_effort'] = $profile->reasoningEffort;
        }

        $response = Http::baseUrl($profile->baseUrl)
            ->withToken($profile->key)
            ->timeout($profile->timeout)
            ->post('/chat/completions', $payload);

        if ($response->failed()) {
            throw new RuntimeException("Wywołanie AI zakończyło się błędem (zadanie: {$task}).");
        }

        // Modele rozumujące zwracają tok myślenia w osobnym polu
        // („reasoning_content") — nas interesuje wyłącznie właściwa odpowiedź.
        $answer = $response->json('choices.0.message.content');

        if (! is_string($answer) || trim($answer) === '') {
            throw new RuntimeException("AI zwróciło pustą odpowiedź (zadanie: {$task}).");
        }

        return trim($answer);
    }

    /**
     * Jak run(), ale STRUMIENIEM: model odsyła odpowiedź kawałkami (SSE),
     * a każdy kawałek tekstu trafia od razu do $onDelta — dzięki temu
     * użytkownik widzi tekst w trakcie pisania, nie po jego zakończeniu.
     * Zwraca pełną, sklejoną odpowiedź (ta sama wartość, którą dałoby run()).
     *
     * Limit pobierany jak w run() — przed wysłaniem żądania. Fragmenty toku
     * myślenia („reasoning_content") są pomijane: to nie jest treść odpowiedzi.
     *
     * @param  callable(string): void  $onDelta  Wołane dla każdego kawałka tekstu odpowiedzi.
     *
     * @throws AiQuotaExceededException gdy sklep wyczerpał tygodniowy limit
     * @throws RuntimeException gdy zadanie nie jest skonfigurowane lub wywołanie zawiedzie
     */
    public function stream(string $task, string $system, string $content, ?Shop $shop, ?string $taskId, callable $onDelta): string
    {
        $profile = AiProfile::forTask($task);

        if (! $profile->isConfigured()) {
            throw new RuntimeException("Usługa AI nie jest skonfigurowana (zadanie: {$task}).");
        }

        if ($shop !== null) {
            $this->quota->consume($shop, $taskId);
        }

        $payload = [
            'model' => $profile->model,
            'temperature' => $profile->temperature,
            'stream' => true,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $content],
            ],
        ];

        if ($profile->reasoningEffort !== null) {
            $payload['reasoning_effort'] = $profile->reasoningEffort;
        }

        $response = Http::baseUrl($profile->baseUrl)
            ->withToken($profile->key)
            ->timeout($profile->timeout)
            // Bez tej opcji klient HTTP i tak zbuforowałby całe body przed
            // zwróceniem odpowiedzi — strumień istniałby tylko na papierze.
            ->withOptions(['stream' => true])
            ->post('/chat/completions', $payload);

        if ($response->failed()) {
            throw new RuntimeException("Wywołanie AI zakończyło się błędem (zadanie: {$task}).");
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $answer = '';

        // Protokół SSE: zdarzenia to linie „data: {json}", odpowiedź kończy
        // „data: [DONE]". Kawałki sieci nie respektują granic linii, więc
        // trzymamy niedokończoną linię w buforze do następnego odczytu.
        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newline));
                $buffer = substr($buffer, $newline + 1);

                if (! str_starts_with($line, 'data:')) {
                    continue;
                }

                $data = trim(substr($line, 5));

                if ($data === '[DONE]') {
                    break 2;
                }

                $delta = json_decode($data, true)['choices'][0]['delta']['content'] ?? '';

                if ($delta !== '') {
                    $answer .= $delta;
                    $onDelta($delta);
                }
            }
        }

        if (trim($answer) === '') {
            throw new RuntimeException("AI zwróciło pustą odpowiedź (zadanie: {$task}).");
        }

        return trim($answer);
    }
}
