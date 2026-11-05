<?php

declare(strict_types=1);

use App\Core\Env;

// APP_ENV=testing always means the separate *_test database, in web requests,
// CLI tools and PHPUnit alike. Nothing in a test run can reach the shop data.
$suffix = strtolower(Env::get('APP_ENV', 'local')) === 'testing' ? '_test' : '';

return [
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => (int) Env::get('DB_PORT', '3307'),
    'name' => Env::get('DB_NAME', 'zapsub') . $suffix,
    'user' => Env::get('DB_USER', 'root'),
    'pass' => (string) Env::get('DB_PASS', ''),
    'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
];
