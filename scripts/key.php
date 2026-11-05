<?php

declare(strict_types=1);

/**
 * Prints a fresh APP_KEY. Paste it into .env; it is the AES-256-GCM key for the
 * account details stored against a delivered order, so keep it out of git and out
 * of the database.
 */

$key = bin2hex(random_bytes(32));

echo $key, PHP_EOL;

$envPath = dirname(__DIR__) . '/.env';

if (is_file($envPath)) {
    $contents = (string) file_get_contents($envPath);
    $updated = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $contents);

    if (is_string($updated) && $updated !== $contents) {
        file_put_contents($envPath, $updated);
        echo 'Written to .env' . PHP_EOL;
    } else {
        echo 'Set the APP_KEY line in .env manually.' . PHP_EOL;
    }

    exit(0);
}

echo 'No .env found - create it first: cp .env.example .env' . PHP_EOL;
