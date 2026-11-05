<?php

declare(strict_types=1);

namespace App\Core;

/**
 * The single outbound HTTP response object. Security headers are attached here,
 * at the last possible moment, so no controller can accidentally render a page
 * without them.
 */
final class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    public function __construct(private string $body = '', private int $status = 200)
    {
    }

    public static function html(string $body, int $status = 200): self
    {
        return (new self($body, $status))->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /** @param array<string, mixed>|list<mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return (new self($body === false ? '{}' : $body, $status))
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return (new self('', $status))->withHeader('Location', $url);
    }

    public static function notFound(string $body = ''): self
    {
        return self::html($body, 404);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function header(string $name): string
    {
        return (string) ($this->headers[$name] ?? '');
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function bodyString(): string
    {
        return $this->body;
    }

    /**
     * CSP is built around a per-request nonce so inline <script> blocks can run
     * while an injected one cannot. The nonce is generated once, in App::boot().
     */
    public function withSecurityHeaders(string $cspNonce): self
    {
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-" . $cspNonce . "' 'unsafe-eval' https://cdn.tailwindcss.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);

        $headers = [
            'Content-Security-Policy' => $csp,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), payment=()',
        ];

        foreach ($headers as $name => $value) {
            if ($this->header($name) === '') {
                $this->withHeader($name, $value);
            }
        }

        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo $this->body;
    }
}
