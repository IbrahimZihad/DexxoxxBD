<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Session;

/**
 * Mirrors PHP sessions into MySQL so "signed in devices" can be listed and
 * revoked, without exposing the raw session id.
 */
final class UserSessionRecorder
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function record(int $userId, string $guard = 'web'): void
    {
        $lifetime = (int) Config::get('security.session.lifetime', 7200);

        $this->db->insert('user_sessions', [
            'user_id' => $userId,
            'guard' => $guard,
            'session_hash' => self::hash(Session::isActive() ? session_id() : ''),
            'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
            'last_activity' => now(),
            'expires_at' => date('Y-m-d H:i:s', time() + $lifetime),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function close(int $userId, string $guard = 'web'): void
    {
        $this->db->statement(
            'UPDATE user_sessions SET revoked_at = NOW(), updated_at = NOW()
             WHERE user_id = :uid AND guard = :guard AND session_hash = :hash AND revoked_at IS NULL',
            [
                'uid' => $userId,
                'guard' => $guard,
                'hash' => self::hash(Session::isActive() ? session_id() : ''),
            ]
        );
    }

    /** @return list<array<string, mixed>> */
    public function listFor(int $userId): array
    {
        return $this->db->select(
            'SELECT id, guard, ip_address, user_agent, last_activity, expires_at, revoked_at, created_at
             FROM user_sessions WHERE user_id = :uid ORDER BY revoked_at IS NULL DESC, last_activity DESC LIMIT 50',
            ['uid' => $userId]
        );
    }

    public function revoke(int $userId, int $sessionId): void
    {
        $this->db->statement(
            'UPDATE user_sessions SET revoked_at = NOW(), updated_at = NOW()
             WHERE id = :id AND user_id = :uid',
            ['id' => $sessionId, 'uid' => $userId]
        );
    }

    public function revokeAllExceptCurrent(int $userId, string $guard = 'web'): int
    {
        return $this->db->statement(
            'UPDATE user_sessions SET revoked_at = NOW(), updated_at = NOW()
             WHERE user_id = :uid AND guard = :guard AND session_hash <> :hash AND revoked_at IS NULL',
            [
                'uid' => $userId,
                'guard' => $guard,
                'hash' => self::hash(Session::isActive() ? session_id() : ''),
            ]
        );
    }

    /** Drop every row for a user (used when an account is suspended). */
    public function revokeAll(int $userId): int
    {
        return $this->db->statement(
            'UPDATE user_sessions SET revoked_at = NOW(), updated_at = NOW() WHERE user_id = :uid AND revoked_at IS NULL',
            ['uid' => $userId]
        );
    }

    public function purgeExpired(): int
    {
        return $this->db->statement(
            'DELETE FROM user_sessions WHERE expires_at < :now OR revoked_at < :old',
            ['now' => now(), 'old' => date('Y-m-d H:i:s', time() - 86400 * 30)]
        );
    }

    private static function hash(string $sessionId): string
    {
        return $sessionId === '' ? hash('sha256', 'empty') : hash('sha256', $sessionId);
    }
}
