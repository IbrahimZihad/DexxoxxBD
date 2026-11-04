<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Environment (.env) reader. Deliberately dependency-free so the stack stays
 * "native PHP" and so nothing outside this class can dump the raw values.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $variables = [];

    private static bool $loaded = false;

    public static function load(string $file): void
    {
        self::$variables = [];
        self::$loaded = true;

        if (!is_readable($file)) {
            return;
        }

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read the environment file.');
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Optional `export ` prefix for shell compatibility.
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $position = strpos($line, '=');
            if ($position === false) {
                continue;
            }

            $key = trim(substr($line, 0, $position));
            $value = trim(substr($line, $position + 1));
            if ($key === '') {
                continue;
            }

            self::$variables[$key] = self::parseValue($value);
        }

        fclose($handle);
    }

    private static function parseValue(string $value): string
    {
        // Strip trailing inline comment only from unquoted values.
        if ($value !== '' && !in_array($value[0], ['"', "'"], true)) {
            $hash = strpos($value, ' #');
            if ($hash !== false) {
                $value = rtrim(substr($value, 0, $hash));
            }
        }

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $inner = substr($value, 1, -1);
                return $first === '"' ? self::interpolate($inner) : $inner;
            }
        }

        return self::interpolate($value);
    }

    /** Expand ${OTHER_VAR} references against already-read variables. */
    private static function interpolate(string $value): string
    {
        return preg_replace_callback(
            '/\$\{([A-Z0-9_]+)\}/i',
            static fn (array $m): string => self::raw($m[1]) ?? '',
            $value
        ) ?? $value;
    }

    private static function raw(string $key): ?string
    {
        if (array_key_exists($key, self::$variables)) {
            return self::$variables[$key];
        }
        $fromEnv = getenv($key);
        return $fromEnv === false ? null : (string) $fromEnv;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            return $default;
        }

        $value = self::raw($key);
        if ($value === null) {
            return $default;
        }

        return self::cast($value, $default);
    }

    /** Boolean/numeric normalisation so .env "true"/"null" behave sensibly. */
    private static function cast(string $value, mixed $default): mixed
    {
        $lower = strtolower($value);
        if ($lower === 'true' || $lower === '(true)') {
            return true;
        }
        if ($lower === 'false' || $lower === '(false)') {
            return false;
        }
        if ($lower === 'null' || $lower === '(null)') {
            return null;
        }
        if ($value === 'empty') {
            return '';
        }
        if ($default === null && $value !== '' && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return $value;
    }

    /** Names of the loaded variables only - never their values. */
    public static function keys(): array
    {
        return array_keys(self::$variables);
    }
}
