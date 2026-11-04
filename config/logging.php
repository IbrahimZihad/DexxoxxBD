<?php

declare(strict_types=1);

return [
    'path' => base_path('storage/logs'),

    // debug | info | warning | error | critical
    'level' => env('APP_DEBUG', false) ? 'debug' : 'info',

    'channels' => [
        'app' => 'app',
        'error' => 'error',
        'audit' => 'audit',
        'payment' => 'payment',
        'job' => 'jobs',
    ],

    'retention_days' => (int) env('LOG_RETENTION_DAYS', 90),

    // Mirror critical events into MySQL system_logs for dashboard visibility.
    'database_mirror' => true,
];
