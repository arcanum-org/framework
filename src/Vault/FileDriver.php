<?php

declare(strict_types=1);

namespace Arcanum\Vault;

use Psr\SimpleCache\CacheInterface;

/**
 * File-based cache driver. One file per key.
 *
 * Value and expiry are serialized together. Expired entries are lazily
 * deleted on access. This is the default driver — zero config, works
 * on any PHP installation.
 */
final class FileDriver implements CacheInterface
{
    public function __construct(
        private readonly string $directory,
    ) {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        KeyValidator::validate($key);

        $path = $this->path($key);

        if (!file_exists($path)) {
            return $default;
        }

        $data = @unserialize((string) file_get_contents($path));

        if (!is_array($data) || !array_key_exists('value', $data)) {
            @unlink($path);
            return $default;
        }

        if (isset($data['expiry']) && is_int($data['expiry']) && $data['expiry'] <= time()) {
            @unlink($path);
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
        $path = $this->path($key);

        // Atomic write via temp file + rename.
        $tmp = $path . '.' . uniqid('', true) . '.tmp';
        file_put_contents($tmp, serialize($data), LOCK_EX);
        rename($tmp, $path);

        return true;
    }

    public function delete(string $key): bool
    {
        KeyValidator::validate($key);

        $path = $this->path($key);

        if (file_exists($path)) {
            @unlink($path);
        }

        return true;
    }

    public function clear(): bool
    {
        if (!is_dir($this->directory)) {
            return true;
        }

        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.cache');

        if ($files !== false) {
            foreach ($files as $file) {
                @unlink($file);
            }
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

        if ($ttl instanceof \DateInterval) {
            return time() + (int) (new \DateTime())->setTimestamp(0)->add($ttl)->getTimestamp();
        }

        if ($ttl <= 0) {
            return 0;
        }

        return time() + $ttl;
    }
}
