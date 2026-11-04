<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by the Validator when input fails; carries field-wise messages.
 */
class ValidationException extends RuntimeException
{
    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $input
     */
    public function __construct(
        private array $errors,
        private array $input = [],
        string $message = 'The given data was invalid.'
    ) {
        parent::__construct($message, 422);
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function input(): array
    {
        return $this->input;
    }

    public function firstError(): string
    {
        foreach ($this->errors as $messages) {
            if ($messages !== []) {
                return (string) $messages[0];
            }
        }
        return 'The given data was invalid.';
    }
}
