<?php

declare(strict_types=1);

namespace Arcanum\Vault;

use Psr\SimpleCache\CacheInterface;

/**
 * Factory/registry for named cache stores.
 *
 * Reads driver configuration and lazily instantiates stores on first access.
 * The app interacts with named stores: `$cache->store('redis')` or the
 * default via `$cache->store()`.
 */
final class CacheManager
{
    /** @var array<string, CacheInterface> */
    private array $resolved = [];

    /**
     * @param string $defaultStore The name of the default store.
     * @param array<string, array<string, mixed>> $stores Store configs keyed by name.
     * @param array<string, string> $frameworkStores Maps framework cache names to store names.
     * @param string $filesDirectory Base path for relative file driver paths.
     */
    public function __construct(
        private readonly string $defaultStore,
        private readonly array $stores,
        private readonly array $frameworkStores = [],
        private readonly string $filesDirectory = '',
    ) {
    }

    /**
     * Get a cache store by name, or the default store.
     */
    public function store(string $name = ''): CacheInterface
    {
        if ($name === '') {
            $name = $this->defaultStore;
        }

        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        if (!isset($this->stores[$name])) {
            throw new \RuntimeException(sprintf('Cache store "%s" is not configured.', $name));
        }

        $this->resolved[$name] = $this->buildDriver($this->stores[$name]);

        return $this->resolved[$name];
    }

    /**
     * Get the store assigned to a framework cache purpose.
     *
     * Falls back to the default store if the mapping doesn't exist.
     */
    public function frameworkStore(string $purpose): CacheInterface
    {
        $storeName = $this->frameworkStores[$purpose] ?? $this->defaultStore;

        return $this->store($storeName);
    }

    /**
     * Get all configured store names.
     *
     * @return list<string>
     */
    public function storeNames(): array
    {
        return array_keys($this->stores);
    }

    /**
     * Clear all resolved stores.
     */
    public function clearAll(): void
    {
        foreach ($this->resolved as $store) {
            $store->clear();
        }

        // Also clear stores not yet resolved by building and clearing them.
        foreach ($this->stores as $name => $config) {
            if (!isset($this->resolved[$name])) {
                $this->store($name)->clear();
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildDriver(array $config): CacheInterface
    {
        $driver = is_string($config['driver'] ?? '') ? ($config['driver'] ?? '') : '';

        return match ($driver) {
            'file' => new FileDriver($this->resolveFilePath($config)),
            'array' => new ArrayDriver(),
            'null' => new NullDriver(),
            'apcu' => new ApcuDriver(),
            'redis' => $this->buildRedisDriver($config),
            default => throw new \RuntimeException(
                sprintf('Unknown cache driver "%s".', $driver),
            ),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveFilePath(array $config): string
    {
        $path = is_string($config['path'] ?? null) ? $config['path'] : 'cache';

        // If the path is relative, prepend the files directory.
        if (!str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $this->filesDirectory . DIRECTORY_SEPARATOR . $path;
        }

        return $path;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildRedisDriver(array $config): RedisDriver
    {
        $redis = new \Redis();
        $host = is_string($config['host'] ?? null) ? $config['host'] : '127.0.0.1';
        $port = is_int($config['port'] ?? null) ? $config['port'] : 6379;
        $redis->connect($host, $port);

        return new RedisDriver($redis);
    }
}
