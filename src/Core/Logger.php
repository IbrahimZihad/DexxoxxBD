<?php
declare(strict_types=1);

namespace App\Core;

final class Logger
{
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    /** Never log secrets or raw request bodies — call sites pass whitelisted scalars. */
    private static function write(string $level, string $message, array $context): void
    {
        $path = (string) Config::get('storage.log_file');
        $dir = dirname($path);

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $context = array_filter(
            $context,
            static fn ($value) => is_scalar($value) || $value === null
        );

        $line = sprintf(
            "[%s] %s %s | %s%s%s",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $_SERVER['REMOTE_ADDR'] ?? 'cli',
            $context ? ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '',
            "\n"
        );

        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }
}
