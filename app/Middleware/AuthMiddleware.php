<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Closure;

/**
 * Requires a signed-in customer. AJAX calls get 401 JSON instead of a redirect
 * so the frontend can react without following a login page.
 */
final class AuthMiddleware
{
    public static function handle(): Closure
    {
        return static function (Request $request, Closure $next, array $arguments = []): Response {
            $guard = $arguments[0] ?? 'web';
            Auth::instance()->guard($guard);

            if (Auth::instance()->check()) {
                /** @var Response $response */
                $response = $next($request);
                return $response;
            }

            if ($request->wantsJson()) {
                return Response::json(['ok' => false, 'message' => 'Sign in required.'], 401)
                    ->withHeader('X-Auth-Redirect', url('login'));
            }

            if (!Session::has('intended_url')) {
                Session::set('intended_url', '/' . $request->path());
            }

            flash('warning', 'Please sign in to continue.');
            return Response::redirect(url('login'));
        };
    }
}
