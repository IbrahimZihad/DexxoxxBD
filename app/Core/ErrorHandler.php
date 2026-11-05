<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use App\Exceptions\ValidationException;
use Throwable;

/**
 * Turns any throwable into a response. Nothing internal reaches the browser
 * unless APP_DEBUG is on, and the same incident always goes to the log first.
 */
final class ErrorHandler
{
    public static function toResponse(Throwable $exception, Request $request): Response
    {
        if ($exception instanceof ValidationException) {
            return self::forValidation($exception, $request);
        }

        if ($exception instanceof HttpException) {
            if ($exception->status() >= 500) {
                Logger::exception($exception, ['path' => $request->path()]);
            }

            return self::build(
                $request,
                $exception->status(),
                self::titleFor($exception->status()),
                $exception->getMessage(),
                $exception->allowedMethods(),
                [],
                self::debugContext($exception)
            );
        }

        Logger::exception($exception, ['path' => $request->path(), 'method' => $request->method()]);

        $debug = (bool) Config::get('app.debug', false);

        return self::build(
            $request,
            500,
            'Server error',
            $debug ? $exception->getMessage() : 'Something went wrong on our side. The team has been notified.',
            [],
            [],
            self::debugContext($exception)
        );
    }

    /**
     * Body only, for middleware that needs to answer without an exception
     * (rate limiting and CSRF) while still using the same error page.
     */
    public static function failureBody(
        Request $request,
        int $status,
        string $title,
        string $message,
        array $allowed = [],
        array $errors = []
    ): string {
        return self::body($request, $status, $title, $message, $allowed, $errors, null);
    }

    /** @param array<string, list<string>> $errors @param list<string> $allowed */
    private static function build(
        Request $request,
        int $status,
        string $title,
        string $message,
        array $allowed,
        array $errors,
        ?array $incident
    ): Response {
        if ($request->wantsJson()) {
            $payload = ['ok' => false, 'message' => $message, 'code' => self::codeFor($status)];

            if ($errors !== []) {
                $payload['errors'] = $errors;
            }

            if ($incident !== null) {
                $payload['debug'] = $incident;
            }

            $response = Response::json($payload, $status);
        } else {
            $response = Response::html(self::body($request, $status, $title, $message, $allowed, $errors, $incident), $status);
        }

        if ($allowed !== []) {
            $response = $response->withHeader('Allow', implode(', ', $allowed));
        }

        return $response;
    }

    /**
     * @param array<string, list<string>> $errors
     * @param list<string> $allowed
     * @param array<string, mixed>|null $incident
     */
    private static function body(
        Request $request,
        int $status,
        string $title,
        string $message,
        array $allowed,
        array $errors,
        ?array $incident
    ): string {
        unset($request);

        try {
            return App::instance()->view()->html('pages.error', [
                'status' => $status,
                'title' => $title,
                'message' => $message,
                'errors' => $errors,
                'allowed' => $allowed,
                'incident' => $incident,
            ]);
        } catch (Throwable $viewFailure) {
            // The error page itself failed (usually a missing table during an
            // install). Fall back to a standalone page so the user still sees a
            // real status code instead of a blank 500.
            Logger::error('error_page_failed', ['reason' => $viewFailure->getMessage()]);

            return self::fallback($status, $title, $message, (bool) Config::get('app.debug', false), $incident);
        }
    }

    /** @param array<string, mixed>|null $incident */
    private static function fallback(int $status, string $title, string $message, bool $debug, ?array $incident): string
    {
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . e($status . ' ' . $title) . '</title></head>'
            . '<body style="font:16px/1.6 system-ui;background:#0b1220;color:#e8eef7;display:grid;place-items:center;min-height:100vh;margin:0">'
            . '<main style="max-width:34rem;padding:2rem;text-align:center">'
            . '<p style="font-size:3rem;margin:0;font-weight:800">' . $status . '</p>'
            . '<h1 style="font-size:1.3rem;margin:.4rem 0">' . e($title) . '</h1>'
            . '<p style="opacity:.8;margin:0">' . e($message) . '</p>'
            . '<p style="margin-top:1.5rem"><a href="/" style="color:#7dd3fc">Back to the shop</a></p>';

        if ($debug && $incident !== null) {
            $html .= '<pre style="text-align:left;background:#111c2e;padding:1rem;border-radius:.6rem;overflow:auto;font-size:.75rem">'
                . e($incident['class'] . ': ' . $incident['message'] . "\n" . $incident['file'] . ':' . $incident['line'])
                . '</pre>';
        }

        return $html . '</main></body></html>';
    }

    /** @param array<string, list<string>> $errors */
    private static function forValidation(ValidationException $exception, Request $request): Response
    {
        if ($request->wantsJson()) {
            return Response::json([
                'ok' => false,
                'code' => 'validation_failed',
                'message' => $exception->firstError(),
                'errors' => $exception->errors(),
            ], 422);
        }

        Session::set('_errors', $exception->errors());
        Session::flashInput($exception->input());

        $referer = $request->referer();
        $target = $referer === '' ? route('home') : $referer;

        Session::flash('error', $exception->firstError());

        return Response::redirect($target);
    }

    /** @return array{class: string, message: string, file: string, line: int, trace: list<string>}|null */
    private static function debugContext(Throwable $exception): ?array
    {
        if (!(bool) Config::get('app.debug', false)) {
            return null;
        }

        $frames = [];

        foreach (array_slice($exception->getTrace(), 0, 12) as $frame) {
            $call = ($frame['file'] ?? '[internal function]') . (isset($frame['line']) ? ':' . $frame['line'] : '');
            $frames[] = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '') . ' (' . $call . ')';
        }

        return [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $frames,
        ];
    }

    private static function titleFor(int $status): string
    {
        return match ($status) {
            400 => 'Bad request',
            401 => 'Sign in required',
            403 => 'Not allowed',
            404 => 'Page not found',
            405 => 'Method not allowed',
            422 => 'Check your details',
            429 => 'Too many attempts',
            default => 'Server error',
        };
    }

    private static function codeFor(int $status): string
    {
        return match ($status) {
            401 => 'auth_required',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            422 => 'validation_failed',
            429 => 'rate_limited',
            default => 'server_error',
        };
    }
}
