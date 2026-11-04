<?php
declare(strict_types=1);

namespace App\Http;

final class Response
{
    /**
     * @param array<string,mixed> $payload
     * @param array<string,string> $headers
     */
    private function __construct(
        private readonly array $payload,
        private readonly int $status,
        private readonly array $headers,
        private readonly bool $isJson
    ) {
    }

    public static function json(array $payload, int $status = 200, array $headers = []): self
    {
        return new self($payload, $status, $headers, true);
    }

    public static function error(string $message, int $status, array $errors = [], array $headers = []): self
    {
        $payload = ['error' => $message];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return self::json($payload, $status, $headers);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self(['location' => $url], $status, ['Location' => $url], false);
    }

    public function send(): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        if ($this->isJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}
