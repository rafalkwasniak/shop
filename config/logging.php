<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        'slow_queries' => [
            'driver' => 'daily',
            'path' => storage_path('logs/slow-query.log'),
            'level' => 'debug',
            'days' => 14,
            'replace_placeholders' => true,
        ],

        // Audyt integracji z Fakturownią (żądanie + odpowiedź przy wystawianiu FV).
        // Realne faktury idą do KSeF, więc ślad trzymamy znacznie dłużej niż zwykłe
        // logi. Token API NIGDY tu nie trafia — logujemy payload bez sekretu.
        'fakturownia' => [
            'driver' => 'daily',
            'path' => storage_path('logs/fakturownia.log'),
            'level' => 'debug',
            'days' => 365,
            'replace_placeholders' => true,
        ],

        // Audyt płatności online (Paynow): utworzenie płatności + każdy webhook o
        // zmianie statusu. Dotyczy pieniędzy, więc ślad trzymamy długo (jak FV).
        // Klucze sprzedawcy NIGDY tu nie trafiają — logujemy payload i status
        // bez sekretów.
        'paynow' => [
            'driver' => 'daily',
            'path' => storage_path('logs/paynow.log'),
            'level' => 'debug',
            'days' => 365,
            'replace_placeholders' => true,
        ],

        // Audyt płatności Przelewy24 — rejestracja transakcji, powiadomienia,
        // weryfikacja. Te same zasady co `paynow`: długa retencja, bo to
        // pieniądze; klucz CRC i klucz API NIGDY tu nie trafiają.
        'przelewy24' => [
            'driver' => 'daily',
            'path' => storage_path('logs/przelewy24.log'),
            'level' => 'debug',
            'days' => 365,
            'replace_placeholders' => true,
        ],

        // Audyt nadawania przesyłek (InPost ShipX): utworzenie przesyłki, wynik
        // zakupu i pobranie etykiety. Dotyczy pieniędzy sprzedawcy (każde nadanie
        // zdejmuje opłatę z jego salda InPost), więc ślad trzymamy długo jak przy
        // FV i płatnościach. Token ShipX NIGDY tu nie trafia.
        'shipx' => [
            'driver' => 'daily',
            'path' => storage_path('logs/shipx.log'),
            'level' => 'debug',
            'days' => 365,
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', env('APP_NAME', 'Laravel')),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
