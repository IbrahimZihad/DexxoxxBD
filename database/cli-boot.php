<?php
declare(strict_types=1);

/**
 * Shared bootstrap for the database CLI tools. Loads .env and config without
 * touching sessions or HTTP headers, creates the database when it is missing,
 * and adopts the connection into App\Core\Database so the repositories and
 * services can be reused by the seeder.
 */

if (!defined('DEXXOXX_BASE')) {
    define('DEXXOXX_BASE', dirname(__DIR__));
}

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }

    $file = DEXXOXX_BASE . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;

Env::load(DEXXOXX_BASE . '/.env');
Config::load(require DEXXOXX_BASE . '/config/config.php');

date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

$database = (string) Config::get('db.name');

if ($database === '') {
    fwrite(STDERR, "DB_NAME is empty — copy .env.example to .env and set it.\n");
    exit(1);
}

// Used to build the CREATE DATABASE statement, so it has to be a plain identifier.
if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $database)) {
    fwrite(STDERR, "DB_NAME must be 1-64 letters, digits and underscores.\n");
    exit(1);
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', (string) Config::get('db.host'), (int) Config::get('db.port')),
        (string) Config::get('db.user'),
        (string) Config::get('db.pass'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Cannot reach MySQL/MariaDB: " . $e->getMessage() . "\n");
    exit(1);
}

$pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE `' . $database . '`');

Database::adopt($pdo);

return ['pdo' => $pdo, 'database' => $database];
