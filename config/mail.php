<?php

declare(strict_types=1);

return [
    // log writes rendered mail to storage/logs/mail-*.log so local XAMPP runs
    // never need an SMTP server; smtp sends for real.
    'mailer' => env('MAIL_MAILER', 'log'),

    'host' => env('MAIL_HOST', 'localhost'),
    'port' => (int) env('MAIL_PORT', 587),
    'username' => env('MAIL_USERNAME', ''),
    'password' => env('MAIL_PASSWORD', ''),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'timeout' => 15,

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'no-reply@dexxoxxbd.test'),
        'name' => env('MAIL_FROM_NAME', 'DexxoxxBD'),
    ],

    'reply_to' => [
        'address' => env('MAIL_REPLY_TO', 'support@dexxoxxbd.test'),
        'name' => 'DexxoxxBD Support',
    ],

    // Copies of transactional mail land here in addition to the queue.
    'log_path' => base_path('storage/logs/mail.log'),

    'templates' => [
        'verify_email' => 'auth.verify-email',
        'password_reset' => 'auth.password-reset',
        'order_confirmation' => 'account.order-confirmation',
        'order_status' => 'account.order-status',
        'delivery' => 'account.delivery',
        'ticket_reply' => 'account.ticket-reply',
        'welcome' => 'account.welcome',
        'low_stock' => 'admin.low-stock',
    ],

    'queue' => [
        'max_attempts' => 4,
        'backoff_seconds' => 60,
    ],
];
