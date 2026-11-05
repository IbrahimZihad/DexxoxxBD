<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use Closure;

/** The /admin area: staff or admin. The check runs here, not in the template. */
final class StaffMiddleware implements MiddlewareContract
{
    public function __construct(private string $argument = '')
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user === null) {
            if ($request->wantsJson()) {
                return Response::json(['ok' => false, 'message' => 'Sign in to continue.', 'code' => 'auth_required'], 401);
            }

            Session::set('intended_url', $request->fullUrl());

            return Response::redirect(route('admin.login') . '?reason=required');
        }

        if (!in_array((string) $user['role'], ['admin', 'staff'], true)) {
            Logger::security('staff_gate_denied', [
                'user_id' => $user['id'],
                'role' => $user['role'],
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            throw new HttpException(403, 'Your account does not have access to the admin area.');
        }

        return $next($request);
    }
}
