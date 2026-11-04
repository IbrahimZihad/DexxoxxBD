<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class HttpException extends RuntimeException
{
    /** @var array<string, mixed> */
    private array $context;

    /** @param array<string, mixed> $context */
    public function __construct(
        private int $statusCode,
        string $message = '',
        private array $allowedMethods = [],
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->context = $context;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return list<string> */
    public function allowedMethods(): array
    {
        return $this->allowedMethods;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }
}
