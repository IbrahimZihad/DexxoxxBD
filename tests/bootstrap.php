<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * It pins APP_ENV=testing (which config/database.php turns into the separate
 * <name>_test schema), creates that schema when it is missing and migrates it
 * through the same Phinx files the app uses. Nothing here seeds demo data, and
 * nothing here can touch the development database.
 */

use App\Core\Config;
use App\Core\Env;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

Env::load($root . '/.env');
Env::set('APP_ENV', 'testing');
Env::set('APP_DEBUG', 'false');

Config::load($root . '/config');

$config = (array) Config::get('database');
$databaseName = (string) $config['name'];

if (!str_ends_with($databaseName, '_test')) {
    fwrite(STDERR, "Refusing to run tests: the resolved database is \"{$databaseName}\", not a *_test schema." . PHP_EOL);
    exit(1);
}

$serverDsn = sprintf(
    'mysql:host=%s;port=%d;charset=%s',
    (string) $config['host'],
    (int) $config['port'],
    (string) $config['charset']
);

try {
    $server = new PDO($serverDsn, (string) $config['user'], (string) $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $exception) {
    fwrite(STDERR, 'Tests need MySQL running on ' . $serverDsn . '. ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

// The identifier is the configured DB_NAME plus the fixed _test suffix, never
// request data; the underscore and the guard above keep it that way.
$server->exec(sprintf('create database if not exists `%s` character set utf8mb4 collate utf8mb4_unicode_ci', $databaseName));

$tables = (int) $server->query(
    sprintf("select count(*) from information_schema.tables where table_schema = '%s'", $databaseName)
)->fetchColumn();

date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Dhaka'));

if ($tables < 10) {
    $command = sprintf(
        '%s %s migrate -e testing --no-interaction',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($root . '/vendor/bin/phinx')
    );

    exec($command . ' 2>&1', $output, $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, 'Test database migration failed:' . PHP_EOL . implode(PHP_EOL, $output) . PHP_EOL);
        exit(1);
    }
}

// The app is booted so the named-route table and the storage directories exist
// for the tests that call route() or the middleware stack.
App\Core\App::instance($root);
App\Core\App::instance()->boot();
