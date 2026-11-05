<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sliding-window rate limiter backed by files under storage/rate.
 *
 * A file store is deliberate: the limiter has to keep working when MySQL is down
 * (that is when a brute-force attempt is most likely to succeed), and it needs
 * sub-second granularity that a shared table would give slowly. Each bucket is
 * one JSON array of timestamps, rewritten under an exclusive lock.
 */
final class RateLimiter
{
    /**
     * @return array{allowed: bool, remaining: int, retry_after: int}
     */
    public static function attempt(string $bucket, string $identity, int $maxAttempts, int $windowSeconds): array
    {
        $file = self::path($bucket, $identity);
        $now = time();

        $attempts = self::read($file, $now, $windowSeconds);
        $attempts[] = $now;

        $allowed = count($attempts) <= $maxAttempts;

        // When the caller is blocked, the rejected attempt is still recorded so
        // hammering cannot reset the window; but we do not let the array grow
        // without bound.
        if (!$allowed) {
            array_shift($attempts);
        }

        self::write($file, $attempts);

        $oldest = $attempts[0] ?? $now;
        $retryAfter = $allowed ? 0 : max(1, $windowSeconds - ($now - $oldest));
        $remaining = max(0, $maxAttempts - count($attempts));

        return ['allowed' => $allowed, 'remaining' => $remaining, 'retry_after' => $retryAfter];
    }

    public static function remaining(string $bucket, string $identity, int $maxAttempts, int $windowSeconds): int
    {
        return max(0, $maxAttempts - count(self::read(self::path($bucket, $identity), time(), $windowSeconds)));
    }

    /** Wipe a bucket after a success, e.g. once a login actually works. */
    public static function clear(string $bucket, string $identity): void
    {
        $file = self::path($bucket, $identity);

        if (is_file($file)) {
            @unlink($file);
        }
    }

    /** Remove limiter files older than a day; called opportunistically. */
    public static function garbageCollect(): void
    {
        $directory = App::instance()->storagePath('rate');
        $deadline = time() - 86400;

        foreach (glob($directory . '/*.json') ?: [] as $file) {
            if ((int) @filemtime($file) < $deadline) {
                @unlink($file);
            }
        }
    }

    private static function path(string $bucket, string $identity): string
    {
        $directory = App::instance()->storagePath('rate');

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        return $directory . '/' . sha1($bucket . '|' . $identity) . '.json';
    }

    /** @return list<int> */
    private static function read(string $file, int $now, int $window): array
    {
        if (!is_file($file)) {
            return [];
        }

        $raw = @file_get_contents($file, false, null, 0, 65536);
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(
            array_map('intval', $decoded),
            static fn (int $timestamp): bool => $timestamp > $now - $window
        ));
    }

    /** @param list<int> $attempts */
    private static function write(string $file, array $attempts): void
    {
        @file_put_contents($file, json_encode($attempts), LOCK_EX);
    }
}
