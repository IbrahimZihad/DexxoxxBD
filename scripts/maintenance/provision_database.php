<?php

declare(strict_types=1);

/**
 * Local/CI database provisioning.
 *
 * Creates the application schema and the test schema, plus a least-privilege
 * MySQL account that can only touch those two databases. Root credentials are
 * read from DB_ROOT_USERNAME / DB_ROOT_PASSWORD (never from .env) so this
 * script can run once and be re-run safely.
 *
 * Usage:
 *   set DB_ROOT_USERNAME=root
 *   set DB_ROOT_PASSWORD=
 *   php scripts/maintenance/provision_database.php [--with-env]
 *
 * `--with-env` also writes .env from .env.example, generating APP_KEY,
 * INVENTORY_ENCRYPTION_KEY, PAYMENT_WEBHOOK_TOKEN and the app user password.
 */

$basePath = dirname(__DIR__, 2);
require $basePath . '/vendor/autoload.php';

use App\Core\Env;

Env::load($basePath . '/.env');

$options = array_slice($argv ?? [], 1);
$writeEnv = in_array('--with-env', $options, true);
$forceEnv = in_array('--rewrite-env', $options, true);

$rootUser = (string) (getenv('DB_ROOT_USERNAME') ?: 'root');
$rootPass = (string) (getenv('DB_ROOT_PASSWORD') !== false ? getenv('DB_ROOT_PASSWORD') : '');

$host = (string) (getenv('DB_HOST') ?: (Env::get('DB_HOST', '127.0.0.1') ?: '127.0.0.1'));
$port = (int) (getenv('DB_PORT') ?: (Env::get('DB_PORT', 3307) ?: 3307));

$appDatabase = (string) (Env::get('DB_DATABASE', 'dexxoxxbd_marketplace') ?: 'dexxoxxbd_marketplace');
$testDatabase = (string) (Env::get('DB_TEST_DATABASE', 'dexxoxxbd_marketplace_test') ?: 'dexxoxxbd_marketplace_test');
$appUser = (string) (Env::get('DB_USERNAME', 'dexxoxx_app') ?: 'dexxoxx_app');

// The app password is generated once and then reused from .env on later runs.
$existingEnv = $writeEnv || is_file($basePath . '/.env') ? (string) @file_get_contents($basePath . '/.env') : '';
preg_match('/^DB_PASSWORD=(.*)$/m', $existingEnv, $matches);
$appPassword = trim($matches[1] ?? '');

if ($appPassword === '' || $forceEnv) {
    $appPassword = 'Dx_' . bin2hex(random_bytes(12));
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
        $rootUser,
        $rootPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $exception) {
    fwrite(STDERR, sprintf(
        "Cannot connect as %s@%s:%d. Set DB_ROOT_USERNAME / DB_ROOT_PASSWORD and retry.\n",
        $rootUser,
        $host,
        $port
    ));
    exit(1);
}

echo "Connected to MySQL at {$host}:{$port}.\n";

foreach ([$appDatabase, $testDatabase] as $database) {
    // Identifiers cannot be bound, so they are validated rather than quoted blindly.
    if (preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
        fwrite(STDERR, "Refusing to create database with an unsafe name: {$database}\n");
        exit(1);
    }

    $pdo->exec(
        'CREATE DATABASE IF NOT EXISTS `' . $database
        . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
    $pdo->exec('ALTER DATABASE `' . $database . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    echo "Database ready: {$database}\n";
}

// MySQL 8 and MariaDB both accept this syntax; `IDENTIFIED BY` alone is fine.
$pdo->exec(sprintf("CREATE USER IF NOT EXISTS '%s'@'localhost' IDENTIFIED BY '%s'", $appUser, $appPassword));
$pdo->exec(sprintf("CREATE USER IF NOT EXISTS '%s'@'127.0.0.1' IDENTIFIED BY '%s'", $appUser, $appPassword));

// If the account already existed with a different password, rotate it to match .env.
foreach (['localhost', '127.0.0.1'] as $h) {
    $pdo->exec(sprintf("ALTER USER '%s'@'%s' IDENTIFIED BY '%s'", $appUser, $h, $appPassword));
}

foreach ([$appDatabase, $testDatabase] as $database) {
    foreach (['localhost', '127.0.0.1'] as $h) {
        // No GRANT OPTION, no global privileges: DML/DDL scoped to these schemas only.
        $pdo->exec(sprintf(
            "GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER, "
            . "CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW, TRIGGER, EVENT "
            . "ON `%s`.* TO '%s'@'%s'",
            $database,
            $appUser,
            $h
        ));
    }
}

$pdo->exec('FLUSH PRIVILEGES');
echo "Least-privilege account ready: {$appUser} (localhost + 127.0.0.1)\n";

if ($writeEnv) {
    $example = (string) file_get_contents($basePath . '/.env.example');

    $replacements = [
        'APP_NAME="Digital Subscription Marketplace"' => 'APP_NAME="DexxoxxBD Digital Subscriptions"',
        'APP_URL=http://localhost/digital-subscription-marketplace/public' => 'APP_URL=http://localhost/DexxoxxBD/public',
        'APP_KEY=' => 'APP_KEY=base64:' . base64_encode(random_bytes(32)),
        'DB_PORT=3306' => 'DB_PORT=' . $port,
        'DB_DATABASE=digital_subscription_marketplace' => 'DB_DATABASE=' . $appDatabase,
        'DB_TEST_DATABASE=digital_subscription_marketplace_test' => 'DB_TEST_DATABASE=' . $testDatabase,
        'DB_USERNAME=root' => 'DB_USERNAME=' . $appUser,
        'DB_PASSWORD=' => 'DB_PASSWORD=' . $appPassword,
        'INVENTORY_ENCRYPTION_KEY=' => 'INVENTORY_ENCRYPTION_KEY=' . base64_encode(random_bytes(32)),
        'ADMIN_EMAIL=' => 'ADMIN_EMAIL=admin@dexxoxxbd.test',
        'ADMIN_PASSWORD=' => 'ADMIN_PASSWORD=ChangeMe-' . bin2hex(random_bytes(4)),
        'MAIL_FROM_ADDRESS=support@example.com' => 'MAIL_FROM_ADDRESS=no-reply@dexxoxxbd.test',
    ];

    foreach ($replacements as $search => $replacement) {
        $example = str_replace($search, $replacement, $example);
    }

    $target = $basePath . '/.env';
    if (is_file($target) && !$forceEnv) {
        fwrite(STDERR, ".env already exists - refusing to overwrite it (use --rewrite-env).\n");
    } else {
        file_put_contents($target, $example);
        @chmod($target, 0600);
        echo "Wrote {$target}\n";
    }
}

echo "\nNext steps:\n";
echo "  vendor/bin/phinx migrate -e development\n";
echo "  vendor/bin/phinx seed:run -e development\n";
exit(0);
