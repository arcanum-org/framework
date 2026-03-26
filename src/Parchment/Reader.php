<?php

declare(strict_types=1);

namespace Arcanum\Parchment;

class Reader
{
    /**
     * Read the entire contents of a file as a string.
     *
     * @throws \RuntimeException If the file cannot be read.
     */
    public function read(string $path): string
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException("Unable to read file: $path");
        }

        return $contents;
    }

    /**
     * Read a file and return its contents as an array of lines.
     *
     * @return string[]
     * @throws \RuntimeException If the file cannot be read.
     */
    public function lines(string $path): array
    {
        $lines = @file($path, \FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            throw new \RuntimeException("Unable to read file: $path");
        }

        return $lines;
    }

    /**
     * Read a file and decode its contents as JSON.
     *
     * @return mixed
     * @throws \RuntimeException If the file cannot be read or the JSON is invalid.
     */
    public function json(string $path): mixed
    {
        $contents = $this->read($path);

        try {
            return json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException("Invalid JSON in file: $path", 0, $e);
        }
    }

    /**
     * Check if a file exists and is a regular file.
     */
    public function exists(string $path): bool
    {
        return is_file($path);
    }
}
