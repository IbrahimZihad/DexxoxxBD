<?php
declare(strict_types=1);

namespace App\Auth;

use App\Core\Config;
use App\Http\Request;

final class Session
{
    private static bool $started = false;

    public static function start(Request $request): void
    {
        if (self::$started || PHP_SAPI === 'cli') {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        // Session ids must be unguessable and unused ids must not be accepted.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');

        session_name(Config::get('session.name'));
        session_set_cookie_params([
            'lifetime' => (int) Config::get('session.lifetime'),
            'path' => '/',
            'domain' => '',
            'secure' => $request->isSecure(),
            'httponly' => true,
            // SameSite=None is only valid over HTTPS; split-origin deploys need it,
            // same-origin deploys get the stricter Lax.
            'samesite' => $request->isSecure() ? 'None' : 'Lax',
        ]);

        session_start();
        self::$started = true;

        self::enforceIdleTimeout();
    }

    private static function enforceIdleTimeout(): void
    {
        $idle = (int) Config::get('session.idle_timeout');
        $lastSeen = $_SESSION['last_seen'] ?? null;

        if ($idle > 0 && is_int($lastSeen) && (time() - $lastSeen) > $idle) {
            self::logout();
            return;
        }

        $_SESSION['last_seen'] = time();
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function login(int $userId): void
    {
        self::regenerate();
        $_SESSION['user_id'] = $userId;
        $_SESSION['last_seen'] = time();
        unset($_SESSION['cart']);
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function userId(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function tokenMatches(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}
