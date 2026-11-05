<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\OwnerMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\StaffMiddleware;
use Closure;

/**
 * Maps the middleware aliases used in routes/web.php onto classes. Aliases can
 * carry an argument: "throttle:login" or "throttle:20,60".
 *
 * The three role gates are, from loosest to tightest:
 *   auth  - any signed-in customer
 *   staff - admin or staff (the whole /admin panel)
 *   owner - admin only (settings, users, coupons, deletions)
 */
final class Middleware
{
    /** @param array<string, class-string> $aliases */
    public static function handle(string $alias, Request $request, Closure $next): Response
    {
        [$name, $argument] = array_pad(explode(':', $alias, 2), 2, '');

        $class = match ($name) {
            'auth' => AuthMiddleware::class,
            'guest' => GuestMiddleware::class,
            'staff' => StaffMiddleware::class,
            'owner' => OwnerMiddleware::class,
            'csrf' => CsrfMiddleware::class,
            'throttle' => RateLimitMiddleware::class,
            default => null,
        };

        if ($class === null) {
            throw new \App\Exceptions\HttpException(500, 'Unknown middleware: ' . $alias);
        }

        return (new $class((string) $argument))->handle($request, $next);
    }
}
