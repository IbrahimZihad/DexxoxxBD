<?php

declare(strict_types=1);

/**
 * Global view/controller helpers. This file is autoloaded by Composer and stays
 * in the global namespace on purpose: templates read much better with e(),
 * route() and taka() than with static class calls.
 */

use App\Core\App;
use App\Core\Auth;
use App\Core\Crypto;
use App\Core\Settings;
use App\Core\View;
use App\Services\CartService;

if (!function_exists('e')) {
    /** Escape for HTML output. Every dynamic value in a template goes through this. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true);
    }
}

if (!function_exists('app')) {
    function app(): App
    {
        return App::instance();
    }
}

if (!function_exists('view')) {
    function view(): View
    {
        return App::instance()->view();
    }
}

if (!function_exists('url')) {
    /** Absolute URL inside this app, honouring whatever sub-directory it lives in. */
    function url(string $path = ''): string
    {
        return App::instance()->request()->url($path);
    }
}

if (!function_exists('asset')) {
    /**
     * Public URL for a file under public/assets, stamped with its mtime so a
     * rebuilt stylesheet or script reaches visitors who still hold the previous
     * copy in cache. The stamp is a filemtime() of the one named file, and is
     * skipped when that file is absent (a missing asset must not 500 the page).
     */
    function asset(string $path): string
    {
        $rel = 'assets/' . ltrim($path, '/');
        $file = dirname(__DIR__, 2) . '/public/' . $rel;
        $stamp = is_file($file) ? (string) filemtime($file) : '';

        return url($rel) . ($stamp === '' ? '' : '?v=' . $stamp);
    }
}

if (!function_exists('brand_logo')) {
    /**
     * Public URL for the logo named by the `brand.logo` setting, or an empty
     * string when the file is not actually on disk. Every caller is expected to
     * have a drawn fallback, because a missing image in the header reads as a
     * broken site.
     */
    function brand_logo(): string
    {
        $path = trim((string) setting('brand.logo', ''));

        if ($path === '') {
            return '';
        }

        $file = dirname(__DIR__, 2) . '/public/assets/' . ltrim($path, '/');

        return is_file($file) ? asset($path) : '';
    }
}

if (!function_exists('route')) {
    /**
     * @param array<string, scalar> $params
     * @param array<string, scalar> $query
     */
    function route(string $name, array $params = [], array $query = []): string
    {
        return App::instance()->router()->route($name, $params, $query);
    }
}

if (!function_exists('current_url')) {
    function current_url(): string
    {
        return App::instance()->request()->fullUrl();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return \App\Core\Session::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('setting')) {
    function setting(string $key, string $default = ''): string
    {
        return Settings::get($key, $default);
    }
}

if (!function_exists('setting_bool')) {
    function setting_bool(string $key): bool
    {
        return Settings::bool($key);
    }
}

if (!function_exists('auth_user')) {
    /** @return array<string, mixed>|null */
    function auth_user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('auth_check')) {
    function auth_check(): bool
    {
        return Auth::check();
    }
}

if (!function_exists('is_staff')) {
    function is_staff(): bool
    {
        return Auth::isStaff();
    }
}

if (!function_exists('cart_count')) {
    function cart_count(): int
    {
        return CartService::count();
    }
}

if (!function_exists('taka')) {
    /** Taka with a thousands separator; 1234.5 => "৳ 1,234.50". */
    function taka(float|int|string $amount, bool $decimals = true): string
    {
        $symbol = (string) App::instance()->config('app.currency_symbol', '৳');

        return $symbol . ' ' . number_format((float) $amount, $decimals ? 2 : 0);
    }
}

if (!function_exists('old')) {
    /** Previously submitted value for a form field, used to refill after a validation failure. */
    function old(string $field, string $default = ''): string
    {
        $globals = App::instance()->viewGlobals();
        $old = (array) ($globals['old'] ?? []);

        return (string) ($old[$field] ?? $default);
    }
}

if (!function_exists('field_errors')) {
    /** @return list<string> */
    function field_errors(string $field): array
    {
        $globals = App::instance()->viewGlobals();
        $errors = (array) ($globals['errors'] ?? []);

        /** @var list<string> $messages */
        $messages = (array) ($errors[$field] ?? []);

        return $messages;
    }
}

if (!function_exists('has_error')) {
    function has_error(string $field): bool
    {
        return field_errors($field) !== [];
    }
}

if (!function_exists('error_class')) {
    /** Adds a red outline to whichever inputs failed validation. */
    function error_class(string $field, string $extra = ''): string
    {
        return has_error($field) ? $extra . ' ring-2 ring-rose-400' : $extra;
    }
}

if (!function_exists('is_current_path')) {
    function is_current_path(string $path): bool
    {
        $current = App::instance()->request()->path();
        $path = trim($path, '/');

        if ($path === '' || $path === '/') {
            return $current === '';
        }

        return $current === $path || str_starts_with($current, $path . '/');
    }
}

if (!function_exists('time_ago')) {
    function time_ago(string $datetime): string
    {
        $timestamp = strtotime($datetime);

        if ($timestamp === false) {
            return 'recently';
        }

        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'just now';
        }

        if ($diff < 3600) {
            return intdiv($diff, 60) . ' min ago';
        }

        if ($diff < 86400) {
            return intdiv($diff, 3600) . ' h ago';
        }

        if ($diff < 2592000) {
            return intdiv($diff, 86400) . ' d ago';
        }

        return date('d M Y', $timestamp);
    }
}

if (!function_exists('short_text')) {
    function short_text(string $text, int $limit = 120): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit - 1) . '…';
    }
}

if (!function_exists('wa_link')) {
    /** wa.me deep link with the message pre-filled, number taken from settings. */
    function wa_link(string $message = ''): string
    {
        $number = preg_replace('/\D+/', '', setting('whatsapp.number')) ?? '';

        if ($number === '') {
            return 'https://wa.me/';
        }

        $message = $message === '' ? setting('whatsapp.default_message') : $message;

        return 'https://wa.me/' . rawurlencode($number) . '?text=' . rawurlencode($message);
    }
}

if (!function_exists('wa_number')) {
    function wa_number(): string
    {
        return preg_replace('/\D+/', '', setting('whatsapp.number')) ?? '';
    }
}

if (!function_exists('plan_label')) {
    /** "Netflix · 1 month (Personal)" style label used in carts, orders and WhatsApp notes. */
    function plan_label(string $service, string $title): string
    {
        return trim($service . ' - ' . $title, ' -');
    }
}

if (!function_exists('decrypt_note')) {
    function decrypt_note(?string $stored): string
    {
        return Crypto::decrypt($stored);
    }
}

if (!function_exists('status_badge')) {
    /** Tailwind classes for an order/payment status pill; the map is closed so nothing user-controlled reaches the class list. */
    function status_badge(string $status): string
    {
        return match ($status) {
            'paid', 'completed', 'delivered', 'verified', 'active' => 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200',
            'pending_verification', 'processing', 'assigned', 'pending' => 'bg-amber-100 text-amber-800 ring-1 ring-amber-200',
            'cancelled', 'failed', 'rejected', 'refunded', 'unpaid' => 'bg-rose-100 text-rose-800 ring-1 ring-rose-200',
            default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
        };
    }
}

if (!function_exists('status_label')) {
    function status_label(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }
}
