<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Services\CacheService;
use App\Services\Logger;
use Closure;

/**
 * Fixed-window rate limiting per IP + route, e.g. `throttle:10,60`
 * (10 hits per 60 seconds). Used for search, checkout, contact and OTP posts.
 */
final class ThrottleMiddleware
{
    public static function handle(): Closure
    {
        return static function (Request $request, Closure $next, array $arguments = []): Response {
            $limit = (int) ($arguments[0] ?? Config::get('security.rate_limiting.default_max', 60));
            $window = (int) ($arguments[1] ?? Config::get('security.rate_limiting.default_window_seconds', 60));

            $cache = self::cache();
            $key = 'throttle:' . sha1($request->ip() . '|' . $request->method() . '|' . $request->path());
            $state = $cache->increment($key, $window);

            if ($state['hits'] > $limit) {
                Logger::warning('rate_limit_exceeded', [
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                    'hits' => $state['hits'],
                    'limit' => $limit,
                ]);

                $retryAfter = $state['retry_after'];
                $message = 'Too many requests. Please wait ' . $retryAfter . ' second(s) and try again.';

                if ($request->wantsJson()) {
                    return Response::json(['ok' => false, 'message' => $message, 'retry_after' => $retryAfter], 429)
                        ->withHeader('Retry-After', (string) $retryAfter);
                }

                return Response::text($message, 429)->withHeader('Retry-After', (string) $retryAfter);
            }

            /** @var Response $response */
            $response = $next($request);
            return $response->withHeader('X-RateLimit-Limit', (string) $limit)
                ->withHeader('X-RateLimit-Remaining', (string) max(0, $limit - $state['hits']));
        };
    }

    private static function cache(): CacheService
    {
        /** @var CacheService $cache */
        $cache = App::instance()->make('cache');
        return $cache;
    }
}
