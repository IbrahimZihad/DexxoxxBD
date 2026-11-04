<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\Config;

final class Request
{
    private ?array $body = null;
    private string $raw = '';

    /** JSON bodies larger than this are rejected before they are read. */
    private const MAX_BODY_BYTES = 131072;

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** @return list<string> */
    public function allowedMethods(): array
    {
        return ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
    }

    public function isUnsafe(): bool
    {
        return !in_array($this->method(), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $_GET[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->query($key);

        return $value === null || !is_numeric($value) ? $default : (int) $value;
    }

    public function raw(): string
    {
        if ($this->raw === '') {
            $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

            if ($length > self::MAX_BODY_BYTES) {
                return '';
            }

            $this->raw = file_get_contents('php://input') ?: '';
        }

        return $this->raw;
    }

    /** @return array<string,mixed> */
    public function body(): array
    {
        if ($this->body !== null) {
            return $this->body;
        }

        $raw = $this->raw();

        if ($raw === '') {
            return $this->body = $_POST;
        }

        $contentType = $this->header('Content-Type') ?? '';

        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($raw, true);
            $this->body = is_array($decoded) ? $decoded : [];
        } else {
            $this->body = $_POST;
        }

        return $this->body;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $body = $this->body();

        return array_key_exists($key, $body) ? $body[$key] : $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function header(string $name): ?string
    {
        $key = strtoupper(str_replace('-', '_', $name));

        // mod_php / apache2handler expose the two entity headers without the HTTP_
        // prefix the built-in server uses, so Content-Type must be read from both.
        $value = $_SERVER['HTTP_' . $key] ?? (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $_SERVER[$key] ?? null : null);

        return is_scalar($value) ? (string) $value : null;
    }

    public function origin(): ?string
    {
        return $this->header('Origin');
    }

    public function isSecure(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if (($this->header('X-Forwarded-Proto') ?? '') === 'https' && $this->ipIsTrustedProxy()) {
            return true;
        }

        return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    /**
     * X-Forwarded-For is only honoured when the immediate peer is a proxy we
     * configured; otherwise a client could forge it to dodge rate limits.
     */
    public function ip(): string
    {
        $peer = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($this->ipIsTrustedProxy($peer)) {
            $forwarded = $this->header('X-Forwarded-For');

            if ($forwarded !== null && $forwarded !== '') {
                $candidate = trim(explode(',', $forwarded)[0]);

                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }

        return $peer;
    }

    private function ipIsTrustedProxy(?string $ip = null): bool
    {
        $ip ??= $_SERVER['REMOTE_ADDR'] ?? '';
        $trusted = Config::get('security.trusted_proxies', []);

        return $trusted !== [] && in_array($ip, $trusted, true);
    }

    public function userAgent(): string
    {
        return substr($this->header('User-Agent') ?? '', 0, 200);
    }
}
