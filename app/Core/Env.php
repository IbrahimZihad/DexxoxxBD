<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env reader. It is deliberately dependency-free because phinx.php
 * loads it before anything else exists.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $vars = [];

    private static bool $loaded = false;

    public static function load(string $path): void
    {
        self::$loaded = true;

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $pair = explode('=', $line, 2);
            if (count($pair) !== 2) {
                continue;
            }

            $key = strtoupper(trim($pair[0]));
            $value = trim($pair[1]);

            if (strlen($value) > 1) {
                $edge = substr($value, 0, 1);
                if (($edge === '"' || $edge === "'") && substr($value, -1) === $edge) {
                    $value = substr($value, 1, -1);
                }
            }

            if ($key !== '') {
                self::$vars[$key] = $value;
            }
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        if (!self::$loaded) {
            // Allow an explicit boot order (App loads the file) while still
            // working when a CLI script forgets to call load().
            self::load(dirname(__DIR__, 2) . '/.env');
        }

        if (array_key_exists($key, self::$vars)) {
            return self::$vars[$key];
        }

        $fromSystem = getenv($key);

        return $fromSystem === false ? $default : (string) $fromSystem;
    }

    /**
     * Read a "attempts,window_seconds" pair, e.g. RATE_LOGIN=5,900.
     *
     * @return array{0: int, 1: int}
     */
    public static function pair(string $key, string $default): array
    {
        $raw = self::get($key, $default);
        $parts = array_map('trim', explode(',', $raw));

        return [max(1, (int) ($parts[0] ?? 0)), max(1, (int) ($parts[1] ?? 0))];
    }
}
