<?php
declare(strict_types=1);

namespace App\Core;

use App\Auth\Auth;
use App\Exceptions\HttpException;
use App\Exceptions\ThrottleException;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\Security\RateLimiter;
use Throwable;

/**
 * Tiny request kernel. Each public/api/*.php file is a two-line dispatcher that
 * hands the request here; routing lives in routes/api.php so URL shape and
 * controller code stay independent.
 */
final class Kernel
{
    /** @var array<string,array<string,array<string,mixed>>> */
    private array $routes;

    public function __construct(private readonly Request $request)
    {
        $this->routes = require dirname(__DIR__, 2) . '/routes/api.php';
    }

    public function handle(string $endpoint): Response
    {
        try {
            $route = $this->resolve($endpoint);
            $this->guard($route);

            $handler = $route['handler'];
            $result = (new $handler[0]($this->request))->{$handler[1]}();

            return $result instanceof Response ? $result : Response::json((array) $result);
        } catch (Throwable $e) {
            return $this->renderThrowable($e);
        }
    }

    /** @return array<string,mixed> */
    private function resolve(string $endpoint): array
    {
        $routes = $this->routes[$endpoint] ?? null;

        if ($routes === null) {
            throw new HttpException(404, 'Endpoint not found.');
        }

        // Auth-style endpoints route on ?action=, resource endpoints on HTTP verb.
        $action = $this->request->query('action');
        $key = $action !== null && isset($routes[$action]) ? $action : $this->request->method();

        if (!isset($routes[$key])) {
            $allowed = $this->allowedKeys($routes, $action);
            throw new HttpException($allowed === [] ? 404 : 405, 'Method not allowed.', ['allow' => implode(', ', $allowed)]);
        }

        $route = $routes[$key];
        $methods = $route['methods'] ?? (preg_match('/^(GET|POST|PUT|PATCH|DELETE|OPTIONS)$/', (string) $key) ? [(string) $key] : ['GET']);

        if (!in_array($this->request->method(), $methods, true)) {
            throw new HttpException(405, 'Method not allowed.', ['allow' => implode(', ', $methods)]);
        }

        return $route;
    }

    private function allowedKeys(array $routes, ?string $action): array
    {
        if ($action !== null) {
            return [];
        }

        $verbs = [];

        foreach ($routes as $key => $route) {
            if (preg_match('/^(GET|POST|PUT|PATCH|DELETE|OPTIONS)$/', (string) $key)) {
                $verbs = array_merge($verbs, $route['methods'] ?? [(string) $key]);
            }
        }

        return array_values(array_unique($verbs));
    }

    private function guard(array $route): void
    {
        if (!empty($route['throttle'])) {
            (new RateLimiter($this->request->ip()))->hit((string) $route['throttle']);
        }

        $auth = $route['auth'] ?? null;

        if ($auth === 'admin') {
            Auth::requireAdmin();
        } elseif ($auth === 'user') {
            Auth::requireUser();
        }

        // Guests may still be rate limited above, but never pass CSRF.
        if (($route['csrf'] ?? false) === true && $this->request->isUnsafe()) {
            Csrf::verify($this->request);
        }
    }

    private function renderThrowable(Throwable $e): Response
    {
        if ($e instanceof HttpException) {
            $headers = $e instanceof ThrottleException
                ? ['Retry-After' => (string) $e->retryAfter]
                : [];

            return Response::error($e->getMessage(), $e->status(), array_filter($e->errors), $headers);
        }

        Logger::error(
            get_class($e) . ': ' . $e->getMessage(),
            ['file' => basename($e->getFile()), 'line' => $e->getLine(), 'path' => $_SERVER['REQUEST_URI'] ?? 'cli']
        );

        if (Config::get('app.debug')) {
            return Response::error($e->getMessage(), 500, ['trace' => $e->getTraceAsString()]);
        }

        return Response::error('Something went wrong on our end. Please try again shortly.', 500);
    }
}
