<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Searcher;
use Psr\SimpleCache\CacheInterface;

/**
 * Discovers domain-scoped template helpers from co-located Helpers.php files.
 *
 * Follows the same convention as MiddlewareDiscovery: Helpers.php files
 * in domain directories return an array mapping aliases to class names.
 * Each file's directory maps to a namespace prefix, so helpers registered
 * in app/Domain/Shop/Helpers.php only apply to DTOs under App\Domain\Shop.
 *
 * Example Helpers.php:
 *
 *     return [
 *         'Cart' => CartHelper::class,
 *     ];
 */
class HelperDiscovery
{
    private const CACHE_KEY = 'helper_discovery';

    public function __construct(
        private readonly string $rootNamespace,
        private readonly string $rootDirectory,
        private readonly ?CacheInterface $cache = null,
        private readonly int $cacheTtl = 0,
        private readonly Reader $reader = new Reader(),
        private readonly FileSystem $fileSystem = new FileSystem(),
    ) {
    }

    /**
     * Discover all Helpers.php files and return the prefix → alias → class map.
     *
     * @return array<string, array<string, string>> namespace prefix => [alias => class name]
     */
    public function discover(): array
    {
        if ($this->cache !== null) {
            $cached = $this->cache->get(self::CACHE_KEY);
            if (is_array($cached)) {
                /** @var array<string, array<string, string>> */
                return $cached;
            }
        }

        $result = $this->scan();

        if ($this->cache !== null) {
            $this->cache->set(self::CACHE_KEY, $result, $this->cacheTtl > 0 ? $this->cacheTtl : null);
        }

        return $result;
    }

    public function clearCache(): void
    {
        $this->cache?->delete(self::CACHE_KEY);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function scan(): array
    {
        if (!$this->fileSystem->isDirectory($this->rootDirectory)) {
            return [];
        }

        $result = [];

        foreach (Searcher::findAll('Helpers.php', $this->rootDirectory) as $file) {
            $relativePath = $file->getRelativePath();
            $prefix = $this->pathToNamespace($relativePath);

            $helpers = $this->reader->require($file->getRealPath());

            if (is_array($helpers) && $helpers !== []) {
                /** @var array<string, string> $helpers */
                $result[$prefix] = $helpers;
            }
        }

        return $result;
    }

    private function pathToNamespace(string $relativePath): string
    {
        if ($relativePath === '') {
            return $this->rootNamespace;
        }

        $namespacePart = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);

        return $this->rootNamespace . '\\' . $namespacePart;
    }
}
