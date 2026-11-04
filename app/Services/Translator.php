<?php

declare(strict_types=1);

namespace App\Services;

/**
 * File-based translator. Each locale owns a directory of PHP files; the
 * filename is the top-level key segment (e.g. `products.add_to_cart`).
 */
final class Translator
{
    /** @var array<string, array<string, mixed>> */
    private array $loaded = [];

    /** @var array<string, string> */
    private array $messages = [];

    public function __construct(
        private string $path,
        private string $locale,
        private string $fallback = 'en'
    ) {
        $this->load($this->locale);
        if ($this->locale !== $this->fallback) {
            $this->load($this->fallback);
        }
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        if (!preg_match('/^[a-z]{2}(-[A-Za-z]{2})?$/', $locale)) {
            return;
        }
        if (!is_dir($this->path . '/' . $locale)) {
            $locale = $this->fallback;
        }
        $this->locale = $locale;
        $this->load($locale);
    }

    private function load(string $locale): void
    {
        if (isset($this->loaded[$locale])) {
            return;
        }

        $directory = $this->path . '/' . $locale;
        $this->loaded[$locale] = [];

        if (!is_dir($directory)) {
            return;
        }

        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $group = basename($file, '.php');
            $contents = require $file;
            if (is_array($contents)) {
                $this->loaded[$locale][$group] = $contents;
            }
        }
    }

    public function get(string $key, array $replace = []): string
    {
        $line = $this->find($this->locale, $key);

        if ($line === null && $this->locale !== $this->fallback) {
            $line = $this->find($this->fallback, $key);
        }

        // Untranslated keys fall back to a humanised version of the key itself.
        if ($line === null) {
            $line = ucfirst(str_replace('_', ' ', (string) strrchr('.' . $key, '.')));
        }

        if (!is_string($line)) {
            return $key;
        }

        return $this->interpolate($line, $replace);
    }

    /** @return mixed */
    private function find(string $locale, string $key): mixed
    {
        $segments = explode('.', $key);
        $value = $this->loaded[$locale] ?? [];

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return null;
            }
        }

        return $value;
    }

    /** @param array<string, mixed> $replace */
    private function interpolate(string $line, array $replace): string
    {
        if ($replace === []) {
            return $line;
        }

        $names = [];
        foreach ($replace as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }
            $names[':' . $key] = (string) $value;
        }

        return strtr($line, $names);
    }

    public function has(string $key): bool
    {
        return $this->find($this->locale, $key) !== null
            || ($this->locale !== $this->fallback && $this->find($this->fallback, $key) !== null);
    }

    /** @return list<string> */
    public function availableLocales(): array
    {
        $locales = [];
        foreach (glob($this->path . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $locales[] = basename($directory);
        }
        sort($locales);
        return $locales;
    }
}
