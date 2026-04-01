<?php

declare(strict_types=1);

namespace Arcanum\Vault;

use Psr\SimpleCache\CacheInterface;

/**
 * Cache driver that stores nothing.
 *
 * Useful for disabling caching entirely without conditionals
 * (e.g., development mode).
 */
final class NullDriver implements CacheInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        KeyValidator::validate($key);
        return $default;
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        KeyValidator::validate($key);
        return true;
    }

    public function delete(string $key): bool
    {
        KeyValidator::validate($key);
        return true;
    }

    public function clear(): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        KeyValidator::validate($key);
        return false;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        KeyValidator::validateMultiple($keys);
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $default;
        }
        return $result;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            KeyValidator::validate($key);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        KeyValidator::validateMultiple($keys);
        return true;
    }
}
