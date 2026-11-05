<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use Closure;

/**
 * Pattern-matching router with groups, per-route middleware and named routes.
 *
 * Placeholders look like {id} or {id:\d+}. The constraint is read up to the
 * first "}", so a brace quantifier such as {2} cannot appear inside a route
 * pattern - check lengths in the controller instead.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: mixed, middleware: list<string>, name: string, pattern: string}> */
    private array $routes = [];

    /** @var list<array{prefix: string, middleware: list<string>, namespace: string}> */
    private array $groupStack = [];

    /** @var array<string, string> */
    private array $names = [];

    public function get(string $pattern, mixed $handler, array $options = []): void
    {
        $this->add('GET', $pattern, $handler, $options);
    }

    public function post(string $pattern, mixed $handler, array $options = []): void
    {
        $this->add('POST', $pattern, $handler, $options);
    }

    public function put(string $pattern, mixed $handler, array $options = []): void
    {
        $this->add('PUT', $pattern, $handler, $options);
    }

    public function patch(string $pattern, mixed $handler, array $options = []): void
    {
        $this->add('PATCH', $pattern, $handler, $options);
    }

    public function delete(string $pattern, mixed $handler, array $options = []): void
    {
        $this->add('DELETE', $pattern, $handler, $options);
    }

    /**
     * @param array{prefix?: string, middleware?: list<string>, namespace?: string} $options
     */
    public function group(array $options, Closure $callback): void
    {
        $this->groupStack[] = [
            'prefix' => trim((string) ($options['prefix'] ?? ''), '/'),
            'middleware' => array_values((array) ($options['middleware'] ?? [])),
            'namespace' => (string) ($options['namespace'] ?? 'App\\Controllers'),
        ];

        $callback($this);

        array_pop($this->groupStack);
    }

    /** @return array{prefix: string, middleware: list<string>, namespace: string} */
    private function currentGroup(): array
    {
        $prefix = '';
        $middleware = [];
        $namespace = 'App\\Controllers';

        foreach ($this->groupStack as $group) {
            $prefix = trim($prefix . '/' . $group['prefix'], '/');
            $middleware = array_merge($middleware, $group['middleware']);

            if ($group['namespace'] !== '') {
                $namespace = $group['namespace'];
            }
        }

        return ['prefix' => $prefix, 'middleware' => $middleware, 'namespace' => $namespace];
    }

    /** @param array{name?: string, middleware?: list<string>} $options */
    private function add(string $method, string $pattern, mixed $handler, array $options): void
    {
        $group = $this->currentGroup();
        $full = trim($group['prefix'] . '/' . trim($pattern, '/'), '/');

        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^/]*?))?\}#',
            static fn (array $matches): string => '(' . (($matches[2] ?? '') !== '' ? $matches[2] : '[^/+?#]+') . ')',
            $full
        );

        if ($regex === null) {
            throw new HttpException(500, 'Uncompilable route pattern: ' . $pattern);
        }

        $route = [
            'method' => $method,
            'regex' => '#^' . $regex . '$#',
            // A short "Home@index" string is resolved against the group
            // namespace; anything namespaced or a closure is used as written.
            'handler' => (is_string($handler) && !str_contains($handler, '\\'))
                ? $group['namespace'] . '\\' . $handler
                : $handler,
            // Group middleware first, then the route's own: dropping the group
            // list here would silently remove csrf, throttle and the role gates.
            'middleware' => array_values(array_merge(
                $group['middleware'],
                (array) ($options['middleware'] ?? [])
            )),
            'name' => (string) ($options['name'] ?? ''),
            'pattern' => $full,
        ];

        $this->routes[] = $route;

        if ($route['name'] !== '') {
            $this->names[$route['name']] = $full;
        }
    }

    /**
     * @param array<string, scalar> $params
     * @param array<string, scalar> $query
     */
    public function route(string $name, array $params = [], array $query = []): string
    {
        $pattern = $this->names[$name] ?? null;

        if ($pattern === null) {
            throw new HttpException(500, 'Unknown route name: ' . $name);
        }

        $path = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::[^/]*?)?\}#',
            static function (array $matches) use (&$params): string {
                $key = $matches[1];
                if (!array_key_exists($key, $params)) {
                    throw new HttpException(500, 'Route parameter "' . $key . '" is required.');
                }
                $value = (string) $params[$key];
                unset($params[$key]);

                return rawurlencode($value);
            },
            $pattern
        );

        // Any leftover param becomes part of the query string, which keeps
        // route('plans', ['filter' => 'streaming']) working without a second call.
        $extra = array_merge($params, $query);

        return App::instance()->request()->url((string) $path)
            . ($extra === [] ? '' : '?' . http_build_query($extra));
    }

    /** @return list<array{method: string, pattern: string, name: string}> */
    public function list(): array
    {
        return array_map(
            static fn (array $route): array => [
                'method' => $route['method'],
                'pattern' => $route['pattern'],
                'name' => $route['name'],
            ],
            $this->routes
        );
    }

    public function dispatch(Request $request): Response
    {
        $path = $request->path();
        $allowed = [];
        $wrongMethod = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== $request->method()) {
                $wrongMethod = true;
                $allowed[] = $route['method'];

                continue;
            }

            return $this->run($route, $request, $this->bindNames($route['pattern'], $matches));
        }

        if ($wrongMethod) {
            throw (new HttpException(405, 'That action needs a different HTTP method.'))
                ->withAllowedMethods(array_values(array_unique($allowed)));
        }

        throw new HttpException(404, 'This page does not exist.');
    }

    /**
     * @param list<string> $captures the preg_match set, capture 0 being the path
     * @return array<string, mixed>
     */
    private function bindNames(string $pattern, array $captures): array
    {
        preg_match_all('#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::[^/]*?)?\}#', $pattern, $found);

        $parameters = [];

        foreach (array_slice($captures, 1) as $index => $value) {
            if (isset($found[1][$index])) {
                $parameters[$found[1][$index]] = (string) $value;
            }
        }

        return $parameters;
    }

    /**
     * @param array{handler: mixed, middleware: list<string>} $route
     * @param array<string, mixed> $parameters
     */
    private function run(array $route, Request $request, array $parameters): Response
    {
        $core = fn (Request $request): Response => $this->toResponse(
            $this->callHandler($route['handler'], $request, $parameters)
        );

        $pipeline = array_reduce(
            array_reverse($route['middleware']),
            fn (callable $next, string $alias): Closure => static fn (Request $request): Response => Middleware::handle($alias, $request, $next),
            $core
        );

        /** @var Closure $pipeline */
        return $pipeline($request->withParameters($parameters));
    }

    /** @param array<string, mixed> $parameters */
    private function callHandler(mixed $handler, Request $request, array $parameters): mixed
    {
        if ($handler instanceof Closure) {
            return $handler($request, ...array_values($parameters));
        }

        if (!is_string($handler) || !str_contains($handler, '@')) {
            throw new HttpException(500, 'Malformed route handler.');
        }

        [$class, $method] = explode('@', $handler, 2);

        if (!class_exists($class)) {
            throw new HttpException(500, 'Controller ' . $class . ' is missing.');
        }

        $controller = new $class();

        if (!method_exists($controller, $method)) {
            throw new HttpException(500, $class . '::' . $method . '() is missing.');
        }

        return $controller->{$method}($request, ...array_values($parameters));
    }

    private function toResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        if (is_string($result)) {
            return Response::html($result);
        }

        if ($result === null) {
            return new Response('', 204);
        }

        return Response::html((string) $result);
    }
}
