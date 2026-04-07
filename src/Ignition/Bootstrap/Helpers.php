<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Atlas\UrlResolver;
use Arcanum\Cabinet\Application;
use Arcanum\Gather\Configuration;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Ignition\Kernel;
use Arcanum\Session\ActiveSession;
use Arcanum\Shodo\HelperDiscovery;
use Arcanum\Shodo\HelperRegistry;
use Arcanum\Shodo\HelperResolver;
use Arcanum\Shodo\Helpers\ArrHelper;
use Arcanum\Shodo\Helpers\FormatHelper;
use Arcanum\Shodo\Helpers\HtmlHelper;
use Arcanum\Shodo\Helpers\RouteHelper;
use Arcanum\Shodo\Helpers\StrHelper;
use Arcanum\Vault\CacheManager;
use Arcanum\Vault\PrefixedCache;

/**
 * Registers template helper infrastructure: global helpers, domain-scoped
 * discovery, and the HelperResolver that formatters use at render time.
 *
 * Must run after Bootstrap\Routing (needs UrlResolver) and
 * Bootstrap\Sessions (needs ActiveSession for HtmlHelper).
 */
class Helpers implements Bootstrapper
{
    public function bootstrap(Application $container): void
    {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);

        $global = new HelperRegistry();
        $global->register('Format', new FormatHelper());
        $global->register('Str', new StrHelper());
        $global->register('Arr', new ArrHelper());

        if ($container->has(ActiveSession::class)) {
            /** @var ActiveSession $session */
            $session = $container->get(ActiveSession::class);
            $global->register('Html', new HtmlHelper($session));
        }

        if ($container->has(UrlResolver::class)) {
            /** @var UrlResolver $urlResolver */
            $urlResolver = $container->get(UrlResolver::class);
            /** @var mixed $baseUrl */
            $baseUrl = $config->get('app.url');
            $global->register('Route', new RouteHelper(
                $urlResolver,
                is_string($baseUrl) ? $baseUrl : '',
            ));
        }

        // App-provided global helpers
        /** @var Kernel $kernel */
        $kernel = $container->get(Kernel::class);

        $appHelpersFile = $kernel->rootDirectory()
            . DIRECTORY_SEPARATOR . 'app'
            . DIRECTORY_SEPARATOR . 'Helpers'
            . DIRECTORY_SEPARATOR . 'Helpers.php';

        if (file_exists($appHelpersFile)) {
            /** @var mixed $appHelpers */
            $appHelpers = require $appHelpersFile;
            if (is_array($appHelpers)) {
                /** @var array<string, class-string> $appHelpers */
                foreach ($appHelpers as $alias => $className) {
                    /** @var object $helper */
                    $helper = $container->get($className);
                    $global->register($alias, $helper);
                }
            }
        }

        // Domain-scoped helper discovery

        /** @var string $namespace */
        $namespace = $config->get('app.namespace');

        $rootDirectory = $kernel->rootDirectory()
            . DIRECTORY_SEPARATOR . 'app'
            . DIRECTORY_SEPARATOR . 'Domain';

        $discoveryCache = null;
        /** @var mixed $cacheEnabled */
        $cacheEnabled = $config->get('cache.helpers.enabled');
        if (($cacheEnabled === null || $cacheEnabled === true) && $container->has(CacheManager::class)) {
            /** @var CacheManager $cacheManager */
            $cacheManager = $container->get(CacheManager::class);
            $discoveryCache = new PrefixedCache($cacheManager->frameworkStore('helpers'), 'fw.helpers.');
        }

        $discovery = new HelperDiscovery(
            rootNamespace: $namespace,
            rootDirectory: $rootDirectory,
            cache: $discoveryCache,
        );

        $resolver = new HelperResolver($global, $discovery, $container);
        $container->instance(HelperResolver::class, $resolver);
        $container->instance(HelperDiscovery::class, $discovery);
    }
}
