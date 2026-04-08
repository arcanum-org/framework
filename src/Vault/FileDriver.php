<?php

declare(strict_types=1);

namespace Arcanum\Vault;

use Arcanum\Hourglass\Clock;
use Arcanum\Hourglass\SystemClock;
use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Searcher;
use Arcanum\Parchment\Writer;
use Psr\SimpleCache\CacheInterface;

/**
 * File-based cache driver. One file per key.
 *
 * Value and expiry are serialized together. Expired entries are lazily
 * deleted on access. Uses Parchment for all file I/O.
 * This is the default driver — zero config, works on any PHP installation.
 */
final class FileDriver implements CacheInterface
{
    private readonly Reader $reader;
    private readonly Writer $writer;
    private readonly FileSystem $fileSystem;
    private readonly Clock $clock;

    public function __construct(
        private readonly string $directory,
        Reader $reader = new Reader(),
        Writer $writer = new Writer(),
        FileSystem $fileSystem = new FileSystem(),
        Clock $clock = new SystemClock(),
    ) {
        $this->reader = $reader;
        $this->writer = $writer;
        $this->fileSystem = $fileSystem;
        $this->clock = $clock;

        if (!$this->fileSystem->isDirectory($this->directory)) {
            $this->fileSystem->mkdir($this->directory);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        KeyValidator::validate($key);

        $path = $this->path($key);

        if (!$this->fileSystem->isFile($path)) {
            return $default;
        }

        $data = @unserialize($this->reader->read($path));

        if (!is_array($data) || !array_key_exists('value', $data)) {
            $this->fileSystem->delete($path);
            return $default;
        }

        $now = $this->clock->now()->getTimestamp();
        if (isset($data['expiry']) && is_int($data['expiry']) && $data['expiry'] <= $now) {
            $this->fileSystem->delete($path);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        KeyValidator::validate($key);

        $expiry = $this->resolveExpiry($ttl);

        if ($expiry !== null && $expiry <= 0) {
            return $this->delete($key);
        }

        $data = ['value' => $value, 'expiry' => $expiry];
        $this->writer->write($this->path($key), serialize($data));

        return true;
    }

    public function delete(string $key): bool
    {
        KeyValidator::validate($key);

        $path = $this->path($key);

        if ($this->fileSystem->isFile($path)) {
            $this->fileSystem->delete($path);
        }

        return true;
    }

    public function clear(): bool
    {
        if (!$this->fileSystem->isDirectory($this->directory)) {
            return true;
        }

        foreach (Searcher::findAll('*.cache', $this->directory) as $file) {
            $this->fileSystem->delete($file->getRealPath());
        }

        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    private function path(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }

    private function resolveExpiry(\DateInterval|int|null $ttl): int|null
    {
        if ($ttl === null) {
            return null;
        }

        $now = $this->clock->now()->getTimestamp();

        if ($ttl instanceof \DateInterval) {
            return $now + (int) (new \DateTime())->setTimestamp(0)->add($ttl)->getTimestamp();
        }

        if ($ttl <= 0) {
            return 0;
        }

        return $now + $ttl;
    }
}
