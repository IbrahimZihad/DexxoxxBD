<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Fluent per-route configuration. Registration happens when the registrar is
 * released, so `->name()` / `->middleware()` chains never leave a route half
 * added.
 */
final class RouteRegistrar
{
    private bool $registered = false;

    /** @var list<string> */
    private array $extraMiddleware = [];

    private ?string $name = null;

    /**
     * @param list<string>    $methods
     * @param list<string>    $keys
     * @param array{prefix?: string, middleware?: list<string>, namespace?: string} $group
     */
    public function __construct(
        private Router $router,
        private array $methods,
        private string $path,
        private string $regex,
        private array $keys,
        private mixed $handler,
        private array $group
    ) {
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /** @param list<string>|string $middleware */
    public function middleware(array|string $middleware): self
    {
        $this->extraMiddleware = array_merge(
            $this->extraMiddleware,
            is_string($middleware) ? [$middleware] : $middleware
        );
        return $this;
    }

    public function get(): self
    {
        return $this;
    }

    public function __destruct()
    {
        $this->register();
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        $middleware = array_values(array_unique(array_merge(
            (array) ($this->group['middleware'] ?? []),
            $this->extraMiddleware
        )));

        $this->router->register(
            $this->methods,
            $this->path,
            $this->regex,
            $this->keys,
            $this->handler,
            $this->name,
            $middleware,
            (string) ($this->group['namespace'] ?? '')
        );
    }
}
