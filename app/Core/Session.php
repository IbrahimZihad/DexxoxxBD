<?php

declare(strict_types=1);

namespace App\Core;

/**
 * PHP session wrapper plus the CSRF token that lives inside it. The cookie is
 * scoped to the app's own base path so a XAMPP install with other projects
 * beside this one never shares or leaks it.
 */
final class Session
{
    private static bool $started = false;

    public static function start(string $cookiePath = '/', bool $secure = false): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;

            return;
        }

        $name = (string) Config::get('app.session.name', 'zapsub_session');
        $lifetime = (int) Config::get('app.session.lifetime', 7200);

        // Session IDs must not ride in the URL.
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');

        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath === '' ? '/' : $cookiePath,
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            // Lax (not Strict): a customer clicking back into checkout from a
            // payment page must keep their cart, while cross-site POSTs still
            // arrive without the cookie.
            'samesite' => 'Lax',
        ]);

        session_start();

        // Age out an idle session by hand so the configured lifetime is the one
        // that actually applies on shared hosting with a shorter gc maxlifetime.
        $last = (int) ($_SESSION['_last_seen'] ?? 0);
        if ($last > 0 && time() - $last > $lifetime) {
            self::flush();
            self::regenerate();
        }
        $_SESSION['_last_seen'] = time();

        if (!isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        self::$started = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function push(string $key, mixed $value): void
    {
        if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        $_SESSION[$key][] = $value;
    }

    public static function flush(): void
    {
        $_SESSION = [];
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // true keeps the session data but issues a new id: call this right
            // after a successful login so a pre-auth fixation attempt is dead.
            @session_regenerate_id(true);
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
    }

    public static function token(): string
    {
        return (string) ($_SESSION['_csrf'] ?? '');
    }

    public static function validateToken(mixed $token): bool
    {
        $current = self::token();

        return is_string($token) && $current !== '' && hash_equals($current, $token);
    }

    /**
     * A failed CSRF check means the token in the browser is stale (or forged).
     * Rotating here forces the next page load to carry a fresh one instead of
     * letting the client hammer the same invalid token.
     */
    public static function rotateCsrfOnFailure(): void
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type][] = $message;
    }

    /** @return array<string, list<string>> */
    public static function pullFlash(): array
    {
        $flash = (array) ($_SESSION['_flash'] ?? []);
        unset($_SESSION['_flash']);

        return $flash;
    }

    /** @param array<string, mixed> $input */
    public static function flashInput(array $input): void
    {
        unset($input['_token'], $input['password'], $input['password_confirmation'], $input['txn_id']);
        $_SESSION['_old'] = array_map(
            static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
            $input
        );
    }

    /** @return array<string, string> */
    public static function pullOld(): array
    {
        $old = (array) ($_SESSION['_old'] ?? []);
        unset($_SESSION['_old']);

        return array_map('strval', $old);
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
            @session_destroy();
        }

        self::$started = false;
    }
}
