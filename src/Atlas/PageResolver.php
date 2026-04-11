<?php

declare(strict_types=1);

namespace Arcanum\Atlas;

use Arcanum\Toolkit\Strings;

final class PageResolver
{
    /**
     * Registered page paths. Keys are normalized paths (e.g., '/thing', '/docs/getting-started').
     * Values are the default format for that page, or null to use the global default.
     *
     * @var array<string, string|null>
     */
    private array $pages = [];

    /**
     * @param string $namespace The Pages namespace (e.g., 'App\\Pages').
     * @param string $defaultFormat The default response format for pages.
     */
    public function __construct(
        private readonly string $namespace = 'App\\Pages',
        private readonly string $defaultFormat = 'html',
    ) {
    }

    /**
     * Register a path as a page.
     *
     * @param string $path The URL path (e.g., '/', '/thing', '/docs/getting-started').
     * @param string|null $format Optional per-page default format override.
     */
    public function register(string $path, string|null $format = null): void
    {
        $this->pages[$this->normalizePath($path)] = $format;
    }

    /**
     * Check if a path is registered as a page.
     */
    public function has(string $path): bool
    {
        return array_key_exists($this->normalizePath($path), $this->pages);
    }

    /**
     * Resolve a registered page path into a Route.
     *
     * @param string $path The URL path.
     * @param string|null $extensionFormat Format parsed from file extension, or null if no extension.
     */
    public function resolve(string $path, string|null $extensionFormat = null): Route
    {
        $normalized = $this->normalizePath($path);

        if (!array_key_exists($normalized, $this->pages)) {
            throw new UnresolvableRoute(sprintf(
                'Path "%s" is not registered as a page.',
                $path,
            ));
        }

        $className = $this->buildClassName($normalized);
        $pageFormat = $this->pages[$normalized];
        $format = $extensionFormat ?? $pageFormat ?? $this->defaultFormat;

        return new Route(
            dtoClass: $className,
            handlerPrefix: '',
            format: $format,
        );
    }

    /**
     * Build the fully-qualified class name from a normalized page path.
     */
    private function buildClassName(string $path): string
    {
        if ($path === '/') {
            return $this->namespace . '\\Index';
        }

        $segments = explode('/', trim($path, '/'));
        $pascalSegments = array_map(
            static fn(string $segment): string => Strings::pascal($segment),
            $segments,
        );

        return $this->namespace . '\\' . implode('\\', $pascalSegments);
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path;
    }
}
