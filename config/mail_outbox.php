<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kolejka maili (outbox)
    |--------------------------------------------------------------------------
    |
    | Strojenie komendy `email:dispatch` (cron co minutę). batch_size = ile
    | maili wychodzi na jeden tik (throttling), max_attempts = ile prób zanim
    | wiadomość zostanie trwale oznaczona jako nieudana, retry_delay_minutes =
    | backoff między próbami (żeby nie katować niestabilnego SMTP co minutę).
    |
    */

    'batch_size' => (int) env('MAIL_OUTBOX_BATCH_SIZE', 20),

    'max_attempts' => (int) env('MAIL_OUTBOX_MAX_ATTEMPTS', 3),

    'retry_delay_minutes' => (int) env('MAIL_OUTBOX_RETRY_DELAY_MINUTES', 5),

];
