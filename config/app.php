<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'ZapSub'),
    'env' => strtolower((string) Env::get('APP_ENV', 'local')),
    'debug' => filter_var((string) Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    'url' => rtrim((string) Env::get('APP_URL', ''), '/'),
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Dhaka'),
    'locale' => 'en',
    'currency' => 'BDT',
    'currency_symbol' => '৳',

    // AES-256-GCM key for the small amount of secret data the app stores (the
    // account details an agent attaches to a delivered order). 64 hex chars.
    'key' => (string) Env::get('APP_KEY', ''),

    'session' => [
        'name' => Env::get('SESSION_NAME', 'zapsub_session'),
        'lifetime' => (int) Env::get('SESSION_LIFETIME', '7200'),
        'secure' => filter_var((string) Env::get('SESSION_SECURE', 'false'), FILTER_VALIDATE_BOOL),
    ],

    // Buckets are enforced per IP by App\Core\RateLimiter using a sliding window
    // stored under storage/rate. Values come from .env as "attempts,seconds".
    'rates' => [
        'login' => Env::pair('RATE_LOGIN', '5,900'),
        'register' => Env::pair('RATE_REGISTER', '3,3600'),
        'checkout' => Env::pair('RATE_CHECKOUT', '15,3600'),
        'guest_order' => Env::pair('RATE_GUEST_ORDER', '8,3600'),
        'contact' => Env::pair('RATE_CONTACT', '6,3600'),
        'track' => Env::pair('RATE_TRACK', '20,600'),
        'reset' => Env::pair('RATE_RESET', '4,3600'),
        'general' => Env::pair('RATE_GENERAL', '120,60'),
    ],

    'mail' => [
        'driver' => Env::get('MAIL_DRIVER', 'log'),
        'from_address' => Env::get('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'from_name' => Env::get('MAIL_FROM_NAME', 'ZapSub Support'),
    ],

    // Only enable when the site really is served over TLS; enabling it on plain
    // http://localhost bounces every request into a redirect loop.
    'force_https' => filter_var((string) Env::get('APP_FORCE_HTTPS', 'false'), FILTER_VALIDATE_BOOL),

    // Route patterns that must accept a POST without a CSRF token, for the day a
    // payment gateway posts an IPN back to us. Empty by default.
    'csrf_exempt' => [],

    // Hard cap on how many plan units one order may contain. Keeps a scripted
    // checkout from draining stock and makes an accidental bulk click harmless.
    'max_items_per_order' => 10,
    'max_quantity_per_line' => 5,

    'order_number_prefix' => 'ZS',
];
