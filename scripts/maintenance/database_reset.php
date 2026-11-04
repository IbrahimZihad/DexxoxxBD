<?php

/**
 * Guarded development reset: drop every table in the configured schema, rebuild
 * it from the Phinx migrations, and re-seed the demo data.
 *
 * This is a development tool. It refuses to run when APP_ENV says the schema is
 * shared or live, and it needs --yes even locally, because the drop is not
 * recoverable except from a backup.
 *
 *   php scripts/maintenance/database_reset.php --env=development --yes
 *
 * Add --keep-data to only re-run the migrations and seeders on top of what is
 * already there (that is safe, because both are idempotent).
 */

declare(strict_types=1);

use App\Core\Config;

$basePath = dirname(__DIR__, 2);

require $basePath . '/vendor/autoload.php';

if (!is_file($basePath . '/.env')) {
    fwrite(STDERR, "Missing .env - copy .env.example to .env first.\n");
    exit(1);
}

\App\Core\Env::load($basePath . '/.env');
Config::load($basePath . '/config');

$options = getopt('', ['env::', 'yes', 'keep-data']);
$environment = (string) ($options['env'] ?? (Config::get('app.env') ?: 'development'));
$php = PHP_BINARY;
$phinx = $basePath . '/vendor/bin/phinx';

$abort = static function (string $reason): never {
    fwrite(STDERR, 'Refusing to reset: ' . $reason . "\n");
    exit(2);
};

if (in_array(strtolower($environment), ['production', 'live'], true)) {
    $abort('the ' . $environment . ' environment is not a development schema.');
}

if (!isset($options['yes'])) {
    $abort('pass --yes to confirm that ' . $environment . ' may be rebuilt from scratch.');
}

$applicationEnv = strtolower((string) Config::env('APP_ENV', 'local'));
if (in_array($applicationEnv, ['production', 'live'], true)) {
    $abort('APP_ENV is ' . $applicationEnv . '; this script only rebuilds development data.');
}

$database = (string) ($environment === 'test'
    ? Config::env('DB_TEST_DATABASE', '')
    : Config::env('DB_DATABASE', ''));

echo 'Target environment: ' . $environment . ' (database ' . ($database !== '' ? $database : 'unknown') . ")\n";

/**
 * passthru() takes one shell string, so every token is whitelisted before it is
 * quoted. The pattern excludes every cmd.exe metacharacter, which means an
 * unexpected --env value aborts here instead of becoming shell syntax.
 */
$safeToken = static function (string $token) use ($abort): string {
    if (preg_match('/^[A-Za-z0-9.:_\/\\\\-]+=?[A-Za-z0-9.:_\/\\\\-]*$/', $token) !== 1) {
        $abort('the token "' . $token . '" is not a safe path, option or migration name.');
    }

    return '"' . $token . '"';
};

$run = static function (array $argv) use ($php, $safeToken): int {
    array_unshift($argv, $php);
    $command = implode(' ', array_map($safeToken, $argv));
    echo '+ ' . $command . "\n";
    // Never pass secrets through the command line, so Phinx re-reads .env itself.
    passthru($command, $status);

    return (int) $status;
};

if (!isset($options['keep-data'])) {
    $status = $run([$phinx, 'rollback', '-e', $environment, '-t', '0']);
    if ($status !== 0) {
        echo "Rollback reported a non-zero status; the schema may already have been empty.\n";
    }
}

if ($run([$phinx, 'migrate', '-e', $environment]) !== 0) {
    exit(1);
}

if ($run([$phinx, 'seed:run', '-e', $environment, '-s', 'DatabaseSeeder']) !== 0) {
    exit(1);
}

if ($run([$phinx, 'status', '-e', $environment]) !== 0) {
    exit(1);
}

if ($run([$basePath . '/scripts/maintenance/verify_schema.php', '--env=' . $environment]) !== 0) {
    exit(1);
}

echo "\nReset complete. Migrations applied and demo data seeded in " . $environment . ".\n";
