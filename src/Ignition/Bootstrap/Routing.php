<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Atlas\ConventionResolver;
use Arcanum\Atlas\HttpRouter;
use Arcanum\Atlas\PageResolver;
use Arcanum\Atlas\Router;
use Arcanum\Cabinet\Application;
use Arcanum\Codex\Hydrator;
use Arcanum\Gather\Configuration;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Shodo\Format;
use Arcanum\Shodo\FormatRegistry;
use Arcanum\Shodo\JsonRenderer;

/**
 * Registers Atlas routing and Shodo format registry in the container.
 *
 * Reads from config/routes.php:
 *   - namespace (required) — the app's root namespace
 *   - pages_namespace (required) — the Pages namespace
 *   - pages (optional) — path => format mappings for registered pages
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
        $this->registerHydrator($container);
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

        // Register JsonRenderer so it can be resolved from the container
        $container->service(JsonRenderer::class);
    }

    private function registerRouter(Application $container, Configuration $config): void
    {
        /** @var mixed $namespace */
        $namespace = $config->get('routes.namespace');
        if (!is_string($namespace) || $namespace === '') {
            throw new \RuntimeException(
                'Missing required config "routes.namespace". '
                . 'Set it in config/routes.php to your app\'s root namespace (e.g., "App").'
            );
        }

        /** @var mixed $pagesNamespace */
        $pagesNamespace = $config->get('routes.pages_namespace');
        if (!is_string($pagesNamespace) || $pagesNamespace === '') {
            throw new \RuntimeException(
                'Missing required config "routes.pages_namespace". '
                . 'Set it in config/routes.php to your Pages namespace (e.g., "App\\Pages").'
            );
        }

        /** @var mixed $defaultFormatRaw */
        $defaultFormatRaw = $config->get('formats.default');
        $defaultFormat = is_string($defaultFormatRaw) ? $defaultFormatRaw : 'json';

        $resolver = new ConventionResolver(rootNamespace: $namespace);
        $container->instance(ConventionResolver::class, $resolver);

        $pages = new PageResolver(namespace: $pagesNamespace);

        /** @var array<string, string|null>|null $pageRoutes */
        $pageRoutes = $config->get('routes.pages');
        foreach ($pageRoutes ?? [] as $path => $format) {
            $pages->register($path, $format);
        }

        $container->instance(PageResolver::class, $pages);

        $container->factory(Router::class, function () use ($resolver, $pages, $defaultFormat) {
            return new HttpRouter($resolver, $pages, $defaultFormat);
        });
    }

    private function registerHydrator(Application $container): void
    {
        $container->service(Hydrator::class);
    }
}
