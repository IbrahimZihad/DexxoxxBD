<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Day-rotated flat-file logs under storage/logs. Nothing is web-exposed: the
 * parent .htaccess denies the whole project directory except public/.
 */
final class Logger
{
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function exception(Throwable $exception, array $context = []): void
    {
        $context['exception'] = get_class($exception);
        $context['thrown_at'] = $exception->getFile() . ':' . $exception->getLine();

        self::write(
            'ERROR',
            $exception->getMessage(),
            $context + ['trace' => self::trace($exception)]
        );
    }

    /** Security events get their own file so they are easy to grep in a review. */
    public static function security(string $event, array $context = []): void
    {
        self::append('security', sprintf('[%s] %s %s', self::levelOf('WARNING'), $event, self::format($context)));
    }

    public static function mail(string $subject, string $recipient, string $body): void
    {
        self::append('mail', sprintf(
            "[%s] to=%s subject=%s\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            $recipient,
            $subject,
            str_repeat('-', 60),
            $body
        ));
    }

    private static function write(string $level, string $message, array $context): void
    {
        self::append('app', sprintf('[%s] [%s] %s %s', date('Y-m-d H:i:s'), $level, $message, self::format($context)));
    }

    private static function append(string $channel, string $line): void
    {
        $directory = App::instance()->storagePath('logs');

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }

        $file = sprintf('%s/%s-%s.log', $directory, $channel, date('Y-m-d'));

        // FILE_APPEND | LOCK_EX keeps concurrent requests from interleaving lines.
        @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private static function format(array $context): string
    {
        if ($context === []) {
            return '';
        }

        $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $encoded === false ? '' : $encoded;
    }

    private static function levelOf(string $level): string
    {
        return date('Y-m-d H:i:s') . '] [' . $level;
    }

    /** @return list<string> */
    private static function trace(Throwable $exception): array
    {
        $frames = [];

        foreach (array_slice($exception->getTrace(), 0, 10) as $frame) {
            $call = ($frame['file'] ?? '[internal]') . (isset($frame['line']) ? ':' . $frame['line'] : '');
            $frames[] = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '') . ' (' . $call . ')';
        }

        return $frames;
    }
}
