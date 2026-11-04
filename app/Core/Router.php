<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use Closure;

/**
 * Small regex-compiled router with groups, middleware and named routes.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, keys: list<string>, handler: mixed, name: ?string, middleware: list<string>}> */
    private array $routes = [];

    /** @var array<string, array{path: string, method: string}> */
    private array $names = [];

    /** @var list<string> */
    private array $groupStack = [];

    /** @var list<array{prefix: string, middleware: list<string>, namespace: string}> */
    private array $groups = [];

    /** @var array<string, Closure> */
    private array $middlewareAliases = [];

    public function aliasMiddleware(string $alias, Closure $middleware): void
    {
        $this->middlewareAliases[$alias] = $middleware;
    }

    /** @param array{path: string, method: string} $matched */
    public function matched(): ?array
    {
        return $this->matchedRoute;
    }

    /** @var array{path: string, method: string}|null */
    private ?array $matchedRoute = null;

    public function get(string $path, mixed $handler): RouteRegistrar
    {
        return $this->add(['GET'], $path, $handler);
    }

    public function post(string $path, mixed $handler): RouteRegistrar
    {
        return $this->add(['POST'], $path, $handler);
    }

    public function put(string $path, mixed $handler): RouteRegistrar
    {
        return $this->add(['PUT'], $path, $handler);
    }

    public function patch(string $path, mixed $handler): RouteRegistrar
    {
        return $this->add(['PATCH'], $path, $handler);
    }

    public function delete(string $path, mixed $handler): RouteRegistrar
    {
        return $this->add(['DELETE'], $path, $handler);
    }

    /** @param list<string> $methods */
    public function match(array $methods, string $path, mixed $handler): RouteRegistrar
    {
        return $this->add($methods, $path, $handler);
    }

    public function any(string $path, mixed $handler): RouteRegistrar
    {
        return $this->add(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $path, $handler);
    }

    /**
     * @param array{prefix?: string, middleware?: list<string>|string, namespace?: string} $attributes
     */
    public function group(array $attributes, Closure $routes): void
    {
        $parent = $this->groups === [] ? [] : end($this->groups);

        $prefix = trim((string) ($parent['prefix'] ?? ''), '/');
        $newPrefix = trim((string) ($attributes['prefix'] ?? ''), '/');
        if ($prefix !== '' && $newPrefix !== '') {
            $prefix .= '/' . $newPrefix;
        } else {
            $prefix .= $newPrefix;
        }

        $parentMiddleware = (array) ($parent['middleware'] ?? []);
        $own = $attributes['middleware'] ?? [];
        $own = is_string($own) ? [$own] : (array) $own;

        $this->groups[] = [
            'prefix' => $prefix,
            'middleware' => array_values(array_unique(array_merge($parentMiddleware, $own))),
            'namespace' => (string) ($attributes['namespace'] ?? $parent['namespace'] ?? ''),
        ];

        $routes($this);

        array_pop($this->groups);
    }

    /**
     * Standard CRUD set for a resource, excluding `create`/`show` naming surprises.
     */
    public function resource(string $path, string $controller, array $options = []): void
    {
        $only = $options['only'] ?? ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];
        $singular = $options['parameter'] ?? rtrim($path, 's');

        $map = [
            'index' => [['GET', $path], $controller . '@index'],
            'create' => [['GET', $path . '/create'], $controller . '@create'],
            'store' => [['POST', $path], $controller . '@store'],
            'show' => [['GET', $path . '/{' . $singular . '}'], $controller . '@show'],
            'edit' => [['GET', $path . '/{' . $singular . '}/edit'], $controller . '@edit'],
            'update' => [['PUT', 'PATCH', $path . '/{' . $singular . '}'], $controller . '@update'],
            'destroy' => [['DELETE', $path . '/{' . $singular . '}'], $controller . '@destroy'],
        ];

        foreach ($only as $action) {
            if (!isset($map[$action])) {
                continue;
            }
            [$methods, $handler] = $map[$action];
            $route = $this->add($methods, $handler[0] === '[' ? $handler : $methods[2] ?? '', $handler);
            unset($route);
        }

        // Re-register properly (the loop above is a guard against typos only).
        foreach ($only as $action) {
            if (!isset($map[$action])) {
                continue;
            }
            [$methods, $handler] = $map[$action];
            $routeMethods = array_slice($methods, 0, count($methods) - 1);
            $routePath = end($methods);
            $this->add($routeMethods, $routePath, $handler)->name(str_replace('/', '.', $path) . '.' . $action);
        }
    }

    private function add(array $methods, string $path, mixed $handler): RouteRegistrar
    {
        $path = trim($path, '/');
        $currentGroup = $this->groups === [] ? null : end($this->groups);

        if ($currentGroup !== null) {
            $prefix = trim((string) $currentGroup['prefix'], '/');
            if ($prefix !== '') {
                $path = $prefix . ($path === '' ? '' : '/' . $path);
            }
        }

        [$regex, $keys] = $this->compile($path);

        $registrar = new RouteRegistrar($this, $methods, $regex, $keys, $handler, $currentGroup);
        return $registrar;
    }

    /**
     * @return array{string, list<string>}
     */
    private function compile(string $path): array
    {
        $keys = [];
        // {name} captures; {name:[0-9]+} adds a constraint.
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
            function (array $m) use (&$keys): string {
                $keys[] = $m[1];
                $pattern = $m[2] ?? '[^/]+';
                return '(' . $pattern . ')';
            },
            $path
        ) ?? $path;

        $regex = '#^' . str_replace('#', '\#', $regex) . '$#';
        return [$regex, $keys];
    }

    /**
     * @internal called by RouteRegistrar when a route finishes configuring
     */
    public function register(
        array $methods,
        string $regex,
        array $keys,
        mixed $handler,
        ?string $name,
        array $middleware,
        string $namespace
    ): void {
        foreach ($methods as $method) {
            $this->routes[] = [
                'method' => strtoupper($method),
                'regex' => $regex,
                'keys' => $keys,
                'handler' => $handler,
                'name' => $name,
                'middleware' => $middleware,
                'namespace' => $namespace,
            ];
        }

        if ($name !== null) {
            $this->names[$name] = ['regex' => $regex, 'keys' => $keys, 'method' => $methods[0]];
        }
    }

    /** @param array<string, mixed> $parameters */
    public function route(string $name, array $parameters = []): string
    {
        if (!isset($this->names[$name])) {
            throw new HttpException(500, 'Unknown route name: ' . $name);
        }

        $definition = $this->names[$name];
        $path = $definition['regex'];
        $path = preg_replace('/^\#\^|\$#$/', '', $path) ?? $path;
        $path = str_replace('\#', '#', $path);

        foreach ($definition['keys'] as $index => $key) {
            $value = $parameters[$key] ?? '';
            $replacement = rawurlencode((string) $value);
            // Replace the first capture group in order.
            $path = preg_replace('/\((?:[^()]|\([^()]*\))*\)/', $replacement, $path, 1) ?? $path;
        }

        return url(trim($path, '/'));
    }

    /** @return list<array{method: string, regex: string, name: ?string}> */
    public function all(): array
    {
        return array_map(
            static fn (array $r): array => ['method' => $r['method'], 'regex' => $r['regex'], 'name' => $r['name']],
            $this->routes
        );
    }

    public function dispatch(Request $request): Response
    {
        $path = $request->path();
        $method = $request->method();

        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }

            $this->matchedRoute = ['path' => $path, 'method' => $method];
            array_shift($matches);

            $params = [];
            foreach ($route['keys'] as $index => $key) {
                $params[$key] = $matches[$index] ?? null;
            }
            $request->setRouteParams($params);

            return $this->runThroughMiddleware($route, $request);
        }

        if ($allowed !== []) {
            throw new HttpException(405, 'Method not allowed for this path.', array_values(array_unique($allowed)));
        }

        throw new HttpException(404, 'The page you requested could not be found.');
    }

    private function runThroughMiddleware(array $route, Request $request): Response
    {
        $handler = $route['handler'];
        $namespace = (string) ($route['namespace'] ?? '');

        $core = static function (Request $request) use ($handler, $namespace): Response {
            return self::invokeHandler($handler, $request, $namespace);
        };

        $pipeline = $core;
        foreach (array_reverse($route['middleware']) as $alias) {
            $middleware = $this->middlewareAliases[$alias] ?? null;
            if ($middleware === null) {
                throw new HttpException(500, 'Undefined middleware: ' . $alias);
            }
            $next = $pipeline;
            $pipeline = static fn (Request $r): Response => $middleware($r, $next);
        }

        return $pipeline($request);
    }

    private static function invokeHandler(mixed $handler, Request $request, string $namespace): Response
    {
        if ($handler instanceof Closure) {
            $result = $handler($request, ...array_values($request->routeParams()));
            return self::toResponse($result);
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $fqcn = $namespace === '' ? $class : trim($namespace, '\\') . '\\' . $class;

            if (!class_exists($fqcn)) {
                throw new HttpException(500, 'Controller not found: ' . $fqcn);
            }

            $controller = new $fqcn();
            if (!method_exists($controller, $method)) {
                throw new HttpException(500, 'Controller action not found: ' . $fqcn . '::' . $method);
            }

            return self::toResponse($controller->{$method}($request, ...array_values($request->routeParams())));
        }

        throw new HttpException(500, 'Malformed route handler.');
    }

    private static function toResponse(mixed $result): Response
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
            return Response::make('', 204);
        }
        return Response::html((string) $result);
    }
}
