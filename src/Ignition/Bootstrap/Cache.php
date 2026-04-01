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

        $rawFramework = $config->get('cache.framework');
        /** @var array<string, string> $frameworkStores */
        $frameworkStores = is_array($rawFramework) ? $rawFramework : [];

        $manager = new CacheManager(
            defaultStore: $default,
            stores: $stores,
            frameworkStores: $frameworkStores,
            filesDirectory: $kernel->filesDirectory(),
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
