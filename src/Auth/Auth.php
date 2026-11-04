<?php
declare(strict_types=1);

namespace App\Auth;

use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Repositories\UserRepository;

final class Auth
{
    /** @var array<string,mixed>|null */
    private static ?array $user = null;
    private static bool $resolved = false;

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }

        $id = Session::userId();

        if ($id !== null) {
            $candidate = UserRepository::find($id);

            if ($candidate !== null && $candidate['status'] === 'active') {
                self::$user = $candidate;
            } else {
                // Suspended, deleted, or a stale id — drop the session state.
                Session::logout();
            }
        }

        self::$resolved = true;

        return self::$user;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? null) === 'admin';
    }

    /** @return array<string,mixed> @throws AuthenticationException */
    public static function requireUser(): array
    {
        $user = self::user();

        if ($user === null) {
            throw new AuthenticationException();
        }

        return $user;
    }

    /** @return array<string,mixed> @throws AuthorizationException */
    public static function requireAdmin(): array
    {
        $user = self::requireUser();

        if ($user['role'] !== 'admin') {
            throw new AuthorizationException('Admin access required.');
        }

        return $user;
    }

    public static function forget(): void
    {
        self::$user = null;
        self::$resolved = false;
    }
}
