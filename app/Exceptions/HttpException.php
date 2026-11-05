<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * An error that maps onto an HTTP status. Anything thrown that is not this class
 * is treated as a 500 by the error handler, which keeps internal details out of
 * the browser unless APP_DEBUG is on.
 */
class HttpException extends RuntimeException
{
    /** @var list<string> */
    private array $allowedMethods = [];

    /** @param array<string, mixed> $context */
    public function __construct(
        private int $status,
        string $message = '',
        private array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message === '' ? self::defaultMessage($status) : $message, $status, $previous);
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'The request could not be understood.',
            401 => 'Sign in to continue.',
            403 => 'You are not allowed to do that.',
            404 => 'This page does not exist.',
            405 => 'That action needs a different HTTP method.',
            419 => 'Your session expired. Please try again.',
            422 => 'Some of the details you entered need attention.',
            429 => 'Too many attempts. Please slow down.',
            default => 'Something went wrong on our side.',
        };
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }

    /** @param list<string> $methods */
    public function withAllowedMethods(array $methods): self
    {
        $this->allowedMethods = $methods;

        return $this;
    }

    /** @return list<string> */
    public function allowedMethods(): array
    {
        return $this->allowedMethods;
    }

    public function isClientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }
}
