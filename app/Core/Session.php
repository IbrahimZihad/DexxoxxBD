<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session handling: secure cookie parameters, storage outside the web root,
 * regeneration against fixation, flash bags and the CSRF token.
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || self::isActive()) {
            self::$started = true;
            return;
        }

        $config = (array) Config::get('security.session', []);
        $lifetime = (int) ($config['lifetime'] ?? (int) env('SESSION_LIFETIME', 120) * 60);
        $path = (string) ($config['path'] ?? base_path('storage/sessions'));

        if (!is_dir($path)) {
            @mkdir($path, 0770, true);
        }

        if (is_dir($path) && is_writable($path)) {
            session_save_path($path);
            ini_set('session.use_files', '1');
        }

        // Session cookie must never be readable by JavaScript.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);

        session_set_cookie_params([
            'lifetime' => (int) ($config['expire_on_browser_close'] ?? true) ? 0 : $lifetime,
            'path' => (string) ($config['cookie_path'] ?? '/'),
            'domain' => (string) ($config['cookie_domain'] ?? ''),
            'secure' => self::forceSecureCookies(),
            'httponly' => true,
            'samesite' => (string) ($config['samesite'] ?? 'Lax'),
        ]);

        $name = (string) ($config['name'] ?? 'dexxoxx_session');
        session_name($name);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        self::$started = true;
        self::enforceIdleTimeout($lifetime);
        self::ensureCsrfToken();
    }

    private static function forceSecureCookies(): bool
    {
        // Always secure over HTTPS; configurable to force it behind proxies too.
        $isHttps = (($_SERVER['HTTPS'] ?? '') === 'on')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        return $isHttps || (bool) Config::get('security.force_secure_cookies', false);
    }

    private static function enforceIdleTimeout(int $lifetime): void
    {
        $last = $_SESSION['_last_activity'] ?? null;
        if ($last !== null && (time() - (int) $last) > $lifetime) {
            self::destroy();
            self::start();
            return;
        }
        $_SESSION['_last_activity'] = time();
    }

    public static function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::start();
        return array_key_exists($key, $_SESSION);
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function regenerate(bool $destroyOld = true): void
    {
        if (!self::isActive()) {
            self::start();
            return;
        }
        // Called after every privilege change (login, logout, role change).
        session_regenerate_id($destroyOld);
    }

    public static function destroy(): void
    {
        if (!self::isActive()) {
            return;
        }
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
        session_destroy();
        self::$started = false;
    }

    public static function csrfToken(): string
    {
        self::start();
        self::ensureCsrfToken();
        return (string) $_SESSION['_csrf_token'];
    }

    private static function ensureCsrfToken(): void
    {
        self::start();
        if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function verifyCsrfToken(?string $token): bool
    {
        if ($token === null || $token === '' || !isset($_SESSION['_csrf_token'])) {
            return false;
        }
        return hash_equals((string) $_SESSION['_csrf_token'], $token);
    }

    /**
     * A failed token usually means a stale or forged form: issue a fresh token
     * so the next legitimate submission cannot replay the bad one.
     */
    public static function rotateCsrfOnFailure(): void
    {
        self::start();
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    public static function flash(string $key, mixed $value): void
    {
        self::start();
        $_SESSION['_flash'][$key] = $value;
    }

    public static function flashMessage(string $type, string $message): void
    {
        self::start();
        $_SESSION['_flash']['messages'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type: string, message: string}> */
    public static function takeMessages(): array
    {
        self::start();
        $messages = $_SESSION['_flash']['messages'] ?? [];
        unset($_SESSION['_flash']['messages']);
        return is_array($messages) ? array_values($messages) : [];
    }

    public static function flashInput(array $input): void
    {
        self::start();
        unset($input['_token'], $input['_method'], $input['password'], $input['password_confirmation']);
        $_SESSION['_flash']['input'] = $input;
    }

    /** @return array<string, mixed> */
    public static function flashInputValue(): array
    {
        self::start();
        $value = $_SESSION['_flash']['input'] ?? [];
        unset($_SESSION['_flash']['input']);
        return is_array($value) ? $value : [];
    }

    public static function take(string $key): mixed
    {
        self::start();
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /** @return array<string, mixed> */
    public static function errorsFor(): array
    {
        self::start();
        $errors = $_SESSION['_flash']['errors'] ?? [];
        unset($_SESSION['_flash']['errors']);
        return is_array($errors) ? $errors : [];
    }

    public static function flashErrors(array $errors): void
    {
        self::start();
        $_SESSION['_flash']['errors'] = $errors;
    }

    /** @return array<string, mixed> raw session array, for debugging only */
    public static function all(): array
    {
        self::start();
        return $_SESSION;
    }
}
