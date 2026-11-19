<?php

declare(strict_types=1);

/**
 * DexxozzBD front controller. Everything that arrives over HTTP comes through this
 * file; application code, configuration and dependencies stay in the directory
 * above public_html and are never served as web files.
 */

// Split layout (public_html + app folder elsewhere): uncomment and set this.
// define('APP_ROOT', '/home/USER/dexxozzbd');

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';

/**
 * Webuzo/cPanel primary-domain fallback.
 *
 * The primary domain's document root is fixed at public_html, so this file may
 * be copied there while the application stays in a sibling directory. Resolve
 * the application root from APP_ROOT when the host sets it, otherwise try the
 * conventional folder names. The normal layout (docroot = public_html/) needs none
 * of this and resolves on the first check above.
 */
if (!is_file($autoload)) {
    $candidates = [];
    $configured = trim((string) (getenv('APP_ROOT') ?: ($_SERVER['APP_ROOT'] ?? '')));

    if ($configured !== '') {
        $candidates[] = rtrim($configured, '/\\');
    }

    if (defined('APP_ROOT') && is_string(APP_ROOT) && trim(APP_ROOT) !== '') {
        $candidates[] = rtrim(trim(APP_ROOT), '/\\');
    }

    $candidates[] = dirname(__DIR__) . '/dexxozzbd';
    $candidates[] = dirname(__DIR__) . '/DexxoxxBD';

    foreach ($candidates as $candidate) {
        if (is_file($candidate . '/vendor/autoload.php')) {
            $root = $candidate;
            $autoload = $candidate . '/vendor/autoload.php';
            break;
        }
    }
}

if (!is_file($autoload)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Setup required</title>'
        . '<div style="font:16px/1.6 system-ui;padding:3rem;max-width:40rem;margin:auto">'
        . '<h1 style="font-size:1.3rem;margin:0 0 .6rem">Dependencies are not installed</h1>'
        . '<p style="margin:0 0 1rem">Run these commands in the project root, then reload:</p>'
        . '<pre style="background:#0f172a;color:#e2e8f0;padding:1rem;border-radius:.6rem;overflow:auto">'
        . "composer install\ncp .env.example .env\ncomposer migrate &amp;&amp; composer seed</pre>"
        . '<p style="margin:1rem 0 0">If this file was copied into public_html, point the app at its real '
        . 'folder with <code>SetEnv APP_ROOT /home/USER/dexxozzbd</code> in .htaccess or by defining '
        . '<code>APP_ROOT</code> at the top of this file.</p></div>';
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
