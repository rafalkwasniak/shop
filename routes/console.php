<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Outbox maili: krótki proces co minutę (bezpieczne na shared-hoście, nie demon).
// Wymaga wpisu crona na serwerze: * * * * * php artisan schedule:run
Schedule::command('email:dispatch')->everyMinute()->withoutOverlapping();
