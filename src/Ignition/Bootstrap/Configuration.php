<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Cabinet\Application;
use Arcanum\Gather\Configuration as GatherConfiguration;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Ignition\ConfigurationCache;
use Arcanum\Parchment\Searcher;

/**
 * The configuration bootstrapper.
 *
 * Reads every PHP file under the kernel's config directory and wires
 * the merged result into the container as a single Configuration. If
 * a pre-built configuration cache exists at files/cache/config.php
 * AND the framework cache bypass switch is off, the cached snapshot
 * is loaded instead of re-parsing the source files.
 *
 * The bypass check has to be done by reading config/cache.php directly
 * here, because the CacheManager (which exposes the canonical bypass
 * flag) is built by Bootstrap\Cache, which runs AFTER this bootstrapper.
 * Chicken-and-egg: configuration is needed to know if configuration
 * caching should be honoured.
 */
class Configuration implements Bootstrapper
{
    /**
     * Bootstrap the application.
     */
    public function bootstrap(Application $container): void
    {
        /** @var \Arcanum\Ignition\Kernel $kernel */
        $kernel = $container->get(\Arcanum\Ignition\Kernel::class);

        $cache = new ConfigurationCache(
            $kernel->filesDirectory() . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'config.php'
        );

        $container->instance(ConfigurationCache::class, $cache);

        if ($cache->exists() && $this->frameworkCacheEnabled($kernel->configDirectory())) {
            $config = new GatherConfiguration($cache->load());
            $container->instance(GatherConfiguration::class, $config);
            return;
        }

        $config = new GatherConfiguration();
        $container->instance(GatherConfiguration::class, $config);

        $configDirectory = $kernel->configDirectory();
        $files = Searcher::findAll('*.php', $configDirectory);

        foreach ($files as $file) {
            $config->set(
                $file->getFilenameWithoutExtension(),
                require $file->getRealPath(),
            );
        }
    }

    /**
     * Read cache.framework.enabled directly from config/cache.php.
     *
     * Can't use the CacheManager here because it's built later in the
     * bootstrap chain. Defaults to true (cache enabled) if the file is
     * missing or the key isn't set.
     */
    private function frameworkCacheEnabled(string $configDirectory): bool
    {
        $cacheConfigFile = $configDirectory . DIRECTORY_SEPARATOR . 'cache.php';

        if (!is_file($cacheConfigFile)) {
            return true;
        }

        /** @var mixed $cacheConfig */
        $cacheConfig = require $cacheConfigFile;

        if (!is_array($cacheConfig)) {
            return true;
        }

        $framework = $cacheConfig['framework'] ?? null;
        if (!is_array($framework)) {
            return true;
        }

        return ($framework['enabled'] ?? true) === true;
    }
}
