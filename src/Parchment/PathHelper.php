<?php

declare(strict_types=1);

namespace Arcanum\Parchment;

use Symfony\Component\Filesystem\Path;

class PathHelper
{
    /**
     * Normalize a path by resolving '..' and '.' segments and fixing separators.
     */
    public static function normalize(string $path): string
    {
        return Path::canonicalize($path);
    }

    /**
     * Make a relative path absolute by resolving it against a base path.
     */
    public static function resolve(string $path, string $basePath): string
    {
        return Path::makeAbsolute($path, $basePath);
    }

    /**
     * Make a path relative to a base path.
     */
    public static function relative(string $path, string $basePath): string
    {
        return Path::makeRelative($path, $basePath);
    }

    /**
     * Get the file extension (without the leading dot).
     */
    public static function extension(string $path): string
    {
        return Path::getExtension($path);
    }

    /**
     * Get the filename without its extension.
     */
    public static function filenameWithoutExtension(string $path): string
    {
        return Path::getFilenameWithoutExtension($path);
    }

    /**
     * Get the directory portion of a path.
     */
    public static function directory(string $path): string
    {
        return Path::getDirectory($path);
    }

    /**
     * Join path segments together.
     */
    public static function join(string ...$paths): string
    {
        return Path::join(...$paths);
    }

    /**
     * Check if a path is absolute.
     */
    public static function isAbsolute(string $path): bool
    {
        return Path::isAbsolute($path);
    }

    /**
     * Check if a path is relative.
     */
    public static function isRelative(string $path): bool
    {
        return Path::isRelative($path);
    }
}
