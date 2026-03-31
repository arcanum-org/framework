<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Writer;

/**
 * Manages compiled template caching.
 *
 * When the cache directory is an empty string, caching is disabled
 * and isFresh() always returns false.
 */
final class TemplateCache
{
    public function __construct(
        private readonly string $cacheDirectory,
        private readonly Reader $reader = new Reader(),
        private readonly Writer $writer = new Writer(),
        private readonly FileSystem $fileSystem = new FileSystem(),
    ) {
    }

    /**
     * Check whether a cached compiled template is fresh (exists and not older
     * than the source template).
     */
    public function isFresh(string $templatePath): bool
    {
        if ($this->cacheDirectory === '') {
            return false;
        }

        $cachePath = $this->cachePath($templatePath);

        if (!$this->fileSystem->isFile($cachePath)) {
            return false;
        }

        $cacheMtime = filemtime($cachePath);
        $sourceMtime = filemtime($templatePath);

        if ($cacheMtime === false || $sourceMtime === false) {
            return false;
        }

        return $cacheMtime >= $sourceMtime;
    }

    /**
     * Deterministic cache file path for a given template.
     */
    public function cachePath(string $templatePath): string
    {
        return $this->cacheDirectory
            . DIRECTORY_SEPARATOR
            . md5($templatePath)
            . '.php';
    }

    /**
     * Load compiled PHP from the cache file.
     */
    public function load(string $templatePath): string
    {
        return $this->reader->read($this->cachePath($templatePath));
    }

    /**
     * Store compiled PHP to the cache.
     */
    public function store(string $templatePath, string $compiledPhp): void
    {
        $this->writer->write($this->cachePath($templatePath), $compiledPhp);
    }

    /**
     * Remove all cached templates.
     */
    public function clear(): void
    {
        if ($this->cacheDirectory === '' || !$this->fileSystem->isDirectory($this->cacheDirectory)) {
            return;
        }

        $this->fileSystem->delete($this->cacheDirectory);
    }
}
