<?php

use App\Console\Logger;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */
    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "custom", "stack"
    |
    */
    'channels' => [
        'stack' => [
            'driver'   => 'stack',
            'channels' => [
                'daily',
                // PHP_SAPI === 'cli' ? 'console' : 'daily',
            ],
        ],
        'cron' => [
            'driver'   => 'stack',
            'channels' => [
                'cron_rotating',
                // 'stdout',
            ],
        ],
        'single' => [
            'driver' => 'single',
            'path'   => storage_path('logs/laravel.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
        ],
        'daily' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/laravel.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
            'days'   => env('LOG_DAYS', 3),
        ],
        'cron_rotating' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/cron.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
            'days'   => env('LOG_DAYS', 3),
        ],
        'slack' => [
            'driver'   => 'slack',
            'url'      => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji'    => ':boom:',
            'level'    => 'critical',
        ],
        'stdout' => [
            'driver' => 'custom',
            'via'    => Logger::class,
        ],
        'syslog' => [
            'driver' => 'syslog',
            'level'  => env('LOG_LEVEL', 'debug'),
        ],
        'errorlog' => [
            'driver' => 'errorlog',
            'level'  => env('LOG_LEVEL', 'debug'),
        ],
    ],
];
