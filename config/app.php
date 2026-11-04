<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'DexxoxxBD'),
    'short_name' => 'DexxoxxBD',
    'env' => env('APP_ENV', 'local'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),

    // Base URL used by url()/asset(); XAMPP serves the project from htdocs.
    'url' => rtrim((string) env('APP_URL', 'http://localhost/DexxoxxBD/public'), '/'),
    'key' => env('APP_KEY', ''),

    'timezone' => env('DEFAULT_TIMEZONE', 'Asia/Dhaka'),
    'locale' => env('DEFAULT_LOCALE', 'en'),
    'fallback_locale' => 'en',
    'locales' => ['en', 'bn'],

    'currency' => env('DEFAULT_CURRENCY', 'BDT'),
    'currency_symbol' => '৳',
    'currency_code' => 'BDT',

    'pagination' => [
        'per_page' => 12,
        'admin_per_page' => 20,
        'max_per_page' => 100,
    ],

    'view_paths' => [
        'main' => base_path('resources/views'),
    ],

    'brand' => [
        'tagline' => 'Premium digital subscriptions, delivered instantly',
        'support_phone' => '+880 1700-000000',
        'support_email' => 'support@dexxoxxbd.test',
        'address' => 'Dhaka, Bangladesh',
        'hours' => 'Open 24/7 - our team replies within 2 hours',
    ],

    'shop' => [
        'minimum_order_amount' => 0,
        'order_number_prefix' => 'DXB',
        'guest_checkout' => true,
        'allow_negative_stock' => false,
        'low_stock_threshold' => 5,
        'abandoned_cart_days' => 7,
    ],
];
