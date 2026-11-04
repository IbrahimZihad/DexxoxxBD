<?php
declare(strict_types=1);

namespace App\Exceptions;

/** Field-level failure; the message is safe to show, the map drives inline errors. */
final class ValidationException extends HttpException
{
    public function __construct(array $errors, string $message = 'Please check the highlighted fields.')
    {
        parent::__construct(422, $message, $errors);
    }
}
