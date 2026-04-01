<?php

declare(strict_types=1);

namespace Arcanum\Vault;

use Psr\SimpleCache\CacheInterface;

/**
 * APCu-backed cache driver. Extremely fast, single-server only.
 *
 * Requires the APCu extension. Throws RuntimeException if unavailable.
 */
final class ApcuDriver implements CacheInterface
{
    public function __construct()
    {
        if (!extension_loaded('apcu') || !ini_get('apc.enable_cli')) {
            throw new \RuntimeException(
                'APCu extension is not available or apc.enable_cli is disabled.',
            );
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        KeyValidator::validate($key);

        $value = apcu_fetch($key, $success);

        return $success ? $value : $default;
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        KeyValidator::validate($key);

        $seconds = $this->resolveTtl($ttl);

        if ($seconds !== null && $seconds <= 0) {
            return $this->delete($key);
        }

        return apcu_store($key, $value, $seconds ?? 0);
    }

    public function delete(string $key): bool
    {
        KeyValidator::validate($key);

        apcu_delete($key);

        return true;
    }

    public function clear(): bool
    {
        return apcu_clear_cache();
    }

    public function has(string $key): bool
    {
        KeyValidator::validate($key);

        return apcu_exists($key);
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
