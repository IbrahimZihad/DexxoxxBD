<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Closure;

/**
 * Baseline browser-request pipeline: session, guest-cart continuity and the
 * "intended url" cookie used after a login redirect.
 */
final class WebMiddleware
{
    public static function handle(): Closure
    {
        return static function (Request $request, Closure $next): Response {
            Session::start();

            // Remember where a guest was heading so login can send them back.
            if (!$request->wantsJson() && $request->is('GET') && $request->path() !== '') {
                $publicPaths = ['/', 'login', 'register', 'password/forgot', 'products', 'categories', 'search'];
                $first = explode('/', $request->path())[0];
                if (!in_array($request->path(), $publicPaths, true) && !in_array($first, $publicPaths, true)) {
                    if (!Session::has('intended_url')) {
                        Session::set('intended_url', '/' . $request->path());
                    }
                }
            }

            /** @var Response $response */
            $response = $next($request);
            return $response;
        };
    }
}
