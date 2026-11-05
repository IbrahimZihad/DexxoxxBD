<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable snapshot of one HTTP request, with the sub-directory awareness the
 * app needs because a XAMPP install is usually served from /<project>/public.
 */
final class Request
{
    private string $method;

    private string $path;

    /** @var array<string, mixed> */
    private array $query;

    /** @var array<string, mixed> */
    private array $body;

    /** @var array<string, string> */
    private array $headers;

    /** @var array<string, mixed> */
    private array $parameters = [];

    private string $ip;

    private string $basePath;

    private string $host;

    private bool $secure;

    /** @param array<string, mixed> $query @param array<string, mixed> $body @param array<string, string> $headers */
    public function __construct(
        string $method,
        string $path,
        array $query,
        array $body,
        array $headers,
        string $ip,
        string $basePath,
        string $host,
        bool $secure
    ) {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->query = $query;
        $this->body = $body;
        $this->headers = $headers;
        $this->ip = $ip;
        $this->basePath = $basePath;
        $this->host = $host;
        $this->secure = $secure;
    }

    public static function capture(): self
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $base = rtrim(dirname($script), '/');

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $parsed = parse_url($uri);
        $rawPath = (string) ($parsed['path'] ?? '/');

        if ($base !== '' && $base !== '/' && str_starts_with($rawPath, $base)) {
            $rawPath = substr($rawPath, strlen($base));
        }

        $path = trim($rawPath, '/');

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $body = $_POST;
        $query = $_GET;
        $hasJson = false;

        // HTML forms cannot send PUT/PATCH/DELETE, so a POST carrying _method is
        // rewritten. Anything else is ignored rather than trusted.
        $override = strtoupper(trim((string) ($body['_method'] ?? '')));
        if ($method === 'POST' && in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
            $method = $override;
        }

        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $hasJson = true;
                $body = $decoded + $body;
            }
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        $host = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost')));

        return new self(
            $method,
            $path,
            $query,
            $hasJson ? $body : $query + $body,
            $headers,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            $base,
            is_string($host) && $host !== '' ? $host : 'localhost',
            (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        );
    }

    public function withParameters(array $parameters): self
    {
        $clone = clone $this;
        $clone->parameters = $parameters;

        return $clone;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function is(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function path(): string
    {
        return $this->path;
    }

    /** @return list<string> */
    public function segments(): array
    {
        return $this->path === '' ? [] : explode('/', $this->path);
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    public function ip(): string
    {
        return $this->ip;
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->headers['user-agent'] ?? ''), 0, 400);
    }

    public function header(string $name, string $default = ''): string
    {
        return (string) ($this->headers[strtolower($name)] ?? $default);
    }

    public function parameter(string $name, string $default = ''): string
    {
        $value = $this->parameters[$name] ?? $default;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return $this->parameters;
    }

    public function queryValue(string $name, mixed $default = null): mixed
    {
        return $this->query[$name] ?? $default;
    }

    public function integer(string $name, int $default = 0): int
    {
        $value = $this->input($name);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function boolean(string $name): bool
    {
        $value = $this->input($name);

        return in_array(is_string($value) ? strtolower($value) : $value, ['1', 'true', 'on', 'yes', 1, true], true);
    }

    public function input(string $name, mixed $default = null): mixed
    {
        $value = $this->body[$name] ?? $this->query[$name] ?? $default;

        // Every scalar that reaches validation or a model is trimmed here so a
        // stray space can never turn into a "different" email or coupon code.
        return is_string($value) ? trim($value) : $value;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->body + $this->query;
    }

    /** @param list<string> $keys @return array<string, mixed> */
    public function only(array $keys): array
    {
        $picked = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $this->body) || array_key_exists($key, $this->query)) {
                $picked[$key] = $this->input($key);
            }
        }

        return $picked;
    }

    public function has(string $name): bool
    {
        $value = $this->input($name);

        return $value !== null && $value !== '';
    }

    public function wantsJson(): bool
    {
        if ($this->header('x-requested-with') === 'XMLHttpRequest') {
            return true;
        }

        return str_contains($this->header('accept'), 'application/json');
    }

    public function referer(): string
    {
        return (string) ($this->headers['referer'] ?? '');
    }

    public function url(string $path = ''): string
    {
        $configured = (string) Config::get('app.url', '');
        $root = $configured !== '' ? $configured : ($this->secure ? 'https' : 'http') . '://' . $this->host . $this->basePath;

        $path = ltrim($path, '/');

        return $path === '' ? $root : $root . '/' . $path;
    }

    public function fullUrl(): string
    {
        $query = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);

        return $this->url($this->path) . ($query === '' ? '' : '?' . $query);
    }

    /** @return array<string, mixed> */
    public function files(): array
    {
        return $_FILES;
    }
}
