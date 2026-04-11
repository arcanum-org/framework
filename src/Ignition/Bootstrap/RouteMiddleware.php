<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Atlas\MiddlewareDiscovery;
use Arcanum\Atlas\MiddlewareRegistry;
use Arcanum\Cabinet\Application;
use Arcanum\Flow\Conveyor\Bus;
use Arcanum\Gather\Configuration;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Ignition\Kernel;
use Arcanum\Ignition\RouteDispatcher;
use Arcanum\Vault\CacheManager;
use Arcanum\Vault\PrefixedCache;

/**
 * Discovers per-route middleware from attributes and co-located
 * Middleware.php files, then registers the MiddlewareRegistry
 * and RouteDispatcher in the container.
 *
 * Reads from config/cache.php:
 *   - route_middleware.enabled (optional, default true)
 */
class RouteMiddleware implements Bootstrapper
{
    public function bootstrap(Application $container): void
    {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);

        /** @var Kernel $kernel */
        $kernel = $container->get(Kernel::class);

        /** @var mixed $namespace */
        $namespace = $config->get('app.namespace');
        if (!is_string($namespace) || $namespace === '') {
            return;
        }

        $rootDirectory = $kernel->rootDirectory() . DIRECTORY_SEPARATOR . 'app';

        // Cache configuration
        /** @var mixed $cacheEnabled */
        $cacheEnabled = $config->get('cache.route_middleware.enabled');
        $cacheEnabled = $cacheEnabled === null || $cacheEnabled === true;

        $middlewareCache = null;
        if ($cacheEnabled && $container->has(CacheManager::class)) {
            /** @var CacheManager $cacheManager */
            $cacheManager = $container->get(CacheManager::class);
            $middlewareCache = new PrefixedCache(
                $cacheManager->frameworkStore('middleware'),
                'fw.middleware.',
            );
        }

        $discovery = new MiddlewareDiscovery(
            rootNamespace: $namespace,
            rootDirectory: $rootDirectory,
            cache: $middlewareCache,
        );

        $registry = new MiddlewareRegistry();
        foreach ($discovery->discover() as $dtoClass => $middleware) {
            $registry->register($dtoClass, $middleware);
        }

        $container->instance(MiddlewareDiscovery::class, $discovery);
        $container->instance(MiddlewareRegistry::class, $registry);

        // Register RouteDispatcher — depends on MiddlewareRegistry and Bus
        $container->factory(RouteDispatcher::class, function () use ($container, $registry) {
            /** @var Bus $bus */
            $bus = $container->get(Bus::class);
            return new RouteDispatcher($container, $registry, $bus);
        });
    }
}
