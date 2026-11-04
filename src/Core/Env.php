<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env reader. Real process environment variables always win over the
 * file so hosting panels (cPanel/Webuzo/Docker-free VPS) can override without edits.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $file = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        self::$loaded = true;

        if (!is_readable($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if (strlen($value) > 1 && ($value[0] === '"' || $value[0] === "'") && $value[0] === substr($value, -1)) {
                $value = substr($value, 1, -1);
            }

            if ($key !== '') {
                self::$file[$key] = $value;
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (!self::$loaded) {
            self::load(dirname(__DIR__, 2) . '/.env');
        }

        $fromProcess = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

        if (is_string($fromProcess) && $fromProcess !== '') {
            return $fromProcess;
        }

        return self::$file[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return $value === null || !is_numeric($value) ? $default : (int) $value;
    }

    /** @return list<string> */
    public static function list(string $key, array $default = []): array
    {
        $value = self::get($key);

        if ($value === null || trim($value) === '') {
            return $default;
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn ($v) => $v !== ''));
    }
}
