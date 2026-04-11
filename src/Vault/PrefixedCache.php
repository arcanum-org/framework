<?php

declare(strict_types=1);

namespace Arcanum\Vault;

use Psr\SimpleCache\CacheInterface;

/**
 * Decorator that prepends a namespace prefix to all cache keys.
 *
 * Useful for isolating framework caches from app caches on the same driver.
 *
 * Note: `clear()` delegates to the inner driver's `clear()` — for shared
 * drivers (Redis, APCu), this clears ALL keys, not just prefixed ones.
 * For ArrayDriver and FileDriver, consider using separate instances instead.
 */
final class PrefixedCache implements CacheInterface
{
    public function __construct(
        private readonly CacheInterface $inner,
        private readonly string $prefix,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->inner->get($this->prefix . $key, $default);
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        return $this->inner->set($this->prefix . $key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->inner->delete($this->prefix . $key);
    }

    public function clear(): bool
    {
        return $this->inner->clear();
    }

    public function has(string $key): bool
    {
        return $this->inner->has($this->prefix . $key);
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $prefixed = [];
        $keyMap = [];
        foreach ($keys as $key) {
            $pk = $this->prefix . $key;
            $prefixed[] = $pk;
            $keyMap[$pk] = $key;
        }

        $results = $this->inner->getMultiple($prefixed, $default);
        $output = [];
        foreach ($results as $pk => $value) {
            $output[$keyMap[$pk]] = $value;
        }
        return $output;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        $prefixed = [];
        foreach ($values as $key => $value) {
            $prefixed[$this->prefix . $key] = $value;
        }
        return $this->inner->setMultiple($prefixed, $ttl);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $prefixed = [];
        foreach ($keys as $key) {
            $prefixed[] = $this->prefix . $key;
        }
        return $this->inner->deleteMultiple($prefixed);
    }
}
