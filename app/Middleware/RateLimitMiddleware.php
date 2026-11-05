<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\ErrorHandler;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Applies a per-IP sliding window limit.
 *
 * "throttle:login" reads app.rates.login from .env; "throttle:20,60" sets the
 * window inline. POST/PATCH/PUT/DELETE requests only consume a slot once the
 * limit is actually exceeded, so a normal browse session never pays for it.
 */
final class RateLimitMiddleware implements MiddlewareContract
{
    public function __construct(private string $argument = '')
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        [$max, $window, $bucket] = $this->resolve();

        $identity = $request->ip();
        $result = RateLimiter::attempt($bucket, $identity, $max, $window);

        if (!$result['allowed']) {
            Logger::security('rate_limit_hit', [
                'bucket' => $bucket,
                'ip' => $identity,
                'path' => $request->path(),
                'retry_after' => $result['retry_after'],
            ]);

            $message = 'Too many attempts. Please try again in '
                . $this->humanise($result['retry_after']) . '.';

            if ($request->wantsJson()) {
                return Response::json(['ok' => false, 'code' => 'rate_limited', 'message' => $message], 429)
                    ->withHeader('Retry-After', (string) $result['retry_after']);
            }

            return Response::html(
                ErrorHandler::failureBody($request, 429, 'Slow down', $message) ?? '',
                429
            )->withHeader('Retry-After', (string) $result['retry_after']);
        }

        $response = $next($request);

        return $response->withHeader('X-RateLimit-Remaining', (string) $result['remaining']);
    }

    /** @return array{0: int, 1: int, 2: string} */
    private function resolve(): array
    {
        $argument = trim($this->argument);

        if (str_contains($argument, ',')) {
            [$max, $window] = array_map('intval', explode(',', $argument, 2));

            return [max(1, $max), max(1, $window), 'inline-' . $max . '-' . $window];
        }

        $bucket = $argument === '' ? 'general' : $argument;
        $pair = (array) Config::get('app.rates.' . $bucket, Config::get('app.rates.general', [60, 60]));

        return [max(1, (int) ($pair[0] ?? 60)), max(1, (int) ($pair[1] ?? 60)), $bucket];
    }

    private function humanise(int $seconds): string
    {
        if ($seconds >= 3600) {
            return round($seconds / 3600, 1) . ' hours';
        }

        if ($seconds >= 60) {
            return (int) ceil($seconds / 60) . ' minutes';
        }

        return $seconds . ' seconds';
    }
}
