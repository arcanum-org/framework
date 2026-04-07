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
 *
 * Dependency tracking: store() accepts an optional list of file paths
 * that the template was compiled against (layouts, includes). Those
 * paths are written into the cache file as a JSON header comment, and
 * isFresh() checks all of them in addition to the main template path.
 * This ensures editing a layout or partial invalidates every template
 * that depends on it.
 */
final class TemplateCache
{
    /**
     * Header comment marker. The deps line is the very first line of the
     * cache file when present, in this exact format:
     *
     *     <?php /* arcanum-deps: ["/abs/path/one","/abs/path/two"] *\/ ?>
     *
     * The closing PHP tag plus newline keeps the file valid PHP whether
     * the rest of the file starts with PHP code or HTML.
     */
    private const DEPS_PREFIX = '<?php /* arcanum-deps: ';
    private const DEPS_SUFFIX = ' */ ?>';

    public function __construct(
        private readonly string $cacheDirectory,
        private readonly Reader $reader = new Reader(),
        private readonly Writer $writer = new Writer(),
        private readonly FileSystem $fileSystem = new FileSystem(),
    ) {
    }

    /**
     * Check whether a cached compiled template is fresh.
     *
     * Returns false when:
     *   - caching is disabled (empty cache directory)
     *   - the cache file doesn't exist
     *   - the cache file is older than the main template
     *   - any tracked dependency (layout, include, partial) is newer
     *     than the cache file
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

        if ($cacheMtime < $sourceMtime) {
            return false;
        }

        // Check tracked dependencies (layouts, includes) recorded at compile time.
        foreach ($this->readDependencies($cachePath) as $depPath) {
            $depMtime = @filemtime($depPath);
            // If a dep was deleted, treat as stale — recompile to discover the
            // missing-include error properly instead of serving a stale cache.
            if ($depMtime === false || $depMtime > $cacheMtime) {
                return false;
            }
        }

        return true;
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
     *
     * If the cache file has a dependency-tracking header, it's stripped
     * before returning so the caller gets pure compiled PHP suitable for
     * eval() or include.
     */
    public function load(string $templatePath): string
    {
        $contents = $this->reader->read($this->cachePath($templatePath));

        return $this->stripDependencyHeader($contents);
    }

    /**
     * Store compiled PHP to the cache, optionally with a list of file
     * dependencies (layouts, includes) that should trigger recompilation
     * when modified.
     *
     * No-op when caching is disabled (cacheDirectory is empty), so the
     * "disabled" sentinel can't accidentally write to the filesystem root.
     *
     * @param list<string> $dependencies
     */
    public function store(string $templatePath, string $compiledPhp, array $dependencies = []): void
    {
        if ($this->cacheDirectory === '') {
            return;
        }

        $payload = $this->buildDependencyHeader($dependencies) . $compiledPhp;
        $this->writer->write($this->cachePath($templatePath), $payload);
    }

    /**
     * Build the dependency header line that prefixes a cached compile.
     *
     * Returns an empty string when no dependencies are tracked, so the
     * cache file format stays identical to pre-dependency-tracking output
     * for templates that don't use @extends or @include.
     *
     * @param list<string> $dependencies
     */
    private function buildDependencyHeader(array $dependencies): string
    {
        if ($dependencies === []) {
            return '';
        }

        $encoded = json_encode($dependencies, JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return '';
        }

        return self::DEPS_PREFIX . $encoded . self::DEPS_SUFFIX . "\n";
    }

    /**
     * Read the dependency list out of a cache file's header.
     *
     * Reads only the first line (header is one line by construction) and
     * decodes the JSON array between the prefix and suffix. Returns an
     * empty list for cache files without a header (templates that have
     * no layout/include dependencies).
     *
     * @return list<string>
     */
    private function readDependencies(string $cachePath): array
    {
        $handle = @fopen($cachePath, 'r');
        if ($handle === false) {
            return [];
        }

        $firstLine = fgets($handle);
        fclose($handle);

        if ($firstLine === false) {
            return [];
        }

        $firstLine = rtrim($firstLine, "\r\n");

        if (
            !str_starts_with($firstLine, self::DEPS_PREFIX)
            || !str_ends_with($firstLine, self::DEPS_SUFFIX)
        ) {
            return [];
        }

        $jsonStart = strlen(self::DEPS_PREFIX);
        $jsonLength = strlen($firstLine) - $jsonStart - strlen(self::DEPS_SUFFIX);
        $json = substr($firstLine, $jsonStart, $jsonLength);

        /** @var mixed $decoded */
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        /** @var list<string> $deps */
        $deps = array_values(array_filter($decoded, 'is_string'));

        return $deps;
    }

    /**
     * Strip the dependency header from a cache file's contents on load.
     *
     * The header is intentionally a valid PHP-comment-wrapped line so
     * the file is still valid PHP if loaded directly via include — but
     * stripping it on load is cleaner for callers that want only the
     * compile output.
     */
    private function stripDependencyHeader(string $contents): string
    {
        if (!str_starts_with($contents, self::DEPS_PREFIX)) {
            return $contents;
        }

        $newlinePos = strpos($contents, "\n");
        if ($newlinePos === false) {
            return $contents;
        }

        return substr($contents, $newlinePos + 1);
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
