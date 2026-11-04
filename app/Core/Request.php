<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable-ish HTTP request abstraction over the PHP superglobals.
 */
final class Request
{
    /** @var array<string, mixed> */
    private array $query;

    /** @var array<string, mixed> */
    private array $body;

    /** @var array<string, mixed> */
    private array $files;

    /** @var array<string, mixed> */
    private array $server;

    /** @var array<string, mixed> */
    private array $cookies;

    /** @var array<string, mixed> */
    private array $headers;

    /** @var array<string, mixed> */
    private array $routeParams = [];

    /** @var array<string, mixed>|null */
    private ?array $jsonCache = null;

    private ?string $rawBodyCache = null;

    private string $method;

    public function __construct(
        ?array $query = null,
        ?array $body = null,
        ?array $files = null,
        ?array $server = null,
        ?array $cookies = null
    ) {
        $this->query = $query ?? $_GET;
        $this->server = $server ?? $_SERVER;
        $this->files = $files ?? $_FILES;
        $this->cookies = $cookies ?? $_COOKIE;
        $this->headers = $this->collectHeaders($this->server);
        $this->body = $this->resolveBody($body);
        $this->method = $this->resolveMethod();
    }

    /** @return array<string, mixed> */
    private function resolveBody(?array $body): array
    {
        if ($body !== null) {
            return $body;
        }

        if ($this->isJson()) {
            $decoded = $this->json();
            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }

    /** @return array<string, mixed> */
    private function collectHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            }
        }
        if (isset($server['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $server['CONTENT_TYPE'];
        }
        return $headers;
    }

    private function resolveMethod(): string
    {
        $method = strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));

        // Form method override for PUT/PATCH/DELETE (HTML forms only send GET/POST).
        if ($method === 'POST' && isset($this->body['_method'])) {
            $override = strtoupper((string) $this->body['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }

        return $method;
    }

    /** @return array<string, mixed>|null */
    public function json(): ?array
    {
        if ($this->jsonCache !== null) {
            return $this->jsonCache;
        }

        $raw = $this->rawBody();
        if ($raw === '') {
            $this->jsonCache = [];
            return $this->jsonCache;
        }

        $decoded = json_decode($raw, true);
        $this->jsonCache = is_array($decoded) ? $decoded : [];

        return $this->jsonCache;
    }

    public function rawBody(): string
    {
        if ($this->rawBodyCache !== null) {
            return $this->rawBodyCache;
        }
        $stream = file_get_contents('php://input');
        $this->rawBodyCache = $stream === false ? '' : $stream;
        return $this->rawBodyCache;
    }

    public function isJson(): bool
    {
        return str_contains($this->header('content-type', ''), 'application/json');
    }

    public function wantsJson(): bool
    {
        if ($this->isJson()) {
            return true;
        }
        $accept = $this->header('accept', '');
        $xhr = strtolower($this->header('x-requested-with', '')) === 'xmlhttprequest';
        return $xhr || str_contains($accept, 'application/json');
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
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        // Strip the script directory so routing works from any sub-folder (XAMPP htdocs).
        $scriptName = (string) ($this->server['SCRIPT_NAME'] ?? '');
        $base = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if ($base !== '' && $base !== '/' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        $path = trim($path, '/');
        return $path === '' ? '' : $path;
    }

    public function uri(): string
    {
        return (string) ($this->server['REQUEST_URI'] ?? '/');
    }

    public function url(): string
    {
        $scheme = $this->scheme();
        $host = $this->host();
        return $scheme . '://' . $host . $this->uri();
    }

    public function fullUrlWithoutQuery(): string
    {
        return $this->scheme() . '://' . $this->host() . '/' . $this->path();
    }

    public function scheme(): string
    {
        $https = ($this->server['HTTPS'] ?? '') === 'on'
            || strtolower($this->header('x-forwarded-proto', '')) === 'https';
        return $https ? 'https' : 'http';
    }

    public function host(): string
    {
        $host = $this->header('x-forwarded-host', '');
        if ($host === '') {
            $host = (string) ($this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? 'localhost');
        }
        // Host header is attacker controlled: keep it to a safe character set.
        return preg_replace('/[^A-Za-z0-9.:-]/', '', $host) ?? 'localhost';
    }

    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function cookie(string $name, mixed $default = null): mixed
    {
        return $this->cookies[$name] ?? $default;
    }

    /** @return array<string, mixed> */
    public function cookies(): array
    {
        return $this->cookies;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? null;
        return $value === null ? $default : $value;
    }

    /** @return array<string, mixed> */
    public function query(): array
    {
        return $this->query;
    }

    public function queryValue(string $key, mixed $default = null): mixed
    {
        $value = $this->query[$key] ?? null;
        return $value === null ? $default : $value;
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->input($key);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function boolean(string $key): bool
    {
        $value = filter_var($this->input($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return (bool) $value;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key);
        if (is_array($value)) {
            return $default;
        }
        return trim((string) ($value ?? $default));
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /** @return array<string, mixed> */
    public function only(array $keys): array
    {
        $all = $this->all();
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $all[$key] ?? null;
        }
        return $out;
    }

    public function has(string $key): bool
    {
        $all = $this->all();
        return array_key_exists($key, $all) && $all[$key] !== '';
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);
        return $value !== null && $value !== '' && $value !== [];
    }

    /** @return array<string, mixed> */
    public function files(): array
    {
        return $this->files;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    public function ip(): string
    {
        // Only trusted when a real proxy sets it; otherwise REMOTE_ADDR is authoritative.
        if (Config::get('security.trust_proxy_headers', false)) {
            $forwarded = $this->header('x-forwarded-for', '');
            if (is_string($forwarded) && $forwarded !== '') {
                $first = trim(explode(',', $forwarded)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
                    return $first;
                }
            }
        }
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return substr((string) $this->header('user-agent', ''), 0, 512);
    }

    public function referer(): string
    {
        return (string) $this->header('referer', '');
    }

    /** @param array<string, mixed> $params */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /** @return array<string, mixed> */
    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function routeParam(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }
}
