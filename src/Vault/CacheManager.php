<?php

declare(strict_types=1);

namespace Arcanum\Vault;

use Arcanum\Gather\Configuration;
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
     * Get the default store name.
     */
    public function defaultStoreName(): string
    {
        return $this->defaultStore;
    }

    /**
     * Get the driver name for a configured store.
     */
    public function driverName(string $storeName): string
    {
        $config = new Configuration($this->stores[$storeName] ?? []);
        return $config->asString('driver', 'unknown');
    }

    /**
     * Get the framework store mapping.
     *
     * @return array<string, string>
     */
    public function frameworkStoreMapping(): array
    {
        return $this->frameworkStores;
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
        $cfg = new Configuration($config);
        $driver = $cfg->asString('driver');

        return match ($driver) {
            'file' => new FileDriver($this->resolveFilePath($cfg)),
            'array' => new ArrayDriver(),
            'null' => new NullDriver(),
            'apcu' => new ApcuDriver(),
            'redis' => $this->buildRedisDriver($cfg),
            default => throw new \RuntimeException(
                sprintf('Unknown cache driver "%s".', $driver),
            ),
        };
    }

    private function resolveFilePath(Configuration $cfg): string
    {
        $path = $cfg->asString('path', 'cache');

        // If the path is relative, prepend the files directory.
        if (!str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $this->filesDirectory . DIRECTORY_SEPARATOR . $path;
        }

        return $path;
    }

    private function buildRedisDriver(Configuration $cfg): RedisDriver
    {
        $redis = new \Redis();
        $redis->connect(
            $cfg->asString('host', '127.0.0.1'),
            $cfg->asInt('port', 6379),
        );

        return new RedisDriver($redis);
    }
}
