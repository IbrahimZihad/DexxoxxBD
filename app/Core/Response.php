<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP response with security header injection applied at the single point
 * where output actually leaves the application.
 */
final class Response
{
    private int $status = 200;

    /** @var array<string, string> */
    private array $headers = [];

    private string $content = '';

    /** @var list<array{name: string, value: string, options: array<string, mixed>}> */
    private array $cookies = [];

    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        $this->content = $content;
        $this->status = $status;
        foreach ($headers as $name => $value) {
            $this->headers[strtolower((string) $name)] = (string) $name . ': ' . $value;
        }
    }

    public static function make(string $content, int $status = 200): self
    {
        return new self($content, $status);
    }

    public static function html(string $content, int $status = 200): self
    {
        $response = new self($content, $status);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        if ($encoded === false) {
            $encoded = '{"error":"response_encoding_failed"}';
            $status = 500;
        }
        return (new self($encoded, $status))->withHeader('Content-Type', 'application/json; charset=UTF-8');
    }

    public static function text(
        string $content,
        int $status = 200,
        string $contentType = 'text/plain; charset=UTF-8'
    ): self {
        return (new self($content, $status))->withHeader('Content-Type', $contentType);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return (new self('', $status))->withHeader('Location', $to);
    }

    public static function back(int $status = 302): self
    {
        $target = request()->referer();
        if ($target === '') {
            $target = url('');
        }
        return self::redirect($target, $status);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[strtolower($name)] = $name . ': ' . $value;
        return $this;
    }

    public function withCookie(
        string $name,
        string $value,
        int $expires = 0,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): self {
        $secure = (($_SERVER['HTTPS'] ?? '') === 'on') || (bool) Config::get('security.force_secure_cookies', false);
        $this->cookies[] = [
            'name' => $name,
            'value' => $value,
            'options' => [
                'expires' => $expires,
                'path' => (string) Config::get('security.session.cookie_path', '/'),
                'domain' => (string) Config::get('security.session.cookie_domain', ''),
                'secure' => $secure,
                'httponly' => $httpOnly,
                'samesite' => $sameSite,
            ],
        ];
        return $this;
    }

    public function withoutCookie(string $name): self
    {
        return $this->withCookie($name, '', time() - 42000);
    }

    /**
     * Attach CSP, clickjacking, MIME-sniffing and referrer policy headers.
     * Configured in config/security.php.
     */
    public function withSecurityHeaders(?array $cspNonce = null): self
    {
        $security = (array) Config::get('security.headers', []);

        foreach ($security as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if ($name === 'Content-Security-Policy') {
                $value = $this->buildCsp((string) $value, $cspNonce);
            }
            $this->withHeader((string) $name, (string) $value);
        }

        if ((bool) Config::get('security.hsts_enabled', false)) {
            $this->withHeader(
                'Strict-Transport-Security',
                'max-age=' . (int) Config::get('security.hsts_max_age', 31536000) . '; includeSubDomains'
            );
        }

        return $this;
    }

    private function buildCsp(string $template, ?string $nonce): string
    {
        $policy = $template;
        $nonceValue = $nonce ?? App::cspNonce();

        // Allow inline bootstrap/Chart styles only through the per-request nonce.
        $policy = str_replace('{nonce}', "'nonce-" . $nonceValue . "'", $policy);

        return $policy;
    }

    public function send(): void
    {
        if (headers_sent()) {
            echo $this->content;
            return;
        }

        http_response_code($this->status);

        foreach ($this->headers as $line) {
            $parts = explode(': ', $line, 2);
            header($parts[0] . ': ' . ($parts[1] ?? ''), true);
        }

        foreach ($this->cookies as $cookie) {
            setcookie($cookie['name'], $cookie['value'], $cookie['options']);
        }

        echo $this->content;
    }
}
