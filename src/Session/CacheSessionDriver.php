<?php

declare(strict_types=1);

namespace Arcanum\Session;

use Psr\SimpleCache\CacheInterface;

/**
 * Session driver backed by any PSR-16 cache.
 *
 * Delegates to a Vault CacheInterface, enabling Redis, APCu, or any
 * other cache driver as session storage with zero custom code.
 */
final class CacheSessionDriver implements SessionDriver
{
    private const KEY_PREFIX = 'session.';

    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function read(string $id): array
    {
        $data = $this->cache->get(self::KEY_PREFIX . $id);

        return is_array($data) ? $data : [];
    }

    public function write(string $id, array $data, int $ttl): void
    {
        $this->cache->set(self::KEY_PREFIX . $id, $data, $ttl);
    }

    public function destroy(string $id): void
    {
        $this->cache->delete(self::KEY_PREFIX . $id);
    }
}
