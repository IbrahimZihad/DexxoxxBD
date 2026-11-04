<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;
use App\Services\AuditService;
use App\Services\LoginRateLimiter;
use App\Services\RbacService;
use App\Services\UserSessionRecorder;
use RuntimeException;
use Throwable;

/**
 * Session-based authentication for both customers and staff. Authorization is
 * never inferred from the session array alone - permissions come from MySQL.
 */
final class Auth
{
    private static ?self $instance = null;

    /** @var array<string, mixed>|null */
    private ?array $user = null;

    private bool $resolved = false;

    private ?RbacService $rbac = null;

    private string $guard = 'web';

    private function __construct(private User $users)
    {
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(new User());
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function guard(string $guard): self
    {
        $this->guard = $guard;
        return $this;
    }

    public function currentGuard(): string
    {
        return $this->guard;
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $id = Session::get('user_id_' . $this->guard);

        if (is_int($id) || (is_string($id) && ctype_digit($id))) {
            $user = $this->users->findActiveById((int) $id);
            if ($user !== null) {
                $this->user = $user;
            } else {
                // Account vanished or was hard-deleted: drop the stale marker.
                Session::forget('user_id_' . $this->guard);
            }
        }

        return $this->user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        $user = $this->user();
        return $user === null ? null : (int) $user['id'];
    }

    /**
     * @param array{email?: string, username?: string, password?: string} $credentials
     * @return array{ok: bool, error?: string, retry_after?: int}
     */
    public function attempt(array $credentials, bool $rememberBrowser = true): array
    {
        $identifier = trim((string) ($credentials['email'] ?? $credentials['username'] ?? ''));
        $password = (string) ($credentials['password'] ?? '');

        if ($identifier === '' || $password === '') {
            return ['ok' => false, 'error' => 'Enter your email or username and password.'];
        }

        $limiter = new LoginRateLimiter();
        $blocked = $limiter->check($identifier, request()->ip());
        if ($blocked !== null) {
            return ['ok' => false, 'error' => $blocked['message'], 'retry_after' => $blocked['retry_after']];
        }

        $user = $this->users->findByIdentifier($identifier);

        // Compare against a real hash even when the account is unknown, so the
        // response time does not reveal whether the identifier exists.
        $hash = (string) ($user['password'] ?? self::dummyHash());

        if ($user === null || !password_verify($password, $hash)) {
            $limiter->recordFailure($identifier, request()->ip());
            AuditService::record('auth.login_failed', null, ['identifier' => $identifier]);
            return ['ok' => false, 'error' => 'Those credentials do not match our records.'];
        }

        if ((int) ($user['status'] ?? 0) !== 1) {
            $limiter->recordFailure($identifier, request()->ip());
            return ['ok' => false, 'error' => 'This account is suspended. Contact support for help.'];
        }

        $limiter->clear($identifier, request()->ip());

        if (password_needs_rehash($hash, PASSWORD_ARGON2ID, LoginRateLimiter::hashOptions())) {
            $this->users->replacePassword((int) $user['id'], $password);
        }

        $this->login($user, $rememberBrowser);

        return ['ok' => true];
    }

    /** @param array<string, mixed> $user */
    public function login(array $user, bool $recordSession = true): void
    {
        // Privilege change: the session id must change first.
        Session::regenerate();
        Session::set('user_id_' . $this->guard, (int) $user['id']);
        Session::set('logged_in_at', now());
        if (!empty($user['locale'])) {
            Session::set('locale', (string) $user['locale']);
        }

        $this->user = $user;
        $this->resolved = true;

        if ($recordSession) {
            (new UserSessionRecorder())->record((int) $user['id'], $this->guard);
        }

        (new LoginRateLimiter())->touchSuccess((int) $user['id']);
        AuditService::record('auth.login', (int) $user['id'], ['guard' => $this->guard]);
    }

    /** Bind an already-authenticated user to a freshly created session. */
    public function forceLogin(int $userId): bool
    {
        $user = $this->users->findActiveById($userId);
        if ($user === null) {
            return false;
        }
        $this->login($user);
        return true;
    }

    public function logout(): void
    {
        $userId = $this->id();
        if ($userId !== null) {
            (new UserSessionRecorder())->close($userId, $this->guard);
            AuditService::record('auth.logout', $userId, ['guard' => $this->guard]);
        }

        Session::forget('user_id_' . $this->guard);
        $this->user = null;
        $this->resolved = true;
        Session::regenerate();
    }

    public function can(string $permission): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        try {
            $this->rbac ??= new RbacService();
            return $this->rbac->userCan((int) $user['id'], $permission);
        } catch (Throwable) {
            // A broken permission lookup must never grant access.
            return false;
        }
    }

    public function hasRole(string $roleSlug): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        $this->rbac ??= new RbacService();
        return $this->rbac->userHasRole((int) $user['id'], $roleSlug);
    }

    /** @return list<string> */
    public function roles(): array
    {
        $user = $this->user();
        if ($user === null) {
            return [];
        }
        $this->rbac ??= new RbacService();
        return $this->rbac->rolesFor((int) $user['id']);
    }

    /**
     * Confirm the current user's password for sensitive actions (re-auth).
     */
    public function confirmPassword(string $password): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        $row = $this->users->find((int) $user['id']);
        return $row !== null && password_verify($password, (string) $row['password']);
    }

    public function hashPassword(string $password): string
    {
        $algorithm = LoginRateLimiter::preferredAlgorithm();
        $hash = password_hash($password, $algorithm, LoginRateLimiter::hashOptions());
        if ($hash === false) {
            throw new RuntimeException('Password hashing failed.');
        }
        return $hash;
    }

    /**
     * A valid Argon2id hash of a throwaway value, generated once per process,
     * used to equalise login timing for unknown accounts.
     */
    private static function dummyHash(): string
    {
        static $hash = null;
        if ($hash === null) {
            $hash = password_hash(bin2hex(random_bytes(16)), LoginRateLimiter::preferredAlgorithm(), LoginRateLimiter::hashOptions());
        }
        return (string) $hash;
    }
}
