<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use Closure;

/** Admin-only actions: settings, user management, coupons, anything destructive. */
final class OwnerMiddleware implements MiddlewareContract
{
    public function __construct(private string $argument = '')
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user === null) {
            return Response::redirect(route('admin.login') . '?reason=required');
        }

        if ((string) $user['role'] !== 'admin') {
            Logger::security('owner_gate_denied', [
                'user_id' => $user['id'],
                'role' => $user['role'],
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            throw new HttpException(403, 'Only an administrator can do that.');
        }

        return $next($request);
    }
}
