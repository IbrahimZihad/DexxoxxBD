<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Closure;

/**
 * Double-submit CSRF check for every state-changing request.
 *
 * A failure returns 403 rather than the sometimes-suggested 419: 419 is not a
 * registered HTTP status and Apache rewrites it to 500 on the way out, which
 * hides the real reason from the user.
 */
final class CsrfMiddleware implements MiddlewareContract
{
    public function __construct(private string $argument = '')
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $exempt = (array) Config::get('app.csrf_exempt', []);

        foreach ($exempt as $pattern) {
            if (preg_match('#^' . str_replace('#', '\#', (string) $pattern) . '$#', $request->path()) === 1) {
                return $next($request);
            }
        }

        $token = $request->input('_token') ?? $request->header('x-csrf-token');

        if (Session::validateToken($token)) {
            return $next($request);
        }

        Logger::security('csrf_mismatch', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'session' => session_id() !== '' ? substr(sha1(session_id()), 0, 12) : 'none',
        ]);

        // Rotating means a reload fixes the page instead of leaving the browser
        // replaying a token that will never validate again.
        Session::rotateCsrfOnFailure();

        if ($request->wantsJson()) {
            return Response::json([
                'ok' => false,
                'code' => 'csrf_mismatch',
                'message' => 'Your session expired. Refresh the page and try again.',
            ], 403);
        }

        Session::flash('error', 'Your session expired. Please submit the form again.');

        return Response::html(
            \App\Core\ErrorHandler::failureBody($request, 403, 'Session expired', 'Your session expired. Refresh the page and try again.')
            ?: '',
            403
        );
    }
}
