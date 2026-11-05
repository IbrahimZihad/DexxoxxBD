<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Session-based authentication. Passwords are Argon2id; a failed lookup still
 * pays a hash verification so response time cannot reveal which emails exist.
 */
final class Auth
{
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$c29tZXNhbHRzbHR3bA$9CQ7rh1t6v1oQ2Rg6pQeQKZ3xUuZ3Wk8bWb1cQd7YqE';

    private static ?array $cachedUser = null;

    private static bool $resolved = false;

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$cachedUser;
        }

        self::$resolved = true;
        $id = (int) Session::get('user_id', 0);

        if ($id <= 0) {
            return self::$cachedUser = null;
        }

        $user = (new User())->findActive($id);

        if ($user === null) {
            // Account deleted or deactivated mid-session: drop the session key so
            // the next request cannot reach a stale identity.
            Session::forget('user_id');

            return self::$cachedUser = null;
        }

        unset($user['password_hash']);

        return self::$cachedUser = $user;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    /** @return array{ok: bool, message: string, code: string} */
    public static function attempt(string $email, string $password): array
    {
        $user = (new User())->findByEmail($email);

        $hash = (string) ($user['password_hash'] ?? self::DUMMY_HASH);
        $valid = password_verify($password, $hash);

        if ($user === null || !$valid) {
            Logger::security('login_failed', ['email' => mb_substr($email, 0, 120), 'ip' => self::ip()]);

            return ['ok' => false, 'code' => 'bad_credentials', 'message' => 'Email or password is wrong.'];
        }

        if ((int) ($user['is_active'] ?? 0) !== 1) {
            return ['ok' => false, 'code' => 'inactive', 'message' => 'This account is disabled. Ask an administrator to re-enable it.'];
        }

        // Transparent upgrade if the algorithm or cost changed since the hash was written.
        if (password_needs_rehash($hash, self::algorithm(), self::options())) {
            (new User())->updatePasswordHash((int) $user['id'], self::hash($password));
        }

        self::login((int) $user['id']);

        return ['ok' => true, 'code' => 'ok', 'message' => 'Welcome back.'];
    }

    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::set('user_id', $userId);
        Session::set('login_ip', self::ip());
        Session::set('login_ua', App::instance()->request()->userAgent());
        self::$cachedUser = null;
        self::$resolved = false;

        (new User())->touchLastLogin($userId);

        RateLimiter::clear('login', self::ip());
    }

    public static function logout(): void
    {
        Session::forget('user_id');
        Session::regenerate();
        self::$cachedUser = null;
        self::$resolved = true;
    }

    public static function role(): string
    {
        $user = self::user();

        return $user === null ? 'guest' : (string) $user['role'];
    }

    public static function isStaff(): bool
    {
        return in_array(self::role(), ['admin', 'staff'], true);
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function hash(string $password): string
    {
        $hash = password_hash($password, self::algorithm(), self::options());

        return $hash === false ? throw new HttpException(500, 'Password hashing failed.') : $hash;
    }

    private static function algorithm(): string
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    /** @return array<string, int> */
    private static function options(): array
    {
        if (self::algorithm() === PASSWORD_BCRYPT) {
            return ['cost' => 12];
        }

        // 64 MB / 4 passes / 1 lane: strong for a small shop and still fast
        // enough that a login does not stall a shared XAMPP box.
        return ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1];
    }

    private static function ip(): string
    {
        try {
            return App::instance()->request()->ip();
        } catch (\Throwable) {
            return 'cli';
        }
    }
}
