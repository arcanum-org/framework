<?php

declare(strict_types=1);

namespace Arcanum\Session;

use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Searcher;
use Arcanum\Parchment\Writer;

/**
 * File-based session driver. One file per session.
 *
 * Session data is serialized with its expiry timestamp. Expired sessions
 * are lazily deleted on read and batch-deleted during garbage collection.
 */
final class FileSessionDriver implements SessionDriver, GarbageCollectable
{
    private readonly Reader $reader;
    private readonly Writer $writer;
    private readonly FileSystem $fileSystem;

    public function __construct(
        private readonly string $directory,
        Reader $reader = new Reader(),
        Writer $writer = new Writer(),
        FileSystem $fileSystem = new FileSystem(),
    ) {
        $this->reader = $reader;
        $this->writer = $writer;
        $this->fileSystem = $fileSystem;

        if (!$this->fileSystem->isDirectory($this->directory)) {
            $this->fileSystem->mkdir($this->directory);
        }
    }

    public function read(string $id): array
    {
        $path = $this->path($id);

        if (!$this->fileSystem->isFile($path)) {
            return [];
        }

        $raw = @unserialize($this->reader->read($path));

        if (!is_array($raw) || !isset($raw['data'], $raw['expiry'])) {
            $this->fileSystem->delete($path);
            return [];
        }

        if (is_int($raw['expiry']) && $raw['expiry'] <= time()) {
            $this->fileSystem->delete($path);
            return [];
        }

        return is_array($raw['data']) ? $raw['data'] : [];
    }

    public function write(string $id, array $data, int $ttl): void
    {
        $envelope = [
            'data' => $data,
            'expiry' => time() + $ttl,
        ];

        $this->writer->write($this->path($id), serialize($envelope));
    }

    public function destroy(string $id): void
    {
        $path = $this->path($id);

        if ($this->fileSystem->isFile($path)) {
            $this->fileSystem->delete($path);
        }
    }

    public function gc(int $maxLifetime): void
    {
        if (!$this->fileSystem->isDirectory($this->directory)) {
            return;
        }

        $now = time();

        foreach (Searcher::findAll('*.session', $this->directory) as $file) {
            $raw = @unserialize($this->reader->read($file->getRealPath()));

            if (!is_array($raw) || !isset($raw['expiry']) || (is_int($raw['expiry']) && $raw['expiry'] <= $now)) {
                $this->fileSystem->delete($file->getRealPath());
            }
        }
    }

    private function path(string $id): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $id . '.session';
    }
}
