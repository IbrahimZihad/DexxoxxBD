<?php
/**
 * Global site configuration
 *
 * Deployment models supported:
 *  1) Single domain (recommended): set SITE_URL only, e.g. https://subpass.example.com
 *     The frontend lives at SITE_URL/ (public/) and the API at SITE_URL/api (public/api/).
 *  2) Split domains (frontend + backend hosted separately, e.g. Vercel + Render):
 *     set FRONTEND_URL and API_BASE_URL explicitly instead of SITE_URL.
 */

// ---------------------------------------------------------
// Error handling — never leak stack traces/paths to clients.
// Set APP_ENV=local to see raw PHP errors during development.
// ---------------------------------------------------------
$isProd = getenv('APP_ENV') !== 'local';
ini_set('display_errors', $isProd ? '0' : '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e) {
    error_log('[SubPass] Uncaught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Something went wrong on our end. Please try again shortly.']);
    exit;
});
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// ---------------------------------------------------------
// URL configuration
// ---------------------------------------------------------
$siteUrl = rtrim(getenv('SITE_URL') ?: 'http://localhost:8080', '/');
define('FRONTEND_URL', rtrim(getenv('FRONTEND_URL') ?: $siteUrl, '/'));
define('API_BASE_URL', rtrim(getenv('API_BASE_URL') ?: ($siteUrl . '/api'), '/'));

define('SITE_NAME', 'SubPass');

// ---------------------------------------------------------
// Secure, cross-origin-capable session cookies
// Must be configured before session_start().
// ---------------------------------------------------------
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 14, // 14 days
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isHttps,                  // requires HTTPS in production
    'httponly' => true,
    'samesite' => $isHttps ? 'None' : 'Lax', // 'None' needed when frontend/API are on different domains
]);
ini_set('session.use_strict_mode', '1');
session_start();

// ---------------------------------------------------------
// SSLCommerz credentials — sandbox defaults for local testing;
// override all four in production.
// ---------------------------------------------------------
define('SSLCZ_STORE_ID', getenv('SSLCZ_STORE_ID') ?: 'testbox');
define('SSLCZ_STORE_PASSWORD', getenv('SSLCZ_STORE_PASSWORD') ?: 'qwerty');
define('SSLCZ_IS_SANDBOX', filter_var(getenv('SSLCZ_IS_SANDBOX') ?: 'true', FILTER_VALIDATE_BOOLEAN));
define('SSLCZ_API_URL', SSLCZ_IS_SANDBOX
    ? 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php'
    : 'https://securepay.sslcommerz.com/gwprocess/v4/api.php');
define('SSLCZ_VALIDATION_URL', SSLCZ_IS_SANDBOX
    ? 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php'
    : 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php');

// ---------------------------------------------------------
// CORS — only trusted frontend origin(s) may call the API with cookies
// ---------------------------------------------------------
$allowedOrigins = array_filter(array_map('trim', explode(',', getenv('ALLOWED_ORIGINS') ?: $siteUrl)));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// ---------------------------------------------------------
// Baseline security headers
// ---------------------------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/functions.php';
