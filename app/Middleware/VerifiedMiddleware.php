<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Blocks actions that need a verified address (checkout, ticket creation) while
 * still letting the customer browse and fix their verification.
 */
final class VerifiedMiddleware
{
    public static function handle(): Closure
    {
        return static function (Request $request, Closure $next, array $arguments = []): Response {
            $user = Auth::instance()->user();

            if ($user === null) {
                return Response::redirect(url('login'));
            }

            if (empty($user['email_verified_at'])) {
                if ($request->wantsJson()) {
                    return Response::json([
                        'ok' => false,
                        'code' => 'email_unverified',
                        'message' => 'Please confirm your email address first.',
                        'redirect' => url('account/verify-email'),
                    ], 403);
                }

                flash('warning', 'Please confirm your email address to continue.');
                return Response::redirect(url('account/verify-email'));
            }

            /** @var Response $response */
            $response = $next($request);
            return $response;
        };
    }
}
