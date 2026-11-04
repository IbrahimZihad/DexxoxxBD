<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * Sliding-window login throttle backed by the login_attempts table, so limits
 * survive a process restart and apply across every web entry point.
 */
final class LoginRateLimiter
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * @return array{message: string, retry_after: int}|null null when allowed
     */
    public function check(string $identifier, string $ip): ?array
    {
        $maxAttempts = (int) Config::get('security.rate_limiting.login_max_attempts', 5);
        $windowMinutes = (int) Config::get('security.rate_limiting.login_window_minutes', 10);
        $ipCeiling = (int) Config::get('security.rate_limiting.login_ip_max_attempts', 25);

        $since = date('Y-m-d H:i:s', time() - ($windowMinutes * 60));

        $failures = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM login_attempts
             WHERE identifier = :identifier AND successful = 0 AND attempted_at >= :since',
            ['identifier' => self::hashIdentifier($identifier), 'since' => $since]
        );

        if ($failures >= $maxAttempts) {
            $oldest = $this->db->scalar(
                'SELECT MIN(attempted_at) FROM login_attempts
                 WHERE identifier = :identifier AND successful = 0 AND attempted_at >= :since',
                ['identifier' => self::hashIdentifier($identifier), 'since' => $since]
            );
            $retry = max(60, ($windowMinutes * 60) - (time() - strtotime((string) $oldest)));
            return [
                'message' => 'Too many sign-in attempts. Try again in ' . (int) ceil($retry / 60) . ' minute(s).',
                'retry_after' => (int) $retry,
            ];
        }

        $ipFailures = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip = :ip AND successful = 0 AND attempted_at >= :since',
            ['ip' => $ip, 'since' => $since]
        );

        if ($ipFailures >= $ipCeiling) {
            return [
                'message' => 'Too many sign-in attempts from this network. Please try again later.',
                'retry_after' => $windowMinutes * 60,
            ];
        }

        return null;
    }

    public function recordFailure(string $identifier, string $ip): void
    {
        $this->db->insert('login_attempts', [
            'identifier' => self::hashIdentifier($identifier),
            'ip' => $ip,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
            'successful' => 0,
            'attempted_at' => now(),
        ]);
    }

    public function clear(string $identifier, string $ip): void
    {
        $this->db->statement(
            'DELETE FROM login_attempts WHERE identifier = :identifier AND successful = 0',
            ['identifier' => self::hashIdentifier($identifier)]
        );
    }

    /** Record a successful sign-in and stamp the user row. */
    public function touchSuccess(int $userId, ?string $ip = null): void
    {
        $ip ??= (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        $this->db->insert('login_attempts', [
            'identifier' => self::hashIdentifier((string) $userId),
            'ip' => $ip,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
            'successful' => 1,
            'user_id' => $userId,
            'attempted_at' => now(),
        ]);

        $this->db->statement(
            'UPDATE users SET last_login_at = NOW(), last_login_ip = :ip WHERE id = :id',
            ['ip' => $ip, 'id' => $userId]
        );
    }

    /** Store a blind hash so the table never holds raw emails. */
    private static function hashIdentifier(string $identifier): string
    {
        $pepper = (string) Config::get('security.identifier_pepper', '');
        return hash_hmac('sha256', strtolower(trim($identifier)), $pepper !== '' ? $pepper : 'dexxoxx-local-pepper');
    }

    public static function preferredAlgorithm(): string|int
    {
        return defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true)
            ? PASSWORD_ARGON2ID
            : PASSWORD_BCRYPT;
    }

    /**
     * @return array<string, int|string>
     */
    public static function hashOptions(): array
    {
        if (self::preferredAlgorithm() === PASSWORD_BCRYPT) {
            return ['cost' => (int) Config::get('security.password.bcrypt_cost', 12)];
        }

        return [
            'memory_cost' => (int) Config::get('security.password.memory_cost', 65536),
            'time_cost' => (int) Config::get('security.password.time_cost', 4),
            'threads' => (int) Config::get('security.password.threads', 1),
        ];
    }

    /** Prune old attempt rows (maintenance cron). */
    public function purge(int $olderThanHours = 72): int
    {
        return $this->db->statement(
            'DELETE FROM login_attempts WHERE attempted_at < :cutoff',
            ['cutoff' => date('Y-m-d H:i:s', time() - ($olderThanHours * 3600))]
        );
    }
}
