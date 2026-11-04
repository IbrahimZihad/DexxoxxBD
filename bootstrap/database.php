<?php

declare(strict_types=1);

/**
 * Database bootstrap for CLI entry points (phinx, cron scripts, tests).
 * Returns the shared connection without starting a session or routing.
 */

use App\Core\Config;
use App\Core\Database;

$basePath = dirname(__DIR__);

require $basePath . '/vendor/autoload.php';

if (is_file($basePath . '/.env')) {
    \App\Core\Env::load($basePath . '/.env');
    Config::load($basePath . '/config');
}

date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Dhaka'));

$connection = (string) (getenv('DB_CONNECTION_TARGET') ?: 'mysql');

return Database::connection(
    (array) Config::get('database.connections.' . $connection, [])
);
