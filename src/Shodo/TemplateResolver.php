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
        private readonly string $errorTemplatesDirectory = '',
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
        $path = $this->classToPath($dtoClass, '.' . $this->extension);

        if ($path === null || !$this->fileSystem->isFile($path)) {
            return null;
        }

        return $path;
    }

    /**
     * Resolve a status-specific template for a DTO class.
     *
     * Works for any HTTP status code — error templates (422, 500),
     * success templates (200, 201), or anything in between.
     *
     * Resolution order:
     *   1. Co-located: {DtoClass}.{status}.{format} next to the DTO
     *   2. App-wide:   {statusTemplatesDirectory}/{status}.{format}
     *   3. null
     *
     * @param string $dtoClass Fully-qualified DTO class name.
     * @param int $statusCode HTTP status code.
     * @param string $format Response format (e.g., 'html', 'json').
     */
    public function resolveForStatus(string $dtoClass, int $statusCode, string $format): ?string
    {
        $suffix = '.' . $statusCode . '.' . $format;

        // 1. Co-located: e.g., app/Domain/Guestbook/Command/AddEntry.422.html
        $coLocated = $this->classToPath($dtoClass, $suffix);
        if ($coLocated !== null && $this->fileSystem->isFile($coLocated)) {
            return $coLocated;
        }

        // 2. App-wide: e.g., app/Templates/errors/422.html
        if ($this->errorTemplatesDirectory !== '') {
            $appWide = $this->errorTemplatesDirectory
                . DIRECTORY_SEPARATOR . $statusCode . '.' . $format;

            if ($this->fileSystem->isFile($appWide)) {
                return $appWide;
            }
        }

        return null;
    }

    /**
     * Map a DTO class name to an absolute filesystem path with the given suffix.
     *
     * Returns null if the class doesn't belong to the root namespace.
     */
    private function classToPath(string $dtoClass, string $suffix): ?string
    {
        if ($dtoClass === '') {
            return null;
        }

        $prefix = $this->rootNamespace . '\\';
        if (!str_starts_with($dtoClass, $prefix)) {
            return null;
        }

        $relative = substr($dtoClass, strlen($prefix));
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative);

        // PSR-4: root namespace maps to lowercase directory.
        // App\ → app/, so "App" → "app".
        $namespaceDir = lcfirst($this->rootNamespace);

        return $this->rootDirectory
            . DIRECTORY_SEPARATOR
            . $namespaceDir
            . DIRECTORY_SEPARATOR
            . $relativePath
            . $suffix;
    }
}
