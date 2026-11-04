<?php

declare(strict_types=1);

/**
 * The only PHP entry point exposed to the web server. Everything above this
 * directory (app code, config, .env, storage, vendor) stays outside the docroot.
 */

use App\Core\App;

// The PHP dev server (`php -S`) should still serve real files from here.
if (PHP_SAPI === 'cli-server') {
    $requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $file = __DIR__ . (is_string($requestedPath) ? $requestedPath : '/');
    if ($requestedPath !== '/' && is_file($file)) {
        return false;
    }
}

/** @var App $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';

$app->run();
