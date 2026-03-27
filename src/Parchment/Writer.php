<?php

declare(strict_types=1);

namespace Arcanum\Parchment;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

class Writer
{
    public function __construct(
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * Write a string to a file atomically.
     *
     * Creates the directory if it doesn't exist. Uses a temp file and rename
     * to prevent partial writes.
     *
     * @throws \RuntimeException If the file cannot be written.
     */
    public function write(string $path, string $contents): void
    {
        try {
            $this->filesystem->dumpFile($path, $contents);
        } catch (IOException $e) {
            throw new \RuntimeException("Unable to write file: $path", 0, $e);
        }
    }

    /**
     * Append a string to a file.
     *
     * Creates the file and directory if they don't exist.
     *
     * @throws \RuntimeException If the file cannot be written.
     */
    public function append(string $path, string $contents): void
    {
        try {
            $this->filesystem->appendToFile($path, $contents);
        } catch (IOException $e) {
            throw new \RuntimeException("Unable to append to file: $path", 0, $e);
        }
    }

    /**
     * Write data to a file as JSON.
     *
     * @throws \RuntimeException If the data cannot be encoded or the file cannot be written.
     */
    public function json(string $path, mixed $data, int $flags = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES): void
    {
        try {
            $encoded = json_encode($data, $flags | \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException("Unable to encode JSON for file: $path", 0, $e);
        }

        $this->write($path, $encoded . \PHP_EOL);
    }
}
