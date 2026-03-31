<?php

declare(strict_types=1);

namespace Arcanum\Atlas;

use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Searcher;
use Arcanum\Parchment\Writer;
use Arcanum\Toolkit\Strings;

/**
 * Auto-discovers page classes from the filesystem and registers them
 * as GET-only custom routes in a RouteMap.
 *
 * Class names are converted to URL paths via kebab-case:
 *   - App\Pages\Index        → /
 *   - App\Pages\Thing        → /thing
 *   - App\Pages\Docs\GettingStarted → /docs/getting-started
 *
 * Discovery results can be cached to avoid filesystem scanning on every request.
 */
final class PageDiscovery
{
    /**
     * @param string $namespace The Pages namespace (e.g., 'App\Pages').
     * @param string $directory Absolute path to the pages directory.
     * @param string $defaultFormat Default response format for pages.
     * @param string $cachePath Path to the cache file. Empty string disables caching.
     * @param int $cacheMaxAge Maximum cache age in seconds. 0 means no expiry.
     */
    public function __construct(
        private string $namespace,
        private string $directory,
        private string $defaultFormat = 'html',
        private string $cachePath = '',
        private int $cacheMaxAge = 0,
        private Reader $reader = new Reader(),
        private Writer $writer = new Writer(),
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
            $routeMap->register($path, $dtoClass, ['GET'], $format);
        }
    }

    /**
     * Discover page classes, returning a path → class map.
     *
     * @return array<string, string> path → fully-qualified class name
     */
    public function discover(): array
    {
        if ($this->cachePath !== '' && $this->isCacheValid()) {
            /** @var array<string, string> */
            return $this->reader->require($this->cachePath);
        }

        $pages = $this->scan();

        if ($this->cachePath !== '') {
            $this->writer->write(
                $this->cachePath,
                '<?php return ' . var_export($pages, true) . ';' . \PHP_EOL,
            );
        }

        return $pages;
    }

    /**
     * Check if the cache file exists and is not expired.
     */
    private function isCacheValid(): bool
    {
        if (!$this->fileSystem->isFile($this->cachePath)) {
            return false;
        }

        if ($this->cacheMaxAge === 0) {
            return true;
        }

        $modifiedAt = filemtime($this->cachePath);
        if ($modifiedAt === false) {
            return false;
        }

        return (time() - $modifiedAt) < $this->cacheMaxAge;
    }

    /**
     * Scan the pages directory for PHP classes.
     *
     * @return array<string, string> path → fully-qualified class name
     */
    private function scan(): array
    {
        if (!$this->fileSystem->isDirectory($this->directory)) {
            return [];
        }

        $pages = [];

        foreach (Searcher::findAll('*.php', $this->directory) as $file) {
            $relativePath = $file->getRelativePathname();
            // Remove .php extension
            $relativePath = substr($relativePath, 0, -4);

            // Convert directory separators to namespace separators
            $classSegments = explode(DIRECTORY_SEPARATOR, $relativePath);

            // Build the fully-qualified class name
            $className = $this->namespace . '\\' . implode('\\', $classSegments);

            // Skip handler classes — they're resolved by convention from the DTO
            if (str_ends_with($className, 'Handler')) {
                continue;
            }

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
        if ($this->cachePath !== '' && $this->fileSystem->isFile($this->cachePath)) {
            $this->fileSystem->delete($this->cachePath);
        }
    }
}
