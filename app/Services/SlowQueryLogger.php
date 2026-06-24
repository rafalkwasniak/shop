<?php

namespace App\Services;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;

/**
 * Writes any database query slower than the configured threshold to a dedicated
 * log channel, together with the SQL, bindings and the application origin
 * (file:line) so a slow query can be traced back to the code that issued it.
 */
class SlowQueryLogger
{
    public function handle(QueryExecuted $query): void
    {
        $thresholdMs = (int) config('monitoring.slow_query_ms');

        if ($thresholdMs <= 0 || $query->time < $thresholdMs) {
            return;
        }

        Log::channel('slow_queries')->warning('Slow query', [
            'time_ms' => round($query->time, 2),
            'connection' => $query->connectionName,
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'origin' => $this->origin(),
        ]);
    }

    /**
     * The application frame that issued the query. The query event fires through
     * the framework, so the real caller sits *after* the vendor frames — the
     * listener closure and this service run before them and must be skipped.
     */
    private function origin(): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;
        $vendor = $base.'vendor'.DIRECTORY_SEPARATOR;
        $seenVendor = false;

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            $file = $frame['file'] ?? null;

            if ($file === null) {
                continue;
            }

            if (str_starts_with($file, $vendor)) {
                $seenVendor = true;

                continue;
            }

            if ($seenVendor && str_starts_with($file, $base)) {
                return substr($file, strlen($base)).':'.($frame['line'] ?? '?');
            }
        }

        return '(unknown origin)';
    }
}
