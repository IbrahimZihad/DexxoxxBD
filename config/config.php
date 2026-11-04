<?php
declare(strict_types=1);

/**
 * Application configuration. Pure data — no side effects, no output.
 * Every secret comes from .env (see .env.example); nothing here is committed.
 */

use App\Core\Env;

$appEnv = Env::get('APP_ENV', 'production') ?? 'production';
$isLocal = $appEnv === 'local';

$siteUrl = rtrim(Env::get('SITE_URL', 'http://localhost') ?? 'http://localhost', '/');
$apiBase = rtrim(Env::get('API_BASE_URL', $siteUrl . '/api') ?? ($siteUrl . '/api'), '/');
$frontend = rtrim(Env::get('FRONTEND_URL', $siteUrl) ?? $siteUrl, '/');

return [
    'app' => [
        'env' => $appEnv,
        'debug' => Env::bool('APP_DEBUG', $isLocal),
        'name' => Env::get('APP_NAME', 'DexxoxxBD') ?? 'DexxoxxBD',
        'timezone' => Env::get('APP_TIMEZONE', 'Asia/Dhaka') ?? 'Asia/Dhaka',
        'locale_currency' => Env::get('CURRENCY', 'BDT') ?? 'BDT',
    ],

    'db' => [
        'host' => Env::get('DB_HOST', '127.0.0.1') ?? '127.0.0.1',
        'port' => Env::int('DB_PORT', 3306),
        'name' => Env::get('DB_NAME', ''),
        'user' => Env::get('DB_USER', ''),
        'pass' => Env::get('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],

    'urls' => [
        'site' => $siteUrl,
        'frontend' => $frontend,
        'api' => $apiBase,
    ],

    'cors' => [
        'allowed_origins' => Env::list('ALLOWED_ORIGINS', [$frontend, $siteUrl]),
    ],

    'session' => [
        'name' => Env::get('SESSION_NAME', 'dexxoxx_session') ?? 'dexxoxx_session',
        'lifetime' => Env::int('SESSION_LIFETIME', 60 * 60 * 8),
        'idle_timeout' => Env::int('SESSION_IDLE_TIMEOUT', 60 * 60 * 2),
    ],

    'security' => [
        'bcrypt_cost' => Env::int('BCRYPT_COST', 12),
        'password_min_length' => Env::int('PASSWORD_MIN_LENGTH', 8),
        'trusted_proxies' => Env::list('TRUSTED_PROXIES', []),
        'rehash_on_login' => true,
    ],

    'rate_limits' => [
        'read' => ['max' => Env::int('THROTTLE_READ_HITS', 240), 'window' => 60],
        'auth.login' => ['max' => Env::int('THROTTLE_LOGIN_ATTEMPTS', 8), 'window' => 900],
        'auth.register' => ['max' => Env::int('THROTTLE_REGISTER_ATTEMPTS', 5), 'window' => 3600],
        'orders.create' => ['max' => Env::int('THROTTLE_ORDER_CREATES', 30), 'window' => 3600],
        'payment.init' => ['max' => Env::int('THROTTLE_PAYMENT_INITIATIONS', 20), 'window' => 3600],
        'payment.manual' => ['max' => Env::int('THROTTLE_MANUAL_PAYMENT_SUBMITS', 20), 'window' => 3600],
        'default' => ['max' => 600, 'window' => 60],
    ],

    'orders' => [
        'pending_ttl_minutes' => Env::int('PENDING_ORDER_TTL_MINUTES', 360),
        'max_quantity_per_item' => 20,
        'max_cart_items' => 50,
    ],

    'payment' => [
        'sslcommerz' => [
            'store_id' => Env::get('SSLCZ_STORE_ID', ''),
            'store_password' => Env::get('SSLCZ_STORE_PASSWORD', ''),
            'sandbox' => Env::bool('SSLCZ_IS_SANDBOX', true),
            'currency' => Env::get('SSLCZ_CURRENCY', 'BDT') ?? 'BDT',
        ],
        'manual_enabled' => Env::bool('MANUAL_PAYMENTS_ENABLED', true),
    ],

    'storage' => [
        'path' => dirname(__DIR__) . '/storage',
        'log_file' => dirname(__DIR__) . '/storage/logs/app.log',
    ],
];
