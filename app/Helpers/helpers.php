<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;

/**
 * Global helper functions. Everything here is a thin, escaping-safe shortcut
 * over the core classes so that views and controllers stay readable.
 */

if (!function_exists('app')) {
    /** Access the application container. */
    function app(?string $key = null): mixed
    {
        $instance = App::instance();
        return $key === null ? $instance : $instance->make($key);
    }
}

if (!function_exists('env')) {
    /** Read a value from the environment (.env) layer. */
    function env(string $key, mixed $default = null): mixed
    {
        return Config::env($key, $default);
    }
}

if (!function_exists('config')) {
    /** Read a value from the merged configuration using dot notation. */
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('e')) {
    /** HTML-escape for output (XSS defence). */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('db')) {
    /** Shared PDO connection. */
    function db(): Database
    {
        return Database::connection();
    }
}

if (!function_exists('url')) {
    /** Build an absolute application URL from a path. */
    function url(string $path = ''): string
    {
        $base = rtrim((string) config('app.url', 'http://localhost'), '/');
        if ($path === '' || $path === '/') {
            return $base . '/';
        }
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /** Build a URL to a file under public/assets. */
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('upload_url')) {
    /** Public URL for an uploaded image path stored relative to public/. */
    function upload_url(?string $path): string
    {
        if ($path === null || trim($path) === '') {
            return asset('images/placeholder.svg');
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return url(ltrim($path, '/'));
    }
}

if (!function_exists('old')) {
    /** Previously submitted form value, with fallback. */
    function old(string $key, mixed $fallback = ''): string
    {
        $flash = Session::flashInput();
        return (string) ($flash[$key] ?? $fallback);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Session::csrfToken();
    }
}

if (!function_exists('csrf_field')) {
    /** Hidden input carrying the CSRF token for POST forms. */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('money')) {
    /** Format an amount in the configured currency (BDT by default). */
    function money(mixed $amount, bool $withSymbol = true): string
    {
        $formatted = number_format((float) $amount, 2, '.', ',');
        if (!$withSymbol) {
            return $formatted;
        }
        $symbol = (string) config('app.currency_symbol', '৳');
        return $symbol . ' ' . $formatted;
    }
}

if (!function_exists('price')) {
    /** Compact shop price formatting: drops trailing .00 for whole takas. */
    function price(mixed $amount): string
    {
        $value = (float) $amount;
        $text = ($value === floor($value))
            ? number_format($value, 0, '.', ',')
            : number_format($value, 2, '.', ',');
        return (string) config('app.currency_symbol', '৳') . ' ' . $text;
    }
}

if (!function_exists('percent_off')) {
    /** Whole-percent discount between two prices, 0 when there is none. */
    function percent_off(mixed $regular, mixed $sale): int
    {
        $regular = (float) $regular;
        if ($regular <= 0 || (float) $sale >= $regular) {
            return 0;
        }
        return (int) round((($regular - (float) $sale) / $regular) * 100);
    }
}

if (!function_exists('t')) {
    /** Translate a message key through the active locale. */
    function t(string $key, array $replace = []): string
    {
        return App::translator()->get($key, $replace);
    }
}

if (!function_exists('current_locale')) {
    function current_locale(): string
    {
        return App::translator()->locale();
    }
}

if (!function_exists('str_random')) {
    /** Cryptographically secure alphanumeric string. */
    function str_random(int $length = 32): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $out = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }
}

if (!function_exists('slugify')) {
    function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        return $slug === '' ? 'item-' . substr(md5($value . microtime(true)), 0, 8) : $slug;
    }
}

if (!function_exists('unique_slug')) {
    /** Append -2, -3, ... until the slug is free in the given table/column. */
    function unique_slug(string $table, string $column, string $slug, ?int $ignoreId = null): string
    {
        $candidate = $slug;
        $suffix = 1;
        while (true) {
            $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :slug";
            $bindings = ['slug' => $candidate];
            if ($ignoreId !== null) {
                $sql .= ' AND `id` <> :id';
                $bindings['id'] = $ignoreId;
            }
            $count = (int) db()->scalar($sql, $bindings);
            if ($count === 0) {
                return $candidate;
            }
            $suffix++;
            $candidate = $slug . '-' . $suffix;
        }
    }
}

if (!function_exists('now')) {
    /** Current timestamp string in the application timezone. */
    function now(?int $timestamp = null): string
    {
        return date('Y-m-d H:i:s', $timestamp ?? time());
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return dirname(__DIR__, 2) . '/storage' . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return dirname(__DIR__, 2) . '/app' . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return dirname(__DIR__, 2) . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return dirname(__DIR__, 2) . '/public' . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }
}

if (!function_exists('view')) {
    /** Render a template to a string. */
    function view(string $template, array $data = []): string
    {
        return App::viewFactory()->render($template, $data);
    }
}

if (!function_exists('request')) {
    function request(): Request
    {
        return App::request();
    }
}

if (!function_exists('auth_check')) {
    function auth_check(): bool
    {
        return App::auth()->check();
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return App::auth()->user();
    }
}

if (!function_exists('can')) {
    /** Server-side permission check used by views for menu visibility only. */
    function can(string $permission): bool
    {
        return App::auth()->can($permission);
    }
}

if (!function_exists('flash')) {
    /** Push a flash message onto the session. */
    function flash(string $type, string $message): void
    {
        Session::flashMessage($type, $message);
    }
}

if (!function_exists('settings')) {
    /** CMS-controlled site settings, cached per request. */
    function settings(): array
    {
        return App::settings();
    }
}

if (!function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return App::settings()[$key] ?? $default;
    }
}

if (!function_exists('cart_count')) {
    /** Number of units in the active cart (session for guests, DB for users). */
    function cart_count(): int
    {
        return App::cart()->countItems();
    }
}
