<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Atlas\ConventionResolver;
use Arcanum\Atlas\HttpRouter;
use Arcanum\Atlas\LocationResolver;
use Arcanum\Atlas\PageDiscovery;
use Arcanum\Atlas\PageResolver;
use Arcanum\Atlas\RouteMap;
use Arcanum\Atlas\Router;
use Arcanum\Atlas\UrlResolver;
use Arcanum\Auth\ActiveIdentity;
use Arcanum\Auth\AuthorizationGuard;
use Arcanum\Cabinet\Application;
use Arcanum\Codex\Hydrator;
use Arcanum\Flow\Conveyor\Bus;
use Arcanum\Flow\Conveyor\MiddlewareBus;
use Arcanum\Flow\Conveyor\PageHandler;
use Arcanum\Forge\DomainContext;
use Arcanum\Forge\DomainContextMiddleware;
use Arcanum\Gather\Configuration;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Ignition\Kernel;
use Arcanum\Ignition\Transport;
use Arcanum\Validation\ValidationGuard;
use Arcanum\Vault\CacheManager;
use Arcanum\Vault\PrefixedCache;

/**
 * Registers Atlas routing, URL resolution, hydrator, and Conveyor bus middleware.
 *
 * Reads from config/app.php (namespace, pages), config/routes.php (custom routes,
 * page overrides), and config/formats.php (default format).
 */
class Routing implements Bootstrapper
{
    public function bootstrap(Application $container): void
    {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);

        $this->registerRouter($container, $config);
        $this->registerHydrator($container);
        $this->registerBusMiddleware($container);

        $container->service(PageHandler::class);
    }

    private function registerRouter(Application $container, Configuration $config): void
    {
        /** @var mixed $namespace */
        $namespace = $config->get('app.namespace');
        if (!is_string($namespace) || $namespace === '') {
            throw new \RuntimeException(
                'Missing required config "app.namespace". '
                . 'Set it in config/app.php to your app\'s root namespace (e.g., "App").'
            );
        }

        /** @var mixed $pagesNamespace */
        $pagesNamespace = $config->get('app.pages_namespace');
        if (!is_string($pagesNamespace) || $pagesNamespace === '') {
            throw new \RuntimeException(
                'Missing required config "app.pages_namespace". '
                . 'Set it in config/app.php to your Pages namespace (e.g., "App\\Pages").'
            );
        }

        /** @var mixed $defaultFormatRaw */
        $defaultFormatRaw = $config->get('formats.default');
        $defaultFormat = is_string($defaultFormatRaw) ? $defaultFormatRaw : 'json';

        $resolver = new ConventionResolver(rootNamespace: $namespace);
        $container->instance(ConventionResolver::class, $resolver);

        $routeMap = new RouteMap();

        /** @var array<string, array{class: string, methods?: list<string>, format?: string}>|null $customRoutes */
        $customRoutes = $config->get('routes.custom');
        foreach ($customRoutes ?? [] as $path => $definition) {
            $routeMap->register(
                path: $path,
                dtoClass: $definition['class'],
                methods: $definition['methods'] ?? ['GET'],
                format: $definition['format'] ?? $defaultFormat,
            );
        }

        // Auto-discover pages from filesystem and register as custom routes.
        /** @var mixed $pagesDirectory */
        $pagesDirectory = $config->get('app.pages_directory');
        if (!is_string($pagesDirectory) || $pagesDirectory === '') {
            $pagesDirectory = 'app' . DIRECTORY_SEPARATOR . 'Pages';
        }

        /** @var Kernel $kernel */
        $kernel = $container->get(Kernel::class);

        // Support both relative (to root) and absolute paths.
        $absolutePagesDir = str_starts_with($pagesDirectory, DIRECTORY_SEPARATOR)
            ? $pagesDirectory
            : $kernel->rootDirectory() . DIRECTORY_SEPARATOR . $pagesDirectory;

        if (!is_dir($absolutePagesDir) && $container->has(\Psr\Log\LoggerInterface::class)) {
            /** @var \Psr\Log\LoggerInterface $logger */
            $logger = $container->get(\Psr\Log\LoggerInterface::class);
            $logger->debug("Pages directory does not exist: {$absolutePagesDir}. No pages will be discovered.");
        }

        // Page cache configuration.
        /** @var mixed $cacheEnabled */
        $cacheEnabled = $config->get('cache.pages.enabled');
        $cacheEnabled = $cacheEnabled === null || $cacheEnabled === true;

        /** @var mixed $cacheMaxAge */
        $cacheMaxAge = $config->get('cache.pages.max_age');
        $cacheMaxAge = is_int($cacheMaxAge) ? $cacheMaxAge : 0;

        $pageCache = null;
        if ($cacheEnabled && $container->has(CacheManager::class)) {
            /** @var CacheManager $cacheManager */
            $cacheManager = $container->get(CacheManager::class);
            $pageCache = new PrefixedCache($cacheManager->frameworkStore('pages'), 'fw.pages.');
        }

        $pageDiscovery = new PageDiscovery(
            namespace: $pagesNamespace,
            directory: $absolutePagesDir,
            defaultFormat: 'html',
            cache: $pageCache,
            cacheTtl: $cacheMaxAge,
        );

        /** @var array<string, string>|null $pageOverrides */
        $pageOverrides = $config->get('routes.pages');
        $pageDiscovery->register($routeMap, $pageOverrides ?? []);

        $container->instance(PageDiscovery::class, $pageDiscovery);
        $container->instance(RouteMap::class, $routeMap);

        // PageResolver is still available for backwards compatibility.
        $pages = new PageResolver(namespace: $pagesNamespace);
        $container->instance(PageResolver::class, $pages);

        $container->factory(Router::class, function () use ($container, $resolver, $routeMap, $pages, $defaultFormat) {
            $logger = $container->has(\Psr\Log\LoggerInterface::class)
                ? $container->get(\Psr\Log\LoggerInterface::class)
                : null;

            /** @var ?\Psr\Log\LoggerInterface $logger */
            return new HttpRouter($resolver, $routeMap, $pages, $defaultFormat, $logger);
        });

        $container->instance(UrlResolver::class, new UrlResolver(
            rootNamespace: $namespace,
            routeMap: $routeMap,
            pagesNamespace: $pagesNamespace,
        ));

        /** @var UrlResolver $urlResolver */
        $urlResolver = $container->get(UrlResolver::class);
        /** @var mixed $baseUrl */
        $baseUrl = $config->get('app.url');
        $container->instance(LocationResolver::class, new LocationResolver(
            urlResolver: $urlResolver,
            baseUrl: is_string($baseUrl) ? $baseUrl : '',
        ));
    }

    private function registerHydrator(Application $container): void
    {
        $container->service(Hydrator::class);
    }

    private function registerBusMiddleware(Application $container): void
    {
        if (!$container->has(Bus::class)) {
            return;
        }

        /** @var Bus $bus */
        $bus = $container->get(Bus::class);

        if ($bus instanceof MiddlewareBus) {
            /** @var ActiveIdentity $activeIdentity */
            $activeIdentity = $container->get(ActiveIdentity::class);
            /** @var Transport $transport */
            $transport = $container->get(Transport::class);
            $bus->before(new AuthorizationGuard($activeIdentity, $transport, $container));
            $bus->before(new ValidationGuard());

            if ($container->has(DomainContext::class)) {
                /** @var DomainContext $context */
                $context = $container->get(DomainContext::class);
                /** @var Configuration $config */
                $config = $container->get(Configuration::class);
                $namespace = $config->asString('app.namespace', 'App\\Domain');
                $bus->before(new DomainContextMiddleware($context, $namespace));
            }
        }
    }
}
