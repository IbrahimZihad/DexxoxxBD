<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use Closure;
use RuntimeException;

/**
 * File-based cache with a driver interface, so a Redis implementation can be
 * dropped in later without touching call sites.
 */
final class CacheService
{
    /** @var array<string, mixed>|null in-process memo for this request only */
    private ?array $memo = null;

    private string $driver;

    private string $path;

    public function __construct(?string $driver = null, ?string $path = null)
    {
        $this->driver = $driver ?? (string) Config::get('cache.driver', 'file');
        $this->path = $path ?? (string) Config::get('cache.path', base_path('storage/cache'));

        if ($this->driver !== 'file' && $this->driver !== 'none') {
            throw new RuntimeException('Unsupported cache driver: ' . $this->driver);
        }

        if (!is_dir($this->path)) {
            @mkdir($this->path, 0770, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->driver === 'none') {
            return $default;
        }

        $this->memo ??= [];
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $file = $this->file($key);
        if (!is_file($file)) {
            return $this->memo[$key] = $default;
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return $this->memo[$key] = $default;
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload) || !array_key_exists('expires_at', $payload)) {
            return $this->memo[$key] = $default;
        }

        if ($payload['expires_at'] > 0 && $payload['expires_at'] < time()) {
            @unlink($file);
            return $this->memo[$key] = $default;
        }

        return $this->memo[$key] = $payload['value'];
    }

    /** @param int $ttlSeconds 0 = forever */
    public function put(string $key, mixed $value, int $ttlSeconds = 300): bool
    {
        if ($this->driver === 'none') {
            return false;
        }

        $this->memo[$key] = $value;

        $payload = json_encode(
            ['expires_at' => $ttlSeconds > 0 ? time() + $ttlSeconds : 0, 'value' => $value],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        if ($payload === false) {
            return false;
        }

        $file = $this->file($key);
        $directory = dirname($file);
        if (!is_dir($directory)) {
            @mkdir($directory, 0770, true);
        }

        // Atomic replace so concurrent readers never see a half-written file.
        $temporary = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($temporary, $payload, LOCK_EX) === false) {
            @unlink($temporary);
            return false;
        }

        if (!@rename($temporary, $file)) {
            @unlink($temporary);
            return @file_put_contents($file, $payload, LOCK_EX) !== false;
        }

        return true;
    }

    public function remember(string $key, int $ttlSeconds, Closure $callback): mixed
    {
        $hit = $this->get($key, '__miss__');
        if ($hit !== '__miss__') {
            return $hit;
        }

        $value = $callback();
        $this->put($key, $value, $ttlSeconds);
        return $value;
    }

    public function forget(string $key): bool
    {
        unset($this->memo[$key]);
        $file = $this->file($key);
        return is_file($file) ? @unlink($file) : true;
    }

    /** Invalidate a whole tag group by key prefix. */
    public function forgetPrefix(string $prefix): int
    {
        $count = 0;
        $pattern = $this->path . '/' . $this->sanitize($prefix) . '-*.cache';
        foreach (glob($pattern) ?: [] as $file) {
            if (is_file($file) && @unlink($file)) {
                $count++;
            }
        }
        foreach (array_keys($this->memo ?? []) as $key) {
            if (str_starts_with((string) $key, $prefix)) {
                unset($this->memo[$key]);
            }
        }
        return $count;
    }

    public function flush(): int
    {
        $count = 0;
        foreach (glob($this->path . '/*.cache') ?: [] as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }
        $this->memo = [];
        return $count;
    }

    /**
     * Fixed-window counter used by the throttle middleware.
     *
     * @return array{hits: int, retry_after: int}
     */
    public function increment(string $key, int $windowSeconds): array
    {
        $file = $this->file($key);
        $now = time();
        $hits = 1;
        $resetAt = $now + $windowSeconds;

        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $existing = $raw === false ? null : json_decode($raw, true);
            if (is_array($existing) && ($existing['reset_at'] ?? 0) > $now) {
                $hits = (int) ($existing['hits'] ?? 0) + 1;
                $resetAt = (int) $existing['reset_at'];
            }
        }

        @file_put_contents($file, json_encode(['hits' => $hits, 'reset_at' => $resetAt]), LOCK_EX);
        $this->memo = null;

        return ['hits' => $hits, 'retry_after' => max(1, $resetAt - $now)];
    }

    private function file(string $key): string
    {
        // Flat, readable filenames so prefix invalidation can glob them; the
        // hash suffix keeps two keys that sanitize alike from colliding.
        return $this->path . '/' . $this->sanitize($key) . '-' . substr(sha1($key), 0, 12) . '.cache';
    }

    private function sanitize(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $value) ?? '';
    }
}
