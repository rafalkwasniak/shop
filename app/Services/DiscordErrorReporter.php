<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Posts reportable exceptions to a Discord channel via an incoming webhook as a
 * rich embed. No-ops when no webhook is configured, and never lets a delivery
 * failure escape — that would loop back into the exception handler.
 */
class DiscordErrorReporter
{
    private const COLOR_ERROR = 0xED4245;

    public function report(Throwable $e): void
    {
        $webhook = config('services.discord.webhook');

        if (empty($webhook)) {
            return;
        }

        try {
            Http::timeout(5)->post($webhook, ['embeds' => [$this->embed($e)]]);
        } catch (Throwable) {
            // Reporting must never break the request or recurse.
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function embed(Throwable $e): array
    {
        return [
            'title' => Str::limit('['.config('app.name').'] ERROR', 250),
            'description' => Str::limit($e->getMessage()."\n\n```\n".$this->trace($e)."\n```", 4000),
            'color' => self::COLOR_ERROR,
            'fields' => [
                ['name' => 'Type', 'value' => Str::limit($e::class, 1024)],
                ['name' => 'Location', 'value' => Str::limit($this->relative($e->getFile()).':'.$e->getLine(), 1024)],
                ['name' => 'Source', 'value' => Str::limit($this->source(), 1024)],
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    private function trace(Throwable $e): string
    {
        $lines = [];

        foreach (array_slice($e->getTrace(), 0, 6) as $i => $frame) {
            $location = isset($frame['file'])
                ? $this->relative($frame['file']).':'.($frame['line'] ?? '?')
                : '[internal]';
            $call = ($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '');

            $lines[] = "#{$i} {$location} {$call}";
        }

        return $lines === [] ? '(no stack trace)' : implode("\n", $lines);
    }

    private function relative(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }

    private function source(): string
    {
        if (app()->runningInConsole()) {
            $argv = array_slice($_SERVER['argv'] ?? [], 1);

            return 'cli: '.(implode(' ', $argv) ?: 'artisan');
        }

        return request()->method().' '.request()->fullUrl();
    }
}
