<?php
declare(strict_types=1);

namespace App\Exceptions;

/**
 * A write arrived without a token matching the session. Apache rewrites the
 * unofficial 419 into a 500, so this uses 403 plus a payload code the browser
 * layer can recognise and re-mint against.
 */
final class CsrfException extends HttpException
{
    public const CODE = 'csrf_stale';

    public function __construct(string $message = 'Your session expired or the page is stale. Reload and try again.')
    {
        parent::__construct(403, $message);
    }
}
