<?php

declare(strict_types=1);

namespace Arcanum\Parchment;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * A temporary file that automatically deletes itself when destroyed.
 */
class TempFile
{
    private string $path;
    private bool $deleted = false;

    /**
     * Create a new temporary file.
     *
     * @param string $directory Directory to create the file in. Defaults to system temp dir.
     * @param string $prefix    Filename prefix.
     * @throws \RuntimeException If the temp file cannot be created.
     */
    public function __construct(
        string $directory = '',
        string $prefix = 'arcanum_',
        private Filesystem $filesystem = new Filesystem(),
    ) {
        if ($directory === '') {
            $directory = sys_get_temp_dir();
        }

        try {
            $this->path = $this->filesystem->tempnam($directory, $prefix);
        } catch (IOException $e) {
            throw new \RuntimeException("Unable to create temporary file in: $directory", 0, $e);
        }
    }

    /**
     * Get the path to the temporary file.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Write contents to the temporary file.
     *
     * @throws \RuntimeException If the file cannot be written.
     */
    public function write(string $contents): void
    {
        try {
            $this->filesystem->dumpFile($this->path, $contents);
        } catch (IOException $e) {
            throw new \RuntimeException("Unable to write to temporary file: $this->path", 0, $e);
        }
    }

    /**
     * Read the contents of the temporary file.
     *
     * @throws \RuntimeException If the file cannot be read.
     */
    public function read(): string
    {
        try {
            return $this->filesystem->readFile($this->path);
        } catch (IOException $e) {
            throw new \RuntimeException("Unable to read temporary file: $this->path", 0, $e);
        }
    }

    /**
     * Delete the temporary file immediately.
     */
    public function delete(): void
    {
        if ($this->deleted) {
            return;
        }

        try {
            $this->filesystem->remove($this->path);
        } catch (IOException) {
            // Best-effort cleanup — don't throw from cleanup.
        }

        $this->deleted = true;
    }

    /**
     * Auto-clean on destruction.
     */
    public function __destruct()
    {
        $this->delete();
    }
}
