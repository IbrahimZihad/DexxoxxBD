<?php
declare(strict_types=1);

/**
 * Single entry point required by every public/api/*.php dispatcher.
 * Order matters: autoloader -> env -> config -> errors -> headers -> session.
 */

use App\Auth\Session;
use App\Core\Config;
use App\Core\Env;
use App\Core\Kernel;
use App\Http\Request;
use App\Security\Headers;

if (!defined('DEXXOXX_BASE')) {
    define('DEXXOXX_BASE', dirname(__DIR__));
}

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, 4));
    $file = DEXXOXX_BASE . '/src/' . $relative . '.php';

    if (is_file($file)) {
        require $file;
    }
});

Env::load(DEXXOXX_BASE . '/.env');
Config::load(require DEXXOXX_BASE . '/config/config.php');

date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));
error_reporting(E_ALL);

$debug = (bool) Config::get('app.debug', false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('expose_php', '0');

// Notices and warnings become exceptions so they are logged, never printed.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

$request = new Request();
Headers::apply($request);
Headers::cors($request);
Session::start($request);

return new Kernel($request);
