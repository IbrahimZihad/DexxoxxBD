<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;
use Closure;

/**
 * Double-submit CSRF check for every state-changing request. The token lives in
 * the session and is echoed by views (or sent as X-CSRF-Token by fetch()).
 */
final class CsrfMiddleware
{
    public static function handle(): Closure
    {
        return static function (Request $request, Closure $next): Response {
            if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                /** @var Response $response */
                $response = $next($request);
                return $response;
            }

            // Gateway webhooks authenticate themselves by signature, not CSRF.
            foreach ((array) Config::get('security.csrf_exempt', []) as $exemptPath) {
                if ($request->path() === trim((string) $exemptPath, '/')) {
                    /** @var Response $response */
                    $response = $next($request);
                    return $response;
                }
            }

            $token = $request->input('_token');
            if (!is_string($token) || $token === '') {
                $header = $request->header('x-csrf-token');
                $token = is_string($header) ? $header : null;
            }

            if (!Session::verifyCsrfToken(is_string($token) ? $token : null)) {
                AuditService::recordForCurrentUser('security.csrf_failure', [
                    'path' => $request->path(),
                    'method' => $request->method(),
                ]);

                Session::rotateCsrfOnFailure();

                if ($request->wantsJson()) {
                    return Response::json(['ok' => false, 'message' => 'Your session expired. Please refresh and try again.', 'code' => 'csrf_mismatch'], 419);
                }

                flash('danger', 'Your session expired for security reasons. Please try again.');
                return Response::redirect(self::safeFallback($request));
            }

            /** @var Response $response */
            $response = $next($request);
            return $response;
        };
    }

    /**
     * Never bounce the browser to an attacker-supplied Referer.
     */
    private static function safeFallback(Request $request): string
    {
        $referer = $request->referer();
        if ($referer === '') {
            return url('');
        }

        $host = parse_url($referer, PHP_URL_HOST);
        if (!is_string($host) || $host !== $request->host()) {
            return url('');
        }

        return $referer;
    }
}
