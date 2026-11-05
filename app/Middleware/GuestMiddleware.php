<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use Closure;

/** Keeps the login and register forms away from someone already signed in. */
final class GuestMiddleware implements MiddlewareContract
{
    public function __construct(private string $argument = '')
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return Response::redirect(Auth::user()['role'] === 'customer' ? route('account') : route('admin.dashboard'));
        }

        return $next($request);
    }
}
