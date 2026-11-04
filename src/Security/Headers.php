<?php
declare(strict_types=1);

namespace App\Security;

use App\Core\Config;
use App\Http\Request;

final class Headers
{
    /** Baseline headers for every response, JSON or redirect. */
    public static function apply(Request $request): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 0');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=(), interest-cohort=()');
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
        header('Cache-Control: no-store');

        if ($request->isSecure()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        header_remove('X-Powered-By');
    }

    /**
     * CORS allow-list. Only origins configured in .env may send credentials, and
     * the response is varied on Origin so caches cannot mix them.
     */
    public static function cors(Request $request): void
    {
        $origin = $request->origin();
        $allowed = Config::get('cors.allowed_origins', []);

        if ($origin !== null && in_array(rtrim($origin, '/'), $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Credentials: true');
        }

        if ($request->method() === 'OPTIONS') {
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
            header('Access-Control-Max-Age: 600');
            http_response_code(204);
            exit;
        }
    }
}
