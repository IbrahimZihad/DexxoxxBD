<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Exceptions\HttpException;
use App\Exceptions\ValidationException;
use App\Services\Logger;
use Throwable;

/**
 * Turns any Throwable into a safe response: the visitor sees a branded error
 * page, the log gets the detail, and nothing internal ever reaches the browser
 * unless APP_DEBUG is on.
 */
final class ErrorHandler
{
    /** @param array<string, mixed> $allowedMethods */
    public static function toResponse(Throwable $exception, Request $request): Response
    {
        $status = 500;
        $title = 'Something went wrong';
        $message = 'We hit an unexpected problem while handling that request. '
            . 'Please try again, or contact support if it persists.';
        $errors = [];
        $allowed = [];

        if ($exception instanceof HttpException) {
            $status = $exception->statusCode();
            $allowed = $exception->allowedMethods();
            $title = match ($status) {
                404 => 'Page not found',
                405 => 'Method not allowed',
                403 => 'Access denied',
                401 => 'Sign in required',
                429 => 'Too many requests',
                default => 'Request problem',
            };
            // Client-facing HTTP messages are safe; they come from our own code.
            $message = $status < 500 && $exception->getMessage() !== ''
                ? $exception->getMessage()
                : $message;
        } elseif ($exception instanceof ValidationException) {
            $status = 422;
            $errors = $exception->errors();
            $title = 'Check the form';
            $message = 'Some fields need your attention.';
        } elseif ($exception instanceof AuthorizationException) {
            $status = 403;
            $title = 'Access denied';
            $message = $exception->getMessage();
        } elseif ($exception instanceof AuthenticationException) {
            $status = 401;
            $title = 'Sign in required';
            $message = $exception->getMessage();
        } else {
            Logger::exception($exception, [
                'path' => $request->path(),
                'method' => $request->method(),
                'ip' => $request->ip(),
            ]);

            if (Config::get('app.debug', false)) {
                $message = get_class($exception) . ': ' . $exception->getMessage();
            }
        }

        if ($status >= 500) {
            Logger::error('response_500', ['path' => $request->path(), 'status' => $status], 'error');
        }

        // Form posts get redirected back with their errors and old input.
        if ($exception instanceof ValidationException && !$request->wantsJson() && !$request->is('GET')) {
            Session::flashErrors($errors);
            Session::flashInput($request->all());
            $redirect = Response::redirect(self::safeTarget($request));
            return $redirect;
        }

        if ($request->wantsJson()) {
            $payload = [
                'ok' => false,
                'message' => $message,
                'status' => $status,
            ];
            if ($errors !== []) {
                $payload['errors'] = $errors;
            }
            if (Config::get('app.debug', false) && $status >= 500) {
                $payload['exception'] = [
                    'class' => get_class($exception),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ];
            }
            return Response::json($payload, $status);
        }

        $body = self::renderErrorPage(
            $status,
            $title,
            $message,
            $errors,
            $allowed,
            self::debugContext($exception)
        );
        $response = Response::html($body, $status);

        if ($allowed !== []) {
            $response = $response->withHeader('Allow', implode(', ', $allowed));
        }

        return $response;
    }

    /**
     * Detail for the debug error page: the same class, message and origin that
     * Logger::exception() wrote to storage/logs/error.log, so the browser and
     * the log point at one incident. Null unless APP_DEBUG is on.
     *
     * @return array{class: string, message: string, file: string, line: int, trace: list<string>}|null
     */
    private static function debugContext(Throwable $exception): ?array
    {
        if (!Config::get('app.debug', false)) {
            return null;
        }

        $frames = [];
        foreach (array_slice($exception->getTrace(), 0, 12) as $frame) {
            $call = ($frame['file'] ?? '[internal function]')
                . (isset($frame['line']) ? ':' . $frame['line'] : '');
            $frames[] = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '')
                . ' (' . $call . ')';
        }

        return [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $frames,
        ];
    }

    private static function safeTarget(Request $request): string
    {
        $referer = $request->referer();
        if ($referer === '') {
            return url('');
        }
        $host = parse_url($referer, PHP_URL_HOST);
        return (is_string($host) && $host === $request->host()) ? $referer : url('');
    }

    /** @param array<string, mixed>|null $incident */
    private static function renderErrorPage(
        int $status,
        string $title,
        string $message,
        array $errors,
        array $allowed,
        ?array $incident = null
    ): string {
        try {
            return App::viewFactory()->render('pages.error', [
                'status' => $status,
                'title' => $title,
                'message' => $message,
                'errors' => $errors,
                'allowed' => $allowed,
                'incident' => $incident,
            ]);
        } catch (Throwable) {
            // The error page itself failed - emit a minimal, safe document.
            return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
                . '<meta name="viewport" content="width=device-width,initial-scale=1">'
                . '<title>' . htmlspecialchars($title, ENT_QUOTES) . '</title></head><body>'
                . '<h1>' . $status . ' - ' . htmlspecialchars($title, ENT_QUOTES) . '</h1>'
                . '<p>' . htmlspecialchars($message, ENT_QUOTES) . '</p>'
                . '<p><a href="' . htmlspecialchars(url(''), ENT_QUOTES) . '">Back to the storefront</a></p>'
                . '</body></html>';
        }
    }

    /**
     * CLI entry point for uncaught errors in cron scripts.
     */
    public static function forCli(Throwable $exception): void
    {
        Logger::exception($exception, ['sapi' => PHP_SAPI]);
        fwrite(STDERR, sprintf(
            "[%s] %s\n",
            get_class($exception),
            $exception->getMessage()
        ));
    }
}
