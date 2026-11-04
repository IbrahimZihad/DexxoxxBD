<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Configuration registry. Files under config/ are keyed by filename and
 * addressed with dot notation (e.g. `database.connections.mysql.host`).
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    /** @var array<string, mixed>|null */
    private static ?array $envCache = null;

    public static function load(string $configPath): void
    {
        if (!is_dir($configPath)) {
            throw new RuntimeException('Configuration directory is missing.');
        }

        $files = glob($configPath . '/*.php');
        sort($files);

        foreach ($files as $file) {
            $key = basename($file, '.php');
            $values = require $file;
            if (is_array($values)) {
                self::$items[$key] = $values;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref = &self::$items;

        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        $ref = $value;
    }

    public static function has(string $key): bool
    {
        return self::get($key, '__missing__') !== '__missing__';
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return self::$items;
    }

    /**
     * Environment lookup used by env(). Values are read from the process
     * environment first so a real deployment can override the .env file.
     */
    public static function env(string $key, mixed $default = null): mixed
    {
        if (self::$envCache === null) {
            self::$envCache = [];
        }

        if (array_key_exists($key, self::$envCache)) {
            return self::$envCache[$key];
        }

        $value = Env::get($key, self::phpEnv($key) ?? $default);
        self::$envCache[$key] = $value;

        return $value;
    }

    private static function phpEnv(string $key): ?string
    {
        $value = getenv($key);
        return $value === false ? null : $value;
    }

    /** Force the next env() read back to disk (used by the test suite). */
    public static function flushEnvCache(): void
    {
        self::$envCache = [];
    }
}
