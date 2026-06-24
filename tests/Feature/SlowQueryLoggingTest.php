<?php

namespace Tests\Feature;

use App\Services\SlowQueryLogger;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class SlowQueryLoggingTest extends TestCase
{
    public function test_a_slow_query_is_logged_to_the_slow_channel(): void
    {
        config(['monitoring.slow_query_ms' => 200]);

        $channel = Mockery::mock();
        $channel->shouldReceive('warning')->once()->withArgs(function ($message, $context) {
            return $context['time_ms'] >= 200
                && str_contains($context['sql'], 'select')
                && array_key_exists('origin', $context);
        });
        Log::shouldReceive('channel')->with('slow_queries')->once()->andReturn($channel);

        app(SlowQueryLogger::class)->handle($this->query('select * from products', 250.0));
    }

    public function test_a_fast_query_is_not_logged(): void
    {
        config(['monitoring.slow_query_ms' => 200]);

        Log::shouldReceive('channel')->never();

        app(SlowQueryLogger::class)->handle($this->query('select * from products', 5.0));
    }

    public function test_logging_is_disabled_when_threshold_is_zero(): void
    {
        config(['monitoring.slow_query_ms' => 0]);

        Log::shouldReceive('channel')->never();

        app(SlowQueryLogger::class)->handle($this->query('select * from products', 9999.0));
    }

    private function query(string $sql, float $timeMs): QueryExecuted
    {
        return new QueryExecuted($sql, [], $timeMs, DB::connection());
    }
}
