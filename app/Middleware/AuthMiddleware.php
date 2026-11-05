<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Closure;

/** Any signed-in account. Customers hitting a protected page are sent to the login form. */
final class AuthMiddleware implements MiddlewareContract
{
    public function __construct(private string $argument = '')
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'message' => 'Sign in to continue.', 'code' => 'auth_required'], 401);
        }

        Session::set('intended_url', $request->fullUrl());

        return Response::redirect(route('login') . '?reason=required');
    }
}
