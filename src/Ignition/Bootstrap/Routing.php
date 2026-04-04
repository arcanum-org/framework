<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Atlas\ConventionResolver;
use Arcanum\Atlas\HttpRouter;
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
use Arcanum\Glitch\ExceptionRenderer;
use Arcanum\Hyper\CsvResponseRenderer;
use Arcanum\Hyper\FormatRegistry;
use Arcanum\Hyper\HtmlResponseRenderer;
use Arcanum\Hyper\JsonResponseRenderer;
use Arcanum\Hyper\PlainTextResponseRenderer;
use Arcanum\Hyper\ValidationExceptionRenderer;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Ignition\Kernel;
use Arcanum\Ignition\Transport;
use Arcanum\Session\ActiveSession;
use Arcanum\Shodo\Format;
use Arcanum\Shodo\Formatters\CsvFormatter;
use Arcanum\Shodo\Formatters\HtmlFallbackFormatter;
use Arcanum\Shodo\Formatters\HtmlFormatter;
use Arcanum\Shodo\Formatters\JsonFormatter;
use Arcanum\Shodo\Formatters\PlainTextFallbackFormatter;
use Arcanum\Shodo\Formatters\PlainTextFormatter;
use Arcanum\Shodo\HelperDiscovery;
use Arcanum\Shodo\HelperRegistry;
use Arcanum\Shodo\HelperResolver;
use Arcanum\Shodo\Helpers\ArrHelper;
use Arcanum\Shodo\Helpers\FormatHelper;
use Arcanum\Shodo\Helpers\HtmlHelper;
use Arcanum\Shodo\Helpers\RouteHelper;
use Arcanum\Shodo\Helpers\StrHelper;
use Arcanum\Shodo\TemplateCache;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Shodo\TemplateResolver;
use Arcanum\Validation\ValidationGuard;
use Arcanum\Vault\CacheManager;
use Arcanum\Vault\PrefixedCache;

/**
 * Registers Atlas routing and Shodo format registry in the container.
 *
 * Reads from config/app.php:
 *   - namespace (required) — the app's root namespace
 *   - pages_namespace (required) — the Pages namespace
 *
 * Reads from config/routes.php:
 *   - custom (optional) — explicit path → class route mappings
 *   - pages (optional) — path => format overrides for auto-discovered pages
 *
 * Reads from config/formats.php:
 *   - default (optional) — fallback format for convention routes (default: 'json')
 *   - formats (required) — extension => [content_type, renderer] mappings
 */
class Routing implements Bootstrapper
{
    public function bootstrap(Application $container): void
    {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);

        $this->registerFormats($container, $config);
        $this->registerRouter($container, $config);
        $this->registerHelpers($container, $config);
        $this->registerHydrator($container);
        $this->registerBusMiddleware($container);
        $this->registerValidationRenderer($container);

        // PageHandler is the framework-provided handler for all pages.
        $container->service(PageHandler::class);
    }

    private function registerFormats(Application $container, Configuration $config): void
    {
        $container->factory(FormatRegistry::class, function () use ($container, $config) {
            $registry = new FormatRegistry($container);

            /** @var array<string, array{content_type: string, renderer: string}>|null $formats */
            $formats = $config->get('formats.formats');

            foreach ($formats ?? [] as $extension => $definition) {
                $registry->register(new Format(
                    extension: $extension,
                    contentType: $definition['content_type'],
                    rendererClass: $definition['renderer'],
                ));
            }

            return $registry;
        });

        // Register formatters
        $container->service(JsonFormatter::class);
        $container->service(CsvFormatter::class);

        // Shared template infrastructure
        $container->service(TemplateCompiler::class);

        $container->factory(TemplateCache::class, function () use ($container, $config) {
            /** @var mixed $cacheEnabled */
            $cacheEnabled = $config->get('cache.templates.enabled');
            $cacheEnabled = $cacheEnabled === null || $cacheEnabled === true;

            if (!$cacheEnabled) {
                return new TemplateCache('');
            }

            /** @var Kernel $kernel */
            $kernel = $container->get(Kernel::class);

            return new TemplateCache(
                $kernel->filesDirectory()
                    . DIRECTORY_SEPARATOR . 'cache'
                    . DIRECTORY_SEPARATOR . 'templates',
            );
        });

        // Template-based formatters — each gets its own TemplateResolver
        // configured for its file extension.
        $container->factory(HtmlFormatter::class, function () use ($container, $config) {
            /** @var TemplateCompiler $compiler */
            $compiler = $container->get(TemplateCompiler::class);
            /** @var TemplateCache $cache */
            $cache = $container->get(TemplateCache::class);

            $helpers = $container->has(HelperResolver::class)
                ? $container->get(HelperResolver::class)
                : null;

            /** @var mixed $debug */
            $debug = $config->get('app.debug');

            return new HtmlFormatter(
                resolver: $this->createTemplateResolver($container, $config, 'html'),
                compiler: $compiler,
                cache: $cache,
                fallback: new HtmlFallbackFormatter(),
                helpers: $helpers instanceof HelperResolver ? $helpers : null,
                debug: $debug === true || $debug === 'true',
            );
        });

        $container->factory(PlainTextFormatter::class, function () use ($container, $config) {
            /** @var TemplateCompiler $compiler */
            $compiler = $container->get(TemplateCompiler::class);
            /** @var TemplateCache $cache */
            $cache = $container->get(TemplateCache::class);

            $helpers = $container->has(HelperResolver::class)
                ? $container->get(HelperResolver::class)
                : null;

            /** @var mixed $debug */
            $debug = $config->get('app.debug');

            return new PlainTextFormatter(
                resolver: $this->createTemplateResolver($container, $config, 'txt'),
                compiler: $compiler,
                cache: $cache,
                fallback: new PlainTextFallbackFormatter(),
                helpers: $helpers instanceof HelperResolver ? $helpers : null,
                debug: $debug === true || $debug === 'true',
            );
        });

        // HTTP response renderers — compose formatters
        $container->service(JsonResponseRenderer::class);
        $container->service(CsvResponseRenderer::class);

        $container->factory(HtmlResponseRenderer::class, function () use ($container) {
            /** @var HtmlFormatter $formatter */
            $formatter = $container->get(HtmlFormatter::class);
            return new HtmlResponseRenderer($formatter);
        });

        $container->factory(PlainTextResponseRenderer::class, function () use ($container) {
            /** @var PlainTextFormatter $formatter */
            $formatter = $container->get(PlainTextFormatter::class);
            return new PlainTextResponseRenderer($formatter);
        });
    }

    private function createTemplateResolver(
        Application $container,
        Configuration $config,
        string $extension,
    ): TemplateResolver {
        /** @var Kernel $kernel */
        $kernel = $container->get(Kernel::class);

        /** @var string $rootNamespace */
        $rootNamespace = $config->get('app.namespace');

        // The root namespace may be nested (e.g. "App\Domain"), but PSR-4
        // maps the top-level segment to the directory. Extract it.
        $topLevelNamespace = strstr($rootNamespace, '\\', true) ?: $rootNamespace;

        return new TemplateResolver(
            rootDirectory: $kernel->rootDirectory(),
            rootNamespace: $topLevelNamespace,
            extension: $extension,
        );
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

        $container->factory(Router::class, function () use ($resolver, $routeMap, $pages, $defaultFormat) {
            return new HttpRouter($resolver, $routeMap, $pages, $defaultFormat);
        });

        $container->instance(UrlResolver::class, new UrlResolver(
            rootNamespace: $namespace,
            routeMap: $routeMap,
            pagesNamespace: $pagesNamespace,
        ));
    }

    private function registerHelpers(Application $container, Configuration $config): void
    {
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

        // Domain-scoped helper discovery
        /** @var Kernel $kernel */
        $kernel = $container->get(Kernel::class);

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

    private function registerHydrator(Application $container): void
    {
        $container->service(Hydrator::class);
    }

    private function registerValidationRenderer(Application $container): void
    {
        if (!$container->has(ExceptionRenderer::class)) {
            return;
        }

        $container->decorator(
            ExceptionRenderer::class,
            function (object $inner) use ($container): object {
                /** @var JsonResponseRenderer $jsonRenderer */
                $jsonRenderer = $container->get(JsonResponseRenderer::class);
                return new ValidationExceptionRenderer($inner, $jsonRenderer);
            },
        );
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
