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

        if ($cache->exists()) {
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
}
