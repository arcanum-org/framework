<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Cabinet\Application;
use Arcanum\Gather\Configuration;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Ignition\Kernel;
use Arcanum\Vault\CacheManager;
use Psr\SimpleCache\CacheInterface;

/**
 * Registers the CacheManager and default CacheInterface in the container.
 *
 * Reads `config/cache.php` for store configuration. Registers:
 * - `CacheManager` — the factory for named stores
 * - `CacheInterface` — the default store (PSR-16 typehint)
 *
 * Must run after `Bootstrap\Configuration`.
 */
class Cache implements Bootstrapper
{
    public function bootstrap(Application $container): void
    {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);

        /** @var Kernel $kernel */
        $kernel = $container->get(Kernel::class);

        $default = $this->configString($config, 'cache.default', 'file');

        /** @var array<string, array<string, mixed>> $stores */
        $stores = $config->get('cache.stores') ?? [];

        // Ensure a default file store exists if nothing is configured.
        if ($stores === []) {
            $stores = [
                'file' => [
                    'driver' => 'file',
                    'path' => 'cache' . DIRECTORY_SEPARATOR . 'app',
                ],
            ];
        }

        // The framework cache section has two keys:
        //   'enabled' (bool)        — master bypass switch for framework-internal caches
        //   'stores'  (array)       — purpose => store-name mapping
        // Backwards compatible: if 'cache.framework' is itself a flat
        // [purpose => store] map (the older shape), treat it as stores.
        $rawFramework = $config->get('cache.framework');

        $frameworkCacheEnabled = true;
        /** @var array<string, string> $frameworkStores */
        $frameworkStores = [];

        if (is_array($rawFramework)) {
            if (array_key_exists('enabled', $rawFramework) || array_key_exists('stores', $rawFramework)) {
                $frameworkCacheEnabled = ($rawFramework['enabled'] ?? true) === true;
                /** @var array<string, string> $stores2 */
                $stores2 = is_array($rawFramework['stores'] ?? null) ? $rawFramework['stores'] : [];
                $frameworkStores = $stores2;
            } else {
                /** @var array<string, string> $rawFramework */
                $frameworkStores = $rawFramework;
            }
        }

        $manager = new CacheManager(
            defaultStore: $default,
            stores: $stores,
            frameworkStores: $frameworkStores,
            filesDirectory: $kernel->filesDirectory(),
            frameworkCacheEnabled: $frameworkCacheEnabled,
        );

        $container->instance(CacheManager::class, $manager);

        $container->factory(
            CacheInterface::class,
            fn() => $manager->store(),
        );
    }

    private function configString(Configuration $config, string $key, string $default): string
    {
        $value = $config->get($key);
        return is_string($value) ? $value : $default;
    }
}
