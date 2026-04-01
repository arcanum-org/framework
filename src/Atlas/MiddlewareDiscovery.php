<?php

declare(strict_types=1);

namespace Arcanum\Atlas;

use Arcanum\Atlas\Attribute\After;
use Arcanum\Atlas\Attribute\Before;
use Arcanum\Atlas\Attribute\HttpMiddleware;
use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Searcher;
use Psr\SimpleCache\CacheInterface;

/**
 * Discovers per-route middleware from two sources:
 *
 * 1. Co-located Middleware.php files — apply to all DTOs beneath
 *    that directory, based on namespace prefix.
 * 2. PHP attributes on DTO classes — HttpMiddleware, Before, After.
 *
 * Directory middleware is outer (runs first on the way in, last on the
 * way out). Attribute middleware is inner. When multiple directory levels
 * exist, shallower directories are outermost.
 *
 * Results are cached via any PSR-16 driver.
 */
final class MiddlewareDiscovery
{
    private const CACHE_KEY = 'middleware_discovery';

    /**
     * @param string $rootNamespace The app's root namespace (e.g., 'App').
     * @param string $rootDirectory Absolute path to the app directory.
     * @param CacheInterface|null $cache PSR-16 cache driver. Null disables caching.
     * @param int $cacheTtl Cache TTL in seconds. 0 means no expiry.
     */
    public function __construct(
        private readonly string $rootNamespace,
        private readonly string $rootDirectory,
        private readonly CacheInterface|null $cache = null,
        private readonly int $cacheTtl = 0,
        private readonly Reader $reader = new Reader(),
        private readonly FileSystem $fileSystem = new FileSystem(),
    ) {
    }

    /**
     * Discover all per-route middleware, returning a merged map.
     *
     * @return array<string, RouteMiddleware> dtoClass => RouteMiddleware
     */
    public function discover(): array
    {
        if ($this->cache !== null) {
            /** @var array<string, array{http: list<string>, before: list<string>, after: list<string>}>|null $cached */
            $cached = $this->cache->get(self::CACHE_KEY);

            if ($cached !== null) {
                return $this->hydrateCache($cached);
            }
        }

        $result = $this->scan();

        if ($this->cache !== null) {
            $ttl = $this->cacheTtl > 0 ? $this->cacheTtl : null;
            $this->cache->set(self::CACHE_KEY, $this->dehydrateCache($result), $ttl);
        }

        return $result;
    }

    /**
     * Clear the discovery cache.
     */
    public function clearCache(): void
    {
        $this->cache?->delete(self::CACHE_KEY);
    }

    /**
     * @return array<string, RouteMiddleware>
     */
    private function scan(): array
    {
        if (!$this->fileSystem->isDirectory($this->rootDirectory)) {
            return [];
        }

        $directoryMiddleware = $this->scanDirectoryMiddleware();
        $attributeMiddleware = $this->scanAttributes();

        return $this->mergeAll($directoryMiddleware, $attributeMiddleware);
    }

    /**
     * Scan for Middleware.php files and map them to namespace prefixes.
     *
     * @return array<string, RouteMiddleware> namespacePrefix => RouteMiddleware
     */
    private function scanDirectoryMiddleware(): array
    {
        $result = [];

        foreach (Searcher::findAll('Middleware.php', $this->rootDirectory) as $file) {
            $relativePath = $file->getRelativePath();

            // Build namespace prefix from the file's directory
            $namespacePrefix = $this->rootNamespace;
            if ($relativePath !== '') {
                $namespacePrefix .= '\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
            }

            /** @var array{http?: list<string>, before?: list<string>, after?: list<string>} $config */
            $config = $this->reader->require($file->getRealPath());

            $result[$namespacePrefix] = new RouteMiddleware(
                http: $config['http'] ?? [],
                before: $config['before'] ?? [],
                after: $config['after'] ?? [],
            );
        }

        return $result;
    }

    /**
     * Scan PHP files for DTO classes with middleware attributes.
     *
     * @return array<string, RouteMiddleware> dtoClass => RouteMiddleware
     */
    private function scanAttributes(): array
    {
        $result = [];

        foreach (Searcher::findAll('*.php', $this->rootDirectory) as $file) {
            $filename = $file->getFilename();

            // Skip Middleware.php files and Handler files
            if ($filename === 'Middleware.php' || str_ends_with($filename, 'Handler.php')) {
                continue;
            }

            $relativePath = $file->getRelativePathname();
            // Must be inside a Command/ or Query/ directory
            if (
                !str_contains($relativePath, 'Command' . DIRECTORY_SEPARATOR)
                && !str_contains($relativePath, 'Query' . DIRECTORY_SEPARATOR)
            ) {
                continue;
            }

            $className = $this->pathToClassName($relativePath);

            if (!class_exists($className)) {
                continue;
            }

            $mw = $this->readAttributes($className);
            if (!$mw->isEmpty()) {
                $result[$className] = $mw;
            }
        }

        return $result;
    }

    /**
     * Read middleware attributes from a class.
     *
     * @param class-string $className
     */
    private function readAttributes(string $className): RouteMiddleware
    {
        $ref = new \ReflectionClass($className);

        $http = [];
        foreach ($ref->getAttributes(HttpMiddleware::class) as $attr) {
            $http[] = $attr->newInstance()->class;
        }

        $before = [];
        foreach ($ref->getAttributes(Before::class) as $attr) {
            $before[] = $attr->newInstance()->class;
        }

        $after = [];
        foreach ($ref->getAttributes(After::class) as $attr) {
            $after[] = $attr->newInstance()->class;
        }

        return new RouteMiddleware($http, $before, $after);
    }

    /**
     * Convert a relative file path to a fully-qualified class name.
     */
    private function pathToClassName(string $relativePath): string
    {
        // Remove .php extension
        $withoutExtension = substr($relativePath, 0, -4);
        $namespacePart = str_replace(DIRECTORY_SEPARATOR, '\\', $withoutExtension);

        return $this->rootNamespace . '\\' . $namespacePart;
    }

    /**
     * Merge directory middleware with attribute middleware for each DTO.
     *
     * For each DTO class, walks up its namespace hierarchy to collect
     * matching directory middleware (shallowest = outermost), then merges
     * with attribute middleware (innermost).
     *
     * @param array<string, RouteMiddleware> $directoryMiddleware namespacePrefix => RouteMiddleware
     * @param array<string, RouteMiddleware> $attributeMiddleware dtoClass => RouteMiddleware
     * @return array<string, RouteMiddleware> dtoClass => merged RouteMiddleware
     */
    private function mergeAll(array $directoryMiddleware, array $attributeMiddleware): array
    {
        // Collect all DTO classes from both sources
        $allDtoClasses = array_keys($attributeMiddleware);

        // Also include DTOs that only have directory middleware (no attributes)
        foreach (Searcher::findAll('*.php', $this->rootDirectory) as $file) {
            $filename = $file->getFilename();
            if ($filename === 'Middleware.php' || str_ends_with($filename, 'Handler.php')) {
                continue;
            }
            $relativePath = $file->getRelativePathname();
            if (
                !str_contains($relativePath, 'Command' . DIRECTORY_SEPARATOR)
                && !str_contains($relativePath, 'Query' . DIRECTORY_SEPARATOR)
            ) {
                continue;
            }
            $className = $this->pathToClassName($relativePath);
            if (!in_array($className, $allDtoClasses, true)) {
                $allDtoClasses[] = $className;
            }
        }

        // Sort directory prefixes by length (shallowest first)
        $sortedPrefixes = array_keys($directoryMiddleware);
        usort($sortedPrefixes, fn(string $a, string $b) => strlen($a) <=> strlen($b));

        $result = [];

        foreach ($allDtoClasses as $dtoClass) {
            // Collect matching directory middleware (shallowest = outermost)
            $dirMw = new RouteMiddleware();
            foreach ($sortedPrefixes as $prefix) {
                if (str_starts_with($dtoClass, $prefix . '\\')) {
                    $dirMw = $dirMw->merge($directoryMiddleware[$prefix]);
                }
            }

            // Merge with attribute middleware (innermost)
            $attrMw = $attributeMiddleware[$dtoClass] ?? new RouteMiddleware();
            $merged = $dirMw->merge($attrMw);

            if (!$merged->isEmpty()) {
                $result[$dtoClass] = $merged;
            }
        }

        return $result;
    }

    /**
     * @param array<string, array{http: list<string>, before: list<string>, after: list<string>}> $cached
     * @return array<string, RouteMiddleware>
     */
    private function hydrateCache(array $cached): array
    {
        $result = [];
        foreach ($cached as $dtoClass => $data) {
            $result[$dtoClass] = new RouteMiddleware(
                http: $data['http'],
                before: $data['before'],
                after: $data['after'],
            );
        }
        return $result;
    }

    /**
     * @param array<string, RouteMiddleware> $middleware
     * @return array<string, array{http: list<string>, before: list<string>, after: list<string>}>
     */
    private function dehydrateCache(array $middleware): array
    {
        $data = [];
        foreach ($middleware as $dtoClass => $mw) {
            $data[$dtoClass] = [
                'http' => $mw->http,
                'before' => $mw->before,
                'after' => $mw->after,
            ];
        }
        return $data;
    }
}
