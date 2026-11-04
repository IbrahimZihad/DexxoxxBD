<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ValidationException;
use App\Validators\Validator;

/**
 * Base controller: rendering, redirects, validation and authorization helpers
 * so individual actions stay short.
 */
abstract class Controller
{
    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html(App::viewFactory()->render($template, $data), $status);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function ok(array $data = [], string $message = ''): Response
    {
        return Response::json(['ok' => true, 'message' => $message, 'data' => $data]);
    }

    protected function fail(string $message, int $status = 422, array $extra = []): Response
    {
        return Response::json(array_merge(['ok' => false, 'message' => $message], $extra), $status);
    }

    protected function redirect(string $path, int $status = 302): Response
    {
        return Response::redirect(str_starts_with($path, 'http') ? $path : url($path));
    }

    /** Redirect back to the referring page (validated against our own host). */
    protected function back(int $status = 302): Response
    {
        $request = App::request();
        $referer = $request->referer();
        $target = url('');

        if ($referer !== '') {
            $host = parse_url($referer, PHP_URL_HOST);
            if (is_string($host) && $host === $request->host()) {
                $target = $referer;
            }
        }

        return Response::redirect($target, $status);
    }

    /**
     * Validate request input.
     *
     * @param array<string, mixed> $rules
     * @param array<string, string> $messages
     * @return array<string, mixed> the validated subset
     */
    protected function validate(Request $request, array $rules, array $messages = []): array
    {
        $validator = new Validator($request->all(), $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator->errors(), $request->all());
        }

        return $validator->validated();
    }

    /**
     * Enforce a permission for this action. Views may hide a button, but the
     * server is the only authority.
     */
    protected function authorize(string $permission, ?string $message = null): void
    {
        if (!Auth::instance()->can($permission)) {
            throw new \App\Exceptions\AuthorizationException(
                $message ?? 'You do not have permission to perform this action.'
            );
        }
    }

    protected function authorizeOwnership(
        int $ownerId,
        string $message = 'This record belongs to another account.'
    ): void {
        $currentUserId = Auth::instance()->id();
        if ($currentUserId === null || $currentUserId !== $ownerId) {
            throw new \App\Exceptions\AuthorizationException($message);
        }
    }

    /** Guard against CSRF-unaware cross-site form posts on sensitive actions. */
    protected function requirePasswordConfirmation(Request $request): void
    {
        $password = (string) $request->input('current_password', '');
        if (!Auth::instance()->confirmPassword($password)) {
            throw new ValidationException(
                ['current_password' => ['Your current password is incorrect.']],
                []
            );
        }
    }

    protected function requestIdempotencyKey(Request $request): ?string
    {
        $key = $request->header('idempotency-key');
        if (!is_string($key)) {
            $key = (string) $request->input('_idempotency_key', '');
        }
        $key = trim($key);
        return $key === '' ? null : substr($key, 0, 100);
    }
}
