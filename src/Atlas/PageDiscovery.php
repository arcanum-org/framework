<?php

declare(strict_types=1);

namespace Arcanum\Atlas;

use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Searcher;
use Arcanum\Toolkit\Strings;
use Psr\SimpleCache\CacheInterface;

/**
 * Auto-discovers page classes from the filesystem and registers them
 * as GET-only custom routes in a RouteMap.
 *
 * Class names are converted to URL paths via kebab-case:
 *   - App\Pages\Index        → /
 *   - App\Pages\Thing        → /thing
 *   - App\Pages\Docs\GettingStarted → /docs/getting-started
 *
 * Discovery results can be cached via any PSR-16 driver to avoid
 * filesystem scanning on every request.
 */
final class PageDiscovery
{
    private const CACHE_KEY = 'page_discovery';

    /**
     * @param string $namespace The Pages namespace (e.g., 'App\Pages').
     * @param string $directory Absolute path to the pages directory.
     * @param string $defaultFormat Default response format for pages.
     * @param CacheInterface|null $cache PSR-16 cache driver. Null disables caching.
     * @param int $cacheTtl Cache TTL in seconds. 0 means no expiry (null TTL to PSR-16).
     */
    public function __construct(
        private string $namespace,
        private string $directory,
        private string $defaultFormat = 'html',
        private CacheInterface|null $cache = null,
        private int $cacheTtl = 0,
        private FileSystem $fileSystem = new FileSystem(),
    ) {
    }

    /**
     * Discover pages and register them as custom routes.
     *
     * Uses the cache if available, otherwise scans the filesystem.
     *
     * @param array<string, string> $formatOverrides Path → format overrides from config.
     */
    public function register(RouteMap $routeMap, array $formatOverrides = []): void
    {
        $pages = $this->discover();

        foreach ($pages as $path => $dtoClass) {
            $format = $formatOverrides[$path] ?? $this->defaultFormat;
            $routeMap->register($path, $dtoClass, ['GET'], $format, isPage: true);
        }
    }

    /**
     * Discover page classes, returning a path → class map.
     *
     * @return array<string, string> path → fully-qualified class name
     */
    public function discover(): array
    {
        if ($this->cache !== null) {
            /** @var array<string, string>|null $cached */
            $cached = $this->cache->get(self::CACHE_KEY);

            if ($cached !== null) {
                return $cached;
            }
        }

        $pages = $this->scan();

        if ($this->cache !== null) {
            $ttl = $this->cacheTtl > 0 ? $this->cacheTtl : null;
            $this->cache->set(self::CACHE_KEY, $pages, $ttl);
        }

        return $pages;
    }

    /**
     * Scan the pages directory for template files.
     *
     * A page exists because a template exists, not because a PHP class exists.
     * The dtoClass is a virtual class name that may or may not have a matching
     * PHP file — the kernel handles the distinction.
     *
     * @return array<string, string> path → fully-qualified virtual class name
     */
    private function scan(): array
    {
        if (!$this->fileSystem->isDirectory($this->directory)) {
            return [];
        }

        $pages = [];

        foreach (Searcher::findAll('*.html', $this->directory) as $file) {
            // Skip underscore-prefixed partials (include-only, not routable)
            if (str_starts_with($file->getFilename(), '_')) {
                continue;
            }

            $relativePath = $file->getRelativePathname();
            // Remove .html extension
            $relativePath = substr($relativePath, 0, -5);

            // Convert directory separators to namespace separators
            $classSegments = explode(DIRECTORY_SEPARATOR, $relativePath);

            // Build the virtual fully-qualified class name
            $className = $this->namespace . '\\' . implode('\\', $classSegments);

            // Derive the URL path from the class segments
            $path = $this->classToPath($classSegments);

            $pages[$path] = $className;
        }

        return $pages;
    }

    /**
     * Convert class name segments to a URL path.
     *
     * Index → /
     * Thing → /thing
     * Docs/GettingStarted → /docs/getting-started
     *
     * @param list<string> $segments PascalCase class name segments.
     */
    private function classToPath(array $segments): string
    {
        $last = end($segments);

        // Index at the root level maps to /
        if ($last === 'Index' && count($segments) === 1) {
            return '/';
        }

        $kebabSegments = array_map(
            static fn(string $segment): string => Strings::kebab($segment),
            $segments,
        );

        return '/' . implode('/', $kebabSegments);
    }

    /**
     * Clear the discovery cache.
     */
    public function clearCache(): void
    {
        $this->cache?->delete(self::CACHE_KEY);
    }
}
