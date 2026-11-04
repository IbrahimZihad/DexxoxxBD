<?php

declare(strict_types=1);

/**
 * Application bootstrap. Required by public/index.php, the CLI tools and the
 * test suite so all three share one wiring path.
 */

use App\Core\App;
use App\Core\Config;
use App\Services\Logger;

$basePath = dirname(__DIR__);

require $basePath . '/vendor/autoload.php';

// Errors are logged, never printed, unless APP_DEBUG turns display on later.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $basePath . '/storage/logs/php-error.log');
ini_set('expose_php', '0');
error_reporting(E_ALL);

if (!is_file($basePath . '/.env')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "The application is not configured yet.\n\n";
    echo "1. Copy .env.example to .env\n";
    echo "2. Generate an app key and fill in DB_DATABASE, DB_USERNAME and DB_PASSWORD\n";
    echo "3. Run: vendor/bin/phinx migrate && vendor/bin/phinx seed:run\n";
    exit;
}

$app = App::bootstrap($basePath);

// Environment-derived runtime settings.
date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Dhaka'));

if (Config::get('app.debug', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Convert PHP warnings/notices into the log stream without halting the request.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }
    Logger::write(
        in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED, E_NOTICE, E_USER_NOTICE], true)
            ? Logger::WARNING
            : Logger::ERROR,
        $message,
        ['file' => $file, 'line' => $line],
        'error'
    );
    return true;
});

set_exception_handler(static function (Throwable $exception): void {
    Logger::exception($exception);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'An unexpected error occurred. Please try again.';
});

register_shutdown_function(static function () use ($app): void {
    $error = error_get_last();
    if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    Logger::critical('fatal_error', [
        'message' => $error['message'],
        'file' => $error['file'],
        'line' => $error['line'],
    ], 'error');
});

return $app;
