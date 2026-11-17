<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Rozstrzygnięte ustawienia jednego wywołania AI: dostawca, model i parametry.
 *
 * Powstaje z nazwy ZADANIA („proofread", „product_copy") — ustawienia domyślne
 * z `config('ai.defaults')` przykryte tym, co dane zadanie nadpisuje. Dzięki temu
 * reszta kodu nie wie i nie musi wiedzieć, jaki model ją obsługuje.
 */
class AiProfile
{
    private function __construct(
        public readonly string $task,
        public readonly string $model,
        public readonly string $baseUrl,
        public readonly string $key,
        public readonly ?string $reasoningEffort,
        public readonly float $temperature,
        public readonly int $timeout,
    ) {}

    /**
     * Zbuduj profil dla zadania.
     *
     * @throws RuntimeException gdy zadanie lub dostawca nie są opisane w configu.
     */
    public static function forTask(string $task): self
    {
        $overrides = config("ai.tasks.{$task}");

        // Literówka w nazwie zadania ma paść od razu i głośno, a nie po cichu
        // zjechać na ustawienia domyślne z zupełnie innym modelem.
        if (! is_array($overrides)) {
            throw new RuntimeException("Nieznane zadanie AI: {$task}.");
        }

        $settings = array_merge(config('ai.defaults', []), $overrides);

        $providerName = (string) ($settings['provider'] ?? '');
        $provider = config("ai.providers.{$providerName}");

        if (! is_array($provider)) {
            throw new RuntimeException("Nieznany dostawca AI: {$providerName}.");
        }

        $effort = $settings['reasoning_effort'] ?? null;

        return new self(
            task: $task,
            model: (string) ($settings['model'] ?? ''),
            baseUrl: rtrim((string) ($provider['base_url'] ?? ''), '/'),
            key: (string) ($provider['key'] ?? ''),
            // Pusty wysiłek = parametru nie wysyłamy (modele nierozumujące go nie znają).
            reasoningEffort: $effort === null || $effort === '' ? null : (string) $effort,
            temperature: (float) ($settings['temperature'] ?? 0.3),
            timeout: (int) ($settings['timeout'] ?? 120),
        );
    }

    /**
     * Czy zadanie da się w ogóle wykonać — bez klucza dostawcy nie ma o czym mówić.
     */
    public function isConfigured(): bool
    {
        return $this->key !== '' && $this->model !== '' && $this->baseUrl !== '';
    }

    /**
     * Skrót dla widoków: czy pokazywać przyciski danego zadania. Bez klucza
     * przycisk prowadziłby tylko do komunikatu „chwilowo niedostępna", który
     * kłamie — usługa nie zadziała nigdy, więc przycisku po prostu nie ma.
     */
    public static function available(string $task): bool
    {
        return self::forTask($task)->isConfigured();
    }
}
