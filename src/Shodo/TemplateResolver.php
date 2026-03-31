<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

use Arcanum\Parchment\FileSystem;

/**
 * Resolves a DTO class name to its co-located template file path.
 *
 * Uses PSR-4 convention: the root namespace maps to a directory under
 * the application root. For example, with root namespace "App" and root
 * directory "/var/www", the class App\Domain\Shop\Query\Products resolves
 * to /var/www/app/Domain/Shop/Query/Products.html.
 */
final class TemplateResolver
{
    public function __construct(
        private readonly string $rootDirectory,
        private readonly string $rootNamespace,
        private readonly string $extension = 'html',
        private readonly FileSystem $fileSystem = new FileSystem(),
    ) {
    }

    /**
     * Resolve a DTO class name to its template file path.
     *
     * Returns null if no template file exists for the given class.
     */
    public function resolve(string $dtoClass): ?string
    {
        if ($dtoClass === '') {
            return null;
        }

        // Strip the root namespace prefix and convert to a relative path.
        $prefix = $this->rootNamespace . '\\';
        if (!str_starts_with($dtoClass, $prefix)) {
            return null;
        }

        $relative = substr($dtoClass, strlen($prefix));
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative);

        // PSR-4: root namespace maps to lowercase directory.
        // App\ → app/, so "App" → "app".
        $namespaceDir = lcfirst($this->rootNamespace);

        $path = $this->rootDirectory
            . DIRECTORY_SEPARATOR
            . $namespaceDir
            . DIRECTORY_SEPARATOR
            . $relativePath
            . '.' . $this->extension;

        if (!$this->fileSystem->isFile($path)) {
            return null;
        }

        return $path;
    }
}
