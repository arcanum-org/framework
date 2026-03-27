<?php

declare(strict_types=1);

namespace Arcanum\Parchment;

use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Symfony\Component\Filesystem\Exception\IOException;

class FileSystem
{
    public function __construct(
        private SymfonyFilesystem $filesystem = new SymfonyFilesystem(),
    ) {
    }

    /**
     * Copy a file.
     *
     * @throws \RuntimeException If the copy fails.
     */
    public function copy(string $source, string $target, bool $overwrite = false): void
    {
        try {
            $this->filesystem->copy($source, $target, $overwrite);
        } catch (IOException $e) {
            throw new \RuntimeException("Unable to copy '$source' to '$target'", 0, $e);
        }
    }

    /**
     * Move (rename) a file or directory.
     *
     * @throws \RuntimeException If the move fails.
     */
    public function move(string $source, string $target, bool $overwrite = false): void
    {
        try {
            $this->filesystem->rename($source, $target, $overwrite);
        } catch (IOException $e) {
            throw new \RuntimeException("Unable to move '$source' to '$target'", 0, $e);
        }
    }

    /**
     * Delete a file or directory (recursively).
     *
     * @throws \RuntimeException If the deletion fails.
     */
    public function delete(string $path): void
    {
        try {
            $this->filesystem->remove($path);
        } catch (IOException $e) {
            throw new \RuntimeException("Unable to delete: $path", 0, $e);
        }
    }

    /**
     * Create a directory (and parents if needed).
     *
     * @throws \RuntimeException If the directory cannot be created.
     */
    public function mkdir(string $path, int $mode = 0755): void
    {
        try {
            $this->filesystem->mkdir($path, $mode);
        } catch (IOException $e) {
            throw new \RuntimeException("Unable to create directory: $path", 0, $e);
        }
    }

    /**
     * Check if a path exists (file or directory).
     */
    public function exists(string $path): bool
    {
        return $this->filesystem->exists($path);
    }

    /**
     * Check if a path is a directory.
     */
    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * Check if a path is a regular file.
     */
    public function isFile(string $path): bool
    {
        return is_file($path);
    }
}
