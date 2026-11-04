<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Marks an endpoint as AJAX-only: rejects browser style form posts so the JSON
 * contract cannot be silently broken, and normalises Accept for the handler.
 */
final class JsonMiddleware
{
    public static function handle(): Closure
    {
        return static function (Request $request, Closure $next): Response {
            if (!$request->wantsJson()) {
                return Response::json([
                    'ok' => false,
                    'message' => 'This endpoint only accepts JSON requests.',
                    'code' => 'json_required',
                ], 406);
            }

            /** @var Response $response */
            $response = $next($request);
            return $response->withHeader('Cache-Control', 'no-store');
        };
    }
}
