<?php
declare(strict_types=1);

namespace App\Exceptions;

final class AuthorizationException extends HttpException
{
    public function __construct(string $message = 'You are not allowed to do that.')
    {
        parent::__construct(403, $message);
    }
}
