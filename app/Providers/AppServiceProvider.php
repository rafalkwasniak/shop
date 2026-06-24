<?php

namespace App\Providers;

use App\Services\SlowQueryLogger;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ((int) config('monitoring.slow_query_ms') > 0) {
            DB::listen(fn (QueryExecuted $query) => app(SlowQueryLogger::class)->handle($query));
        }
    }
}
