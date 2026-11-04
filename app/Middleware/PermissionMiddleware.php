<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use Closure;

/**
 * Server-side permission gate. Usage: `->middleware('permission:products.update')`
 * or several slugs (any one satisfies) separated by commas.
 */
final class PermissionMiddleware
{
    public static function handle(): Closure
    {
        return static function (Request $request, Closure $next, array $arguments = []): Response {
            $required = $arguments;

            if ($required === []) {
                // A route that forgot to name a permission is a bug, not a hole.
                return Response::json(['ok' => false, 'message' => 'Route is missing its permission definition.'], 403);
            }

            $granted = false;
            foreach ($required as $permission) {
                if (Auth::instance()->can($permission)) {
                    $granted = true;
                    break;
                }
            }

            if (!$granted) {
                AuditService::recordForCurrentUser('security.permission_denied', [
                    'required' => implode(',', $required),
                    'path' => $request->path(),
                    'method' => $request->method(),
                ]);

                if ($request->wantsJson()) {
                    return Response::json([
                        'ok' => false,
                        'message' => 'You do not have permission to perform this action.',
                    ], 403);
                }

                flash('danger', 'You do not have permission to perform this action.');
                return Auth::instance()->check()
                    ? Response::redirect(url('admin'))
                    : Response::redirect(url('admin/login'));
            }

            /** @var Response $response */
            $response = $next($request);
            return $response;
        };
    }
}
