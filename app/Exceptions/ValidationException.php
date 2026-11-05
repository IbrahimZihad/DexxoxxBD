<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Core\Validator;
use RuntimeException;

/**
 * Thrown by Validator::validateOrFail(). The error handler turns it into a
 * redirect back to the form with field errors (or a 422 JSON body for fetch
 * calls), so controllers never have to thread failure through return values.
 */
final class ValidationException extends RuntimeException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(private array $errors, private array $input = [])
    {
        parent::__construct('Please correct the highlighted fields.');
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

        return 'Invalid input.';
    }

    /** @param array<string, string> $rules */
    public static function from(Validator $validator, array $input = []): self
    {
        return new self($validator->errors(), $input);
    }
}
