<?php

declare(strict_types=1);

/**
 * Phinx configuration. Values come from .env so the same migration set runs
 * against development, the test schema and production without edits.
 */

$basePath = __DIR__;

if (!is_file($basePath . '/.env')) {
    fwrite(STDERR, "Missing .env - copy .env.example to .env first.\n");
    exit(1);
}

$env = static function (string $key, string $default = '') use ($basePath): string {
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        foreach (file($basePath . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }
            $position = strpos($line, '=');
            if ($position === false) {
                continue;
            }
            $value = trim(substr($line, $position + 1));
            if (strlen($value) >= 2
                && (($value[0] === '"' && $value[strlen($value) - 1] === '"')
                    || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))) {
                $value = substr($value, 1, -1);
            }
            $vars[trim(substr($line, 0, $position))] = $value;
        }
    }

    $fromSystem = getenv($key);
    if ($fromSystem !== false && $fromSystem !== '') {
        return $fromSystem;
    }

    return $vars[$key] ?? $default;
};

$connection = static function (string $database) use ($env): array {
    return [
        'adapter' => 'mysql',
        'host' => $env('DB_HOST', '127.0.0.1'),
        'port' => (int) $env('DB_PORT', '3306'),
        'name' => $database,
        'user' => $env('DB_USERNAME', 'root'),
        'pass' => $env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        // Every created table becomes InnoDB unless the migration says otherwise.
        'unix_socket' => null,
    ];
};

return [
    'paths' => [
        'migrations' => $basePath . '/database/migrations',
        'seeds' => $basePath . '/database/seeders',
    ],

    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_database' => 'development',
        'version_order' => 'creation',

        'development' => $connection($env('DB_DATABASE', 'digital_subscription_marketplace')),
        'test' => $connection($env('DB_TEST_DATABASE', 'digital_subscription_marketplace_test')),
        'production' => $connection($env('DB_DATABASE', 'digital_subscription_marketplace')),
    ],

    'version_alias' => [],

    'migration_base_class' => 'DatabaseMigration',

    'templates' => [],

    // Phinx 0.16 keeps the seed/migration bootstrap here.
    'seed_bases' => ['AbstractSeed'],
    'migration_bases' => ['AbstractMigration', 'DatabaseMigration'],
];
