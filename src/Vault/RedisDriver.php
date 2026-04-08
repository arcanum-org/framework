<?php

declare(strict_types=1);

namespace Arcanum\Vault;

use Psr\SimpleCache\CacheInterface;

/**
 * Redis-backed cache driver via the phpredis extension.
 *
 * Constructor takes a `\Redis` instance — the app manages the connection.
 * TTL is native to Redis via SETEX.
 */
final class RedisDriver implements CacheInterface
{
    public function __construct(
        private readonly \Redis $redis,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        KeyValidator::validate($key);

        $value = $this->redis->get($key);

        if (!is_string($value)) {
            return $default;
        }

        return unserialize($value);
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        KeyValidator::validate($key);

        $seconds = $this->resolveTtl($ttl);

        if ($seconds !== null && $seconds <= 0) {
            return $this->delete($key);
        }

        $serialized = serialize($value);

        if ($seconds !== null) {
            return $this->redis->setex($key, $seconds, $serialized);
        }

        return $this->redis->set($key, $serialized);
    }

    public function delete(string $key): bool
    {
        KeyValidator::validate($key);

        $this->redis->del($key);

        return true;
    }

    public function clear(): bool
    {
        return $this->redis->flushDB();
    }

    public function has(string $key): bool
    {
        KeyValidator::validate($key);

        return (bool) $this->redis->exists($key);
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

    /**
     * Normalize a PSR-16 TTL into a positive integer count of seconds.
     *
     * The DateInterval branch converts the interval into total seconds via an
     * epoch-anchored DateTime — it never reads wall-clock "now," so it does
     * not cross a Hourglass\Clock testability boundary. Redis handles expiry
     * natively from the int we hand it.
     */
    private function resolveTtl(\DateInterval|int|null $ttl): int|null
    {
        if ($ttl === null) {
            return null;
        }

        if ($ttl instanceof \DateInterval) {
            return (int) (new \DateTime())->setTimestamp(0)->add($ttl)->getTimestamp();
        }

        return $ttl;
    }
}
