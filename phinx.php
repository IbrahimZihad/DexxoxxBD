<?php

declare(strict_types=1);

/**
 * Phinx entry point. It boots the same .env parser the application uses so the
 * CLI and the web request always agree on which database they are talking to.
 */

use App\Core\Env;

$root = __DIR__;

if (!is_file($root . '/vendor/autoload.php')) {
    fwrite(STDERR, "Dependencies are missing. Run: composer install" . PHP_EOL);
    exit(1);
}

require $root . '/vendor/autoload.php';
Env::load($root . '/.env');

$env = strtolower((string) Env::get('APP_ENV', 'local'));

$connection = static fn (string $suffix): array => [
    // Adapter names are matched case-sensitively by Phinx: it is "mysql".
    'adapter'    => 'mysql',
    'host'       => Env::get('DB_HOST', '127.0.0.1'),
    'name'       => Env::get('DB_NAME', 'zapsub') . $suffix,
    'user'       => Env::get('DB_USER', 'root'),
    'pass'       => (string) Env::get('DB_PASS', ''),
    'port'       => (int) Env::get('DB_PORT', '3307'),
    'charset'    => Env::get('DB_CHARSET', 'utf8mb4'),
    'collation'  => 'utf8mb4_unicode_ci',
];

return [
    'paths' => [
        'migrations' => $root . '/database/migrations',
        'seeds'      => $root . '/database/seeders',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_database'        => $env === 'testing' ? 'testing' : $env,
        'development'             => $connection(''),
        'testing'                 => $connection('_test'),
        'production'              => $connection(''),
    ],
    'version_order' => 'creation',
];
