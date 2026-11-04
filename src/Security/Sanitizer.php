<?php
declare(strict_types=1);

namespace App\Security;

final class Sanitizer
{
    /** Single-line user text: no tags, no control characters, collapsed whitespace. */
    public static function text(mixed $value, int $max = 255): string
    {
        $text = self::raw($value);
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        $text = self::invisible($text);
        $text = preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '';
        $text = trim($text);

        return self::cut($text, $max);
    }

    /** Multi-line text (descriptions, notes): keeps paragraph breaks, drops markup. */
    public static function multiline(mixed $value, int $max = 5000): string
    {
        $text = self::invisible(strip_tags(self::raw($value)));
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? '';
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? '';

        return self::cut(trim($text), $max);
    }

    public static function email(mixed $value): string
    {
        return self::cut(strtolower(trim(self::raw($value))), 190);
    }

    /** Keeps digits and an optional leading + so BD numbers stay readable. */
    public static function phone(mixed $value): string
    {
        $digits = preg_replace('/(?!^\+)[^0-9]/', '', self::raw($value)) ?? '';

        return self::cut(trim($digits), 20);
    }

    public static function digits(mixed $value, int $max = 40): string
    {
        return self::cut(preg_replace('/[^0-9A-Za-z]/', '', self::raw($value)) ?? '', $max);
    }

    /**
     * Asset URLs: https (http on a dev box) or a root-relative path on this site.
     * Protocol-relative //host, javascript:, data: and anything malformed become null.
     */
    public static function url(mixed $value): ?string
    {
        $url = trim(self::raw($value));

        if ($url === '' || strlen($url) > 500) {
            return null;
        }

        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            // Local artwork: no space, quote, backslash or control character may survive.
            return preg_match('/[\s"\'\\\\\x00-\x1F]/', $url) === 1 ? null : $url;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['https', 'http'], true) ? $url : null;
    }

    /** Keys for the settings table — machine readable, never free text. */
    public static function key(mixed $value): string
    {
        return self::cut(strtolower(preg_replace('/[^a-z0-9_]/i', '_', self::raw($value)) ?? ''), 80);
    }

    private static function raw(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            return '';
        }

        return (string) $value;
    }

    /** Strip NULs and other C0/C1 controls (except newline/tab) plus bidi/zero-width chars. */
    private static function invisible(string $text): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;

        return preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{FEFF}]/u', '', $text) ?? $text;
    }

    private static function cut(string $text, int $max): string
    {
        return function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max);
    }
}
