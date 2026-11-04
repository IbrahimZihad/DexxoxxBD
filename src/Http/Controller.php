<?php
declare(strict_types=1);

namespace App\Http;

use App\Security\Validator;

abstract class Controller
{
    public function __construct(protected Request $request)
    {
    }

    /** @return array<string,mixed> the sanitized, typed subset of the body */
    protected function validate(array $rules, array $labels = [], ?array $data = null): array
    {
        return Validator::make($data ?? $this->request->body(), $rules, $labels)->validated();
    }

    protected function json(array $payload, int $status = 200): Response
    {
        return Response::json($payload, $status);
    }

    protected function idFromQuery(string $key = 'id'): int
    {
        return max(0, $this->request->int($key));
    }

    protected function page(): int
    {
        return max(1, $this->request->int('page', 1));
    }

    protected function perPage(int $default = 20, int $max = 100): int
    {
        return max(1, min($max, $this->request->int('per_page', $default)));
    }

    /** True when the client sent the field at all — drives partial PUT updates. */
    protected function has(string $key): bool
    {
        return array_key_exists($key, $this->request->body());
    }

    /** Whitelisted query filter: an unexpected value reads as "no filter". */
    protected function oneOf(array $allowed, string $key): string
    {
        $value = (string) ($this->request->query($key) ?? '');

        return in_array($value, $allowed, true) ? $value : '';
    }
}
