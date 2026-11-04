<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use Throwable;

/**
 * Rotating, day-partitioned file logger. Nothing is written to the web root.
 */
final class Logger
{
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';
    public const CRITICAL = 'critical';

    /** @var array<string, resource> */
    private static array $handles = [];

    public static function channelPath(string $channel): string
    {
        $directory = (string) Config::get('logging.path', base_path('storage/logs'));
        if (!is_dir($directory)) {
            @mkdir($directory, 0770, true);
        }
        return $directory . '/' . preg_replace('/[^a-z0-9_-]/i', '', $channel) . '-' . date('Y-m-d') . '.log';
    }

    public static function write(
        string $level,
        string $message,
        array $context = [],
        string $channel = 'app'
    ): void {
        $level = strtoupper($level);

        // Level threshold filtering (config/logging.php).
        $threshold = strtoupper((string) Config::get('logging.level', 'debug'));
        $order = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3, 'CRITICAL' => 4];
        if (($order[$level] ?? 4) < ($order[$threshold] ?? 0)) {
            return;
        }

        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            self::redact($message),
            $context === [] ? '' : json_encode(
                self::redactArray($context),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
            )
        );

        $path = self::channelPath($channel);
        $handle = self::$handles[$path] ?? @fopen($path, 'a');
        if ($handle === false) {
            return;
        }
        self::$handles[$path] = $handle;
        fwrite($handle, $line);
    }

    public static function debug(string $message, array $context = [], string $channel = 'app'): void
    {
        self::write(self::DEBUG, $message, $context, $channel);
    }

    public static function info(string $message, array $context = [], string $channel = 'app'): void
    {
        self::write(self::INFO, $message, $context, $channel);
    }

    public static function warning(string $message, array $context = [], string $channel = 'app'): void
    {
        self::write(self::WARNING, $message, $context, $channel);
    }

    public static function error(string $message, array $context = [], string $channel = 'app'): void
    {
        self::write(self::ERROR, $message, $context, $channel);
    }

    public static function critical(string $message, array $context = [], string $channel = 'app'): void
    {
        self::write(self::CRITICAL, $message, $context, $channel);
    }

    public static function exception(Throwable $exception, array $context = [], string $channel = 'error'): void
    {
        self::write(
            self::CRITICAL,
            get_class($exception) . ': ' . $exception->getMessage()
                . ' @ ' . $exception->getFile() . ':' . $exception->getLine(),
            array_merge($context, ['trace' => array_slice(explode("\n", $exception->getTraceAsString()), 0, 25)]),
            $channel
        );
    }

    public static function audit(
        string $action,
        ?int $userId,
        array $context = [],
        ?string $ip = null
    ): void {
        // Audit entries are also written to the database by AuditService; this
        // file copy is the tamper-evident trail.
        self::write(
            self::INFO,
            $action,
            array_merge($context, ['user_id' => $userId, 'ip' => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? null)]),
            'audit'
        );
    }

    /** Never log secrets even if a caller passes them in. */
    private static function redact(string $value): string
    {
        $pattern = '/(password|secret|token|api_key|authorization|store_password)'
            . '(["\']?\s*[:=]\s*["\']?)([^"\'\s,}]{3})[^,}"\']*/i';
        return preg_replace($pattern, '$1$2***REDACTED***', $value) ?? $value;
    }

    private static function redactArray(array $context): array
    {
        $sensitive = [
            'password',
            'password_confirmation',
            'secret',
            'token',
            'api_key',
            'store_password',
            'authorization',
            'card',
        ];
        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $context[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $context[$key] = self::redactArray($value);
            }
        }
        return $context;
    }

    /** Rotate logs older than the configured retention (CLI maintenance job). */
    public static function purge(int $retainDays = 30): int
    {
        $directory = (string) Config::get('logging.path', base_path('storage/logs'));
        $removed = 0;
        $cutoff = time() - ($retainDays * 86400);

        foreach (glob($directory . '/*.log') ?: [] as $file) {
            if (filemtime($file) !== false && filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $removed++;
                }
            }
        }

        return $removed;
    }
}
