<?php

declare(strict_types=1);

/**
 * ZapSub front controller. Everything that arrives over HTTP comes through this
 * file; the parent .htaccess keeps the rest of the project out of reach.
 */

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Setup required</title>'
        . '<div style="font:16px/1.6 system-ui;padding:3rem;max-width:40rem;margin:auto">'
        . '<h1 style="font-size:1.3rem;margin:0 0 .6rem">Dependencies are not installed</h1>'
        . '<p style="margin:0 0 1rem">Run these two commands in the project root, then reload:</p>'
        . '<pre style="background:#0f172a;color:#e2e8f0;padding:1rem;border-radius:.6rem;overflow:auto">'
        . "composer install\ncp .env.example .env\ncomposer migrate &amp;&amp; composer seed</pre></div>";
    return;
}

require $autoload;

use App\Core\App;
use App\Core\ErrorHandler;

$app = App::instance($root);

try {
    $response = $app->handle();
} catch (Throwable $exception) {
    $response = ErrorHandler::toResponse($exception, $app->request());
}

$response->send();
