<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ValidationException;

/**
 * Base controller: the handful of things every action needs, kept in one place
 * so controllers stay about the request in front of them.
 */
abstract class Controller
{
    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return App::instance()->view()->render($template, $data, $status);
    }

    /** @param array<string, mixed> $data */
    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /** @param array<string, mixed> $payload */
    protected function ok(string $message, array $payload = []): Response
    {
        return Response::json(['ok' => true, 'message' => $message, 'data' => $payload]);
    }

    /** @param array<string, mixed> $payload */
    protected function fail(string $message, string $code = 'failed', array $payload = [], int $status = 422): Response
    {
        return Response::json(['ok' => false, 'message' => $message, 'code' => $code, 'data' => $payload], $status);
    }

    protected function redirect(string $url, string $flashType = '', string $flashMessage = ''): Response
    {
        if ($flashMessage !== '') {
            Session::flash($flashType === '' ? 'info' : $flashType, $flashMessage);
        }

        return Response::redirect($url);
    }

    protected function back(string $fallback = ''): Response
    {
        $referer = App::instance()->request()->referer();

        // Only follow a same-origin Referer; an absolute external URL would turn
        // the app into an open redirect.
        if ($referer === '') {
            return Response::redirect($fallback === '' ? route('home') : $fallback);
        }

        $path = (string) parse_url($referer, PHP_URL_PATH);
        $base = App::instance()->request()->basePath();

        if ($base !== '' && !str_starts_with($path, $base)) {
            return Response::redirect($fallback === '' ? route('home') : $fallback);
        }

        $relative = $base !== '' && str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
        $query = (string) parse_url($referer, PHP_URL_QUERY);

        return Response::redirect(trim($relative, '/') . ($query === '' ? '' : '?' . $query));
    }

    /**
     * Validate and return only the fields the rule list mentions, so a mass
     * assignment can never come from an unexpected form input.
     *
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     * @return array<string, mixed>
     */
    protected function validate(Request $request, array $rules, array $labels = []): array
    {
        $validator = new Validator($request->all(), $rules, $labels);

        if ($validator->fails()) {
            $errors = $validator->errors();

            if ($request->wantsJson()) {
                throw new ValidationException($errors, $request->all());
            }

            Session::flashInput($request->all());
            Session::flash('error', $validator->firstMessage());

            throw new ValidationException($errors, $request->all());
        }

        return $validator->validated();
    }
}
