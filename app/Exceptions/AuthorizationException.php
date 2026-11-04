<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class AuthorizationException extends RuntimeException
{
    public function __construct(string $message = 'You are not allowed to perform this action.')
    {
        parent::__construct($message, 403);
    }
}
