<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;

/**
 * Plain PHP templates with a layout, named sections and components. No compiler,
 * no cache: the files under resources/views are what render.
 *
 * A page view echoes its body and calls $view->extend('layouts/app'); extra
 * head data (title, meta, JSON-LD) is handed to the layout with setSection().
 */
final class View
{
    /** @var array<string, mixed> */
    private array $shared = [];

    /** @var array<string, string> */
    private array $sections = [];

    /** @var list<string> */
    private array $sectionStack = [];

    private ?string $layout = null;

    public function __construct(private string $basePath)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html($this->html($template, $data), $status);
    }

    /** @param array<string, mixed> $data */
    public function html(string $template, array $data = []): string
    {
        $this->layout = null;
        $this->sections = [];
        $this->sectionStack = [];

        $body = $this->capture($this->path($template), $data);
        $this->sections['content'] = $body;

        if ($this->layout === null) {
            return $body;
        }

        return $this->capture($this->path($this->layout), $data);
    }

    public function path(string $template): string
    {
        $file = rtrim($this->basePath, '/\\') . '/' . trim(str_replace('.', '/', $template), '/') . '.php';

        if (!is_file($file)) {
            throw new HttpException(500, 'View not found: ' . $template);
        }

        return $file;
    }

    public function exists(string $template): bool
    {
        return is_file($this->path($template));
    }

    /** @param array<string, mixed> $data */
    public function component(string $name, array $data = []): string
    {
        return $this->capture($this->path('components/' . $name), $data);
    }

    /** @param array<string, mixed> $data */
    public function partial(string $name, array $data = []): string
    {
        return $this->capture($this->path('partials/' . $name), $data);
    }

    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function startSection(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function stopSection(): void
    {
        $name = array_pop($this->sectionStack);

        if ($name === null) {
            ob_end_clean();

            return;
        }

        $this->sections[$name] = (string) ob_get_clean();
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]) && trim($this->sections[$name]) !== '';
    }

    /** Set a section from a value instead of an output buffer. */
    public function setSection(string $name, string $value): void
    {
        $this->sections[$name] = $value;
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /** @param array<string, mixed> $data */
    private function capture(string $file, array $data): string
    {
        $scope = array_merge($this->shared, $data, ['view' => $this]);

        ob_start();

        try {
            (static function (array $scope, string $file): void {
                extract($scope, EXTR_OVERWRITE);

                include $file;
            })($scope, $file);
        } catch (\Throwable $exception) {
            ob_end_clean();

            throw $exception;
        }

        return (string) ob_get_clean();
    }
}
