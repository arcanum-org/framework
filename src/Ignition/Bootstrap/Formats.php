<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Cabinet\Application;
use Arcanum\Glitch\ExceptionRenderer;
use Arcanum\Hyper\CsvResponseRenderer;
use Arcanum\Hyper\FormatRegistry;
use Arcanum\Hyper\HtmlResponseRenderer;
use Arcanum\Hyper\JsonResponseRenderer;
use Arcanum\Hyper\MarkdownResponseRenderer;
use Arcanum\Hyper\PlainTextResponseRenderer;
use Arcanum\Hyper\ValidationExceptionRenderer;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Ignition\Kernel;
use Arcanum\Gather\Configuration;
use Arcanum\Shodo\Format;
use Arcanum\Shodo\Formatters\CsvFormatter;
use Arcanum\Shodo\Formatters\HtmlFallbackFormatter;
use Arcanum\Shodo\Formatters\HtmlFormatter;
use Arcanum\Shodo\Formatters\JsonFormatter;
use Arcanum\Shodo\Formatters\MarkdownFallbackFormatter;
use Arcanum\Shodo\Formatters\MarkdownFormatter;
use Arcanum\Shodo\Formatters\PlainTextFallbackFormatter;
use Arcanum\Shodo\Formatters\PlainTextFormatter;
use Arcanum\Shodo\HelperResolver;
use Arcanum\Shodo\TemplateCache;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Shodo\TemplateEngine;
use Arcanum\Shodo\TemplateResolver;

/**
 * Registers Shodo formatters, template infrastructure, and HTTP response renderers.
 *
 * Reads from config/formats.php and config/cache.php.
 */
class Formats implements Bootstrapper
{
    public function bootstrap(Application $container): void
    {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);

        $this->registerFormatRegistry($container, $config);
        $this->registerFormatters($container, $config);
        $this->registerResponseRenderers($container);
        $this->registerValidationRenderer($container);
    }

    private function registerFormatRegistry(Application $container, Configuration $config): void
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
    }

    private function registerFormatters(Application $container, Configuration $config): void
    {
        $container->service(JsonFormatter::class);
        $container->service(CsvFormatter::class);

        // Shared template infrastructure
        $container->factory(TemplateCompiler::class, function () use ($container, $config) {
            /** @var Kernel $kernel */
            $kernel = $container->get(Kernel::class);

            /** @var mixed $templatesDir */
            $templatesDir = $config->get('app.templates_directory');
            $templatesPath = is_string($templatesDir) && $templatesDir !== ''
                ? $kernel->rootDirectory() . DIRECTORY_SEPARATOR . $templatesDir
                : '';

            return new TemplateCompiler(templatesDirectory: $templatesPath);
        });

        $container->factory(TemplateCache::class, function () use ($container, $config) {
            // Master switch: cache.framework.enabled disables every framework
            // cache, including templates. Checked first so the per-cache
            // toggle below can't override the master.
            if ($container->has(\Arcanum\Vault\CacheManager::class)) {
                /** @var \Arcanum\Vault\CacheManager $cacheManager */
                $cacheManager = $container->get(\Arcanum\Vault\CacheManager::class);
                if (!$cacheManager->frameworkCacheEnabled()) {
                    return new TemplateCache('');
                }
            }

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

        // Shared template engine — compile, cache, execute.
        $container->factory(TemplateEngine::class, function () use ($container, $config) {
            /** @var TemplateCompiler $compiler */
            $compiler = $container->get(TemplateCompiler::class);
            /** @var TemplateCache $cache */
            $cache = $container->get(TemplateCache::class);

            /** @var mixed $debug */
            $debug = $config->get('app.debug');

            return new TemplateEngine(
                compiler: $compiler,
                cache: $cache,
                debug: $debug === true || $debug === 'true',
            );
        });

        // Template-based formatters — each gets its own TemplateResolver
        // configured for its file extension, but shares the TemplateEngine.
        $container->factory(HtmlFormatter::class, function () use ($container, $config) {
            /** @var TemplateEngine $engine */
            $engine = $container->get(TemplateEngine::class);

            $helpers = $container->has(HelperResolver::class)
                ? $container->get(HelperResolver::class)
                : null;

            return new HtmlFormatter(
                resolver: $this->createTemplateResolver($container, $config, 'html'),
                engine: $engine,
                fallback: new HtmlFallbackFormatter(),
                helpers: $helpers instanceof HelperResolver ? $helpers : null,
            );
        });

        $container->factory(PlainTextFormatter::class, function () use ($container, $config) {
            /** @var TemplateEngine $engine */
            $engine = $container->get(TemplateEngine::class);

            $helpers = $container->has(HelperResolver::class)
                ? $container->get(HelperResolver::class)
                : null;

            return new PlainTextFormatter(
                resolver: $this->createTemplateResolver($container, $config, 'txt'),
                engine: $engine,
                fallback: new PlainTextFallbackFormatter(),
                helpers: $helpers instanceof HelperResolver ? $helpers : null,
            );
        });

        $container->factory(MarkdownFormatter::class, function () use ($container, $config) {
            /** @var TemplateEngine $engine */
            $engine = $container->get(TemplateEngine::class);

            $helpers = $container->has(HelperResolver::class)
                ? $container->get(HelperResolver::class)
                : null;

            return new MarkdownFormatter(
                resolver: $this->createTemplateResolver($container, $config, 'md'),
                engine: $engine,
                fallback: new MarkdownFallbackFormatter(),
                helpers: $helpers instanceof HelperResolver ? $helpers : null,
            );
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

        $topLevelNamespace = strstr($rootNamespace, '\\', true) ?: $rootNamespace;

        return new TemplateResolver(
            rootDirectory: $kernel->rootDirectory(),
            rootNamespace: $topLevelNamespace,
            extension: $extension,
        );
    }

    private function registerResponseRenderers(Application $container): void
    {
        $container->service(JsonResponseRenderer::class);
        $container->service(CsvResponseRenderer::class);
        $container->service(HtmlResponseRenderer::class);
        $container->service(PlainTextResponseRenderer::class);
        $container->service(MarkdownResponseRenderer::class);
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
}
