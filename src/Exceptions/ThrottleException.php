<?php
declare(strict_types=1);

namespace App\Exceptions;

final class ThrottleException extends HttpException
{
    public function __construct(public readonly int $retryAfter, string $message = 'Too many attempts. Please slow down.')
    {
        parent::__construct(429, $message, ['retry_after' => (string) $retryAfter]);
    }
}
