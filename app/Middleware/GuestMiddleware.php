<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Login/register screens must not be reachable by an authenticated visitor.
 */
final class GuestMiddleware
{
    public static function handle(): Closure
    {
        return static function (Request $request, Closure $next): Response {
            if (Auth::instance()->check()) {
                $target = Auth::instance()->can('admin.access') ? url('admin') : url('account');
                return Response::redirect($target);
            }

            /** @var Response $response */
            $response = $next($request);
            return $response;
        };
    }
}
