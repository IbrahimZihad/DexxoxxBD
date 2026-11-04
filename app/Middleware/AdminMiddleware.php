<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Closure;

/**
 * Gates the whole /admin area: the account must exist, be active and hold at
 * least one staff role. Being a customer is not enough.
 */
final class AdminMiddleware
{
    public static function handle(): Closure
    {
        return static function (Request $request, Closure $next): Response {
            Auth::instance()->guard('admin');
            $user = Auth::instance()->user();

            if ($user === null) {
                if ($request->wantsJson()) {
                    return Response::json(['ok' => false, 'message' => 'Sign in required.'], 401);
                }
                if (!Session::has('intended_url')) {
                    Session::set('intended_url', '/' . $request->path());
                }
                return Response::redirect(url('admin/login'));
            }

            if (!Auth::instance()->can('admin.access')) {
                if ($request->wantsJson()) {
                    return Response::json(['ok' => false, 'message' => 'This area is for staff accounts only.'], 403);
                }
                flash('danger', 'Your account does not have dashboard access.');
                return Response::redirect(url('account'));
            }

            /** @var Response $response */
            $response = $next($request);
            return $response;
        };
    }
}
