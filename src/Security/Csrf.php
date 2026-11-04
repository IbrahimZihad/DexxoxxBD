<?php
declare(strict_types=1);

namespace App\Security;

use App\Auth\Session;
use App\Exceptions\HttpException;
use App\Http\Request;

final class Csrf
{
    /**
     * Same-origin JSON writes must echo the session token. Cookie-only requests
     * from another site (CSRF) cannot read it, so they are rejected.
     *
     * @throws HttpException
     */
    public static function verify(Request $request): void
    {
        $token = $request->header('X-CSRF-Token') ?? (string) $request->input('csrf_token', '');

        if (!Session::tokenMatches($token)) {
            throw new HttpException(419, 'Your session expired or the page is stale. Reload and try again.');
        }
    }

    public static function issue(): string
    {
        return Session::csrfToken();
    }
}
