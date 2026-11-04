<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Applies the resolved locale to the translator before the controller runs.
 */
final class LocaleMiddleware
{
    public static function handle(): Closure
    {
        return static function (Request $request, Closure $next): Response {
            App::translator()->setLocale(\App\Core\LocaleResolver::resolve($request));

            /** @var Response $response */
            $response = $next($request);
            return $response;
        };
    }
}
