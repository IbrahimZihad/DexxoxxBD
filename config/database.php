<?php

declare(strict_types=1);

return [
    'default' => 'mysql',

    'connections' => [
        'mysql' => [
            'driver' => env('DB_CONNECTION', 'mysql'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int) env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'digital_subscription_marketplace'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => (string) env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'engine' => 'InnoDB',
            'strict' => true,
            // Connection timeout keeps a hung MySQL from pinning an Apache worker.
            'timeout' => 10,
        ],

        'mysql_test' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int) env('DB_PORT', 3306),
            'database' => env('DB_TEST_DATABASE', 'digital_subscription_marketplace_test'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => (string) env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'engine' => 'InnoDB',
            'strict' => true,
            'timeout' => 10,
        ],
    ],

    // Migration/seed connections are resolved by Phinx from these same values.
    'phinx' => [
        'paths' => [
            'migrations' => '%%BASEPATH%%/database/migrations',
            'seeds' => '%%BASEPATH%%/database/seeders',
        ],
        'migration_base_class' => 'Phinx\\Db\\Adapter\\MysqlAdapter',
        'environments' => [
            'default_migration_table' => 'phinxlog',
            'default_environment' => 'development',
        ],
    ],
];
