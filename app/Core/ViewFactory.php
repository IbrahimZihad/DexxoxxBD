<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Plain-PHP template renderer with layouts, sections, components and escapes
 * applied at the point of use (`e()`), never by silent magic.
 */
final class ViewFactory
{
    /** @var array<string, string> */
    private array $shared = [];

    /** @var array<string, string> */
    private array $namespaces = [];

    /** @var list<string> */
    private array $sectionStack = [];

    /** @var array<string, string> */
    private array $sections = [];

    /** @var list<array<string, mixed>> */
    private array $componentStack = [];

    private ?string $layout = null;

    /** @var array<string, mixed> */
    private array $layoutData = [];

    public function __construct(private string $basePath)
    {
        if (!is_dir($basePath)) {
            throw new RuntimeException('Views directory is missing: ' . $basePath);
        }
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function addNamespace(string $hint, string $path): void
    {
        $this->namespaces[$hint] = $path;
    }

    public function exists(string $template): bool
    {
        return is_file($this->path($template));
    }

    private function path(string $template): string
    {
        if (str_contains($template, '::')) {
            [$hint, $rest] = explode('::', $template, 2);
            if (!isset($this->namespaces[$hint])) {
                throw new RuntimeException('Unknown view namespace: ' . $hint);
            }
            return rtrim($this->namespaces[$hint], '/\\') . '/' . str_replace('.', '/', $rest) . '.php';
        }

        return $this->basePath . '/' . str_replace('.', '/', $template) . '.php';
    }

    public function render(string $template, array $data = []): string
    {
        $file = $this->path($template);
        if (!is_file($file)) {
            throw new RuntimeException('View not found: ' . $template);
        }

        $previousLevel = count($this->sectionStack);
        $hadLayout = isset($this->layout);
        $contents = $this->evaluate($file, array_merge($this->shared, $data));

        // A view that calls extend() renders inside the layout it named.
        if (!$hadLayout && isset($this->layout)) {
            $layout = $this->layout;
            $layoutData = $this->layoutData;
            unset($this->layout, $this->layoutData);
            $this->sections['content'] = $contents;
            $contents = $this->render($layout, array_merge($this->shared, $layoutData));
        }

        // Restore the stack if a nested render left sections open.
        while (count($this->sectionStack) > $previousLevel) {
            array_pop($this->sectionStack);
        }

        return $contents;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function evaluate(string $file, array $data): string
    {
        $view = $this;
        ob_start();
        try {
            (static function (array $__data, string $__file) use ($view): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })($data, $file);
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
        return (string) ob_get_clean();
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
            return;
        }
        $this->sections[$name] = (string) ob_get_clean();
    }

    public function appendSection(string $name): void
    {
        $this->sections[$name] = ($this->sections[$name] ?? '') . (string) ob_get_clean();
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]) && trim($this->sections[$name]) !== '';
    }

    /**
     * Render a reusable component from resources/views/components.
     *
     * @param array<string, mixed> $attributes
     */
    public function component(string $name, array $attributes = []): string
    {
        $file = $this->path('components.' . $name);
        if (!is_file($file)) {
            throw new RuntimeException('Component not found: ' . $name);
        }
        return $this->evaluate($file, array_merge($this->shared, $attributes));
    }

    /** Include a partial template without any layout handling. */
    public function partial(string $template, array $data = []): string
    {
        $file = $this->path($template);
        if (!is_file($file)) {
            throw new RuntimeException('Partial not found: ' . $template);
        }
        return $this->evaluate($file, array_merge($this->shared, $data));
    }

    /**
     * Declare the layout the rendered content should be wrapped in.
     *
     * @param array<string, mixed> $data
     */
    public function extend(string $layout, array $data = []): void
    {
        $this->layout = $layout;
        $this->layoutData = $data;
    }

    public function flushSections(): void
    {
        $this->sections = [];
        $this->sectionStack = [];
        unset($this->layout, $this->layoutData);
        $this->layoutData = [];
    }

    public function basePath(): string
    {
        return $this->basePath;
    }
}
