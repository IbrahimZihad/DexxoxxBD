<?php

declare(strict_types=1);

return [
    'session' => [
        'name' => 'dexxoxx_session',
        'lifetime' => (int) env('SESSION_LIFETIME', 120) * 60,
        'expire_on_browser_close' => true,
        'path' => base_path('storage/sessions'),
        'cookie_path' => '/',
        'cookie_domain' => '',
        'samesite' => 'Lax',
    ],

    // Force Secure on cookies even for a plain-HTTP admin preview (opt in).
    'force_secure_cookies' => filter_var(env('FORCE_SECURE_COOKIES', false), FILTER_VALIDATE_BOOLEAN),

    // Only trust X-Forwarded-* when the app really sits behind a proxy.
    'trust_proxy_headers' => filter_var(env('TRUST_PROXY_HEADERS', false), FILTER_VALIDATE_BOOLEAN),

    'password' => [
        'algorithm' => 'auto',
        'min_length' => 8,
        'character_classes' => 2,
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 1,
        'bcrypt_cost' => 12,
        'reset_token_ttl' => 3600,
        'verification_ttl' => 86400,
        'deny_list' => [
            'password', 'password1', '12345678', '123456789', 'qwertyuiop', 'qwerty123',
            'iloveyou', 'admin123', 'letmein', 'welcome1', 'monkey123', 'abc12345',
            '11111111', 'password', 'doctorwho', 'superman', 'trustno1',
        ],
    ],

    'rate_limiting' => [
        'default_max' => 60,
        'default_window_seconds' => 60,
        'login_max_attempts' => 5,
        'login_window_minutes' => 10,
        'login_ip_max_attempts' => 25,
        'registration_max' => 5,
        'registration_window_seconds' => 3600,
        'password_reset_max' => 5,
        'password_reset_window_seconds' => 900,
        'checkout_max' => 20,
        'checkout_window_seconds' => 600,
        'contact_max' => 5,
        'contact_window_seconds' => 3600,
        'search_max' => 40,
        'search_window_seconds' => 60,
    ],

    // Gateway webhooks authenticate by signature instead of a session token.
    'csrf_exempt' => [
        'payment/webhook/sslcommerz',
        'payment/webhook/bkash',
        'payment/webhook/nagad',
        'payment/ipn/mock',
    ],

    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-XSS-Protection' => '0',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), payment=()',
        'Cross-Origin-Resource-Policy' => 'same-origin',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        // {nonce} is replaced per request. All JavaScript ships as external,
        // nonce-tagged files; inline styles stay allowed for Bootstrap bars.
        'Content-Security-Policy' => "default-src 'self'; "
            . "script-src 'self' {nonce}; "
            . "style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data: blob:; "
            . "font-src 'self' data:; "
            . "connect-src 'self'; "
            . "form-action 'self'; "
            . "frame-ancestors 'self'; "
            . "base-uri 'self'; "
            . "object-src 'none'",
    ],

    // Report-only CSP endpoint target; empty disables reporting.
    'csp_report_only' => false,

    'hsts_enabled' => filter_var(env('HSTS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'hsts_max_age' => 31536000,

    // HTTPS redirect for production (disabled for local XAMPP http).
    'force_https' => filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOLEAN),

    'uploads' => [
        'image_max_bytes' => 3 * 1024 * 1024,
        'max_bytes' => 5 * 1024 * 1024,
        'allowed_image_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'],
        'image_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'],
        'directory' => 'assets/uploads',
    ],

    'encryption' => [
        'cipher' => 'aes-256-gcm',
        'key' => env('INVENTORY_ENCRYPTION_KEY', ''),
        // The key material itself is read from this file, which lives outside
        // the web root and is git-ignored.
        'key_file' => base_path('storage/private/encryption.key'),
        'key_set_file' => base_path('storage/private/encryption.set'),
        'previous_keys' => [],
    ],

    'identifier_pepper' => env('APP_KEY', 'dexxoxx-local-pepper'),

    'permissions_policy' => [
        'admin.access' => 'Reach the admin dashboard at all',
    ],
];
