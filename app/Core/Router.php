<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use Closure;

/**
 * Small router: path templates with {param} captures, groups, middleware
 * pipeline and named-route URL generation.
 */
final class Router
{
    /**
     * @var list<array{
     *     method: string, path: string, regex: string, keys: list<string>,
     *     handler: mixed, name: ?string, middleware: list<string>, namespace: string
     *   }>
     */
    private array $routes = [];

    /** @var array<string, array{path: string, keys: list<string>, method: string}> */
    private array $names = [];

    /** @var list<array{prefix: string, middleware: list<string>, namespace: string}> */
    private array $groups = [];

    /** @var array<string, Closure> */
    private array $middlewareAliases = [];

    /** @var array{path: string, method: string}|null */
    private ?array $matchedRoute = null;

    public function aliasMiddleware(string $alias, Closure $middleware): void
    {
        $this->middlewareAliases[$alias] = $middleware;
    }

    /** @return array{path: string, method: string}|null */
    public function matched(): ?array
    {
        return $this->matchedRoute;
    }

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
        $parent = $this->groups === [] ? ['prefix' => '', 'middleware' => [], 'namespace' => ''] : end($this->groups);

        $newPrefix = trim((string) ($attributes['prefix'] ?? ''), '/');
        $prefix = trim((string) $parent['prefix'], '/');
        $prefix = match (true) {
            $prefix !== '' && $newPrefix !== '' => $prefix . '/' . $newPrefix,
            $newPrefix !== '' => $newPrefix,
            default => $prefix,
        };

        $own = $attributes['middleware'] ?? [];
        $own = is_string($own) ? [$own] : (array) $own;

        $this->groups[] = [
            'prefix' => $prefix,
            'middleware' => array_values(array_unique(array_merge((array) $parent['middleware'], $own))),
            'namespace' => (string) ($attributes['namespace'] ?? $parent['namespace']),
        ];

        $routes($this);

        array_pop($this->groups);
    }

    /**
     * CRUD routes for a controller.
     *
     * @param array{only?: list<string>, parameter?: string, names?: string} $options
     */
    public function resource(string $path, string $controller, array $options = []): void
    {
        $parameter = (string) ($options['parameter'] ?? 'id');
        $namePrefix = (string) ($options['names'] ?? trim(str_replace('/', '.', $path), '.'));
        $only = (array) ($options['only'] ?? ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);

        $definitions = [
            'index' => [['GET'], $path, $controller . '@index'],
            'create' => [['GET'], $path . '/create', $controller . '@create'],
            'store' => [['POST'], $path, $controller . '@store'],
            'show' => [['GET'], $path . '/{' . $parameter . '}', $controller . '@show'],
            'edit' => [['GET'], $path . '/{' . $parameter . '}/edit', $controller . '@edit'],
            'update' => [['PUT', 'PATCH'], $path . '/{' . $parameter . '}', $controller . '@update'],
            'destroy' => [['DELETE'], $path . '/{' . $parameter . '}', $controller . '@destroy'],
        ];

        foreach ($only as $action) {
            if (!isset($definitions[$action])) {
                continue;
            }
            [$methods, $routePath, $handler] = $definitions[$action];
            $this->add($methods, $routePath, $handler)->name($namePrefix . '.' . $action);
        }
    }

    private function add(array $methods, string $path, mixed $handler): RouteRegistrar
    {
        $path = trim($path, '/');
        $group = $this->groups === [] ? null : end($this->groups);

        if ($group !== null) {
            $prefix = trim((string) $group['prefix'], '/');
            if ($prefix !== '') {
                $path = $prefix . ($path === '' ? '' : '/' . $path);
            }
        }

        [$regex, $keys] = $this->compile($path);

        return new RouteRegistrar($this, $methods, $path, $regex, $keys, $handler, (array) $group);
    }

    /**
     * @return array{string, list<string>}
     */
    private function compile(string $path): array
    {
        $keys = [];

        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
            static function (array $m) use (&$keys): string {
                $keys[] = $m[1];
                return '(' . ($m[2] ?? '[^/]+') . ')';
            },
            $path
        ) ?? $path;

        return ['#^' . str_replace('#', '\#', $regex) . '$#', $keys];
    }

    /**
     * Finalises a route once the registrar has collected name/middleware.
     *
     * @param list<string> $methods
     * @param list<string> $middleware
     */
    public function register(
        array $methods,
        string $path,
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
                'path' => $path,
                'regex' => $regex,
                'keys' => $keys,
                'handler' => $handler,
                'name' => $name,
                'middleware' => $middleware,
                'namespace' => $namespace,
            ];
        }

        if ($name !== null) {
            $this->names[$name] = ['path' => $path, 'keys' => $keys, 'method' => strtoupper($methods[0])];
        }
    }

    /** @param array<string, mixed> $parameters */
    public function route(string $name, array $parameters = []): string
    {
        if (!isset($this->names[$name])) {
            throw new HttpException(500, 'Unknown route name: ' . $name);
        }

        $definition = $this->names[$name];
        $path = $definition['path'];

        foreach ($definition['keys'] as $key) {
            $value = $parameters[$key] ?? '';
            $path = preg_replace('/\{' . preg_quote($key, '/') . '(?::[^}]+)?\}/', rawurlencode((string) $value), $path, 1) ?? $path;
        }

        // Drop any captures left unfilled so the URL stays usable.
        $path = preg_replace('/\{[a-zA-Z0-9_]+(?::[^}]+)?\}/', '', $path) ?? $path;

        return url(trim($path, '/'));
    }

    public function hasRoute(string $name): bool
    {
        return isset($this->names[$name]);
    }

    /** @return list<array{method: string, path: string, name: ?string}> */
    public function all(): array
    {
        return array_map(
            static fn (array $r): array => ['method' => $r['method'], 'path' => $r['path'], 'name' => $r['name']],
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
            throw new HttpException(405, 'That HTTP method is not allowed here.', array_values(array_unique($allowed)));
        }

        throw new HttpException(404, 'The page you requested could not be found.');
    }

    private function runThroughMiddleware(array $route, Request $request): Response
    {
        $handler = $route['handler'];
        $namespace = (string) ($route['namespace'] ?? '');

        $pipeline = static fn (Request $r): Response => self::invokeHandler($handler, $r, $namespace);

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
            return self::toResponse($handler($request, ...array_values($request->routeParams())));
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
        return match (true) {
            $result instanceof Response => $result,
            is_array($result) => Response::json($result),
            is_string($result) => Response::html($result),
            $result === null => Response::make('', 204),
            default => Response::html((string) $result),
        };
    }
}
