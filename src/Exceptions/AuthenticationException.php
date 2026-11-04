<?php
declare(strict_types=1);

namespace App\Exceptions;

final class AuthenticationException extends HttpException
{
    public function __construct(string $message = 'You must be logged in.')
    {
        parent::__construct(401, $message);
    }
}
