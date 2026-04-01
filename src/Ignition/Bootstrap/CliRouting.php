<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Atlas\CliRouteMap;
use Arcanum\Atlas\CliRouter;
use Arcanum\Atlas\ConventionResolver;
use Arcanum\Atlas\Router;
use Arcanum\Cabinet\Application;
use Arcanum\Codex\Hydrator;
use Arcanum\Gather\Configuration;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Rune\CliExceptionWriter;
use Arcanum\Rune\ConsoleOutput;
use Arcanum\Rune\Output;
use Arcanum\Shodo\CliFormatRegistry;
use Arcanum\Shodo\CliRenderer;
use Arcanum\Shodo\CsvRenderer;
use Arcanum\Shodo\JsonRenderer;
use Arcanum\Shodo\PlainTextRenderer;
use Arcanum\Shodo\TableRenderer;

/**
 * Registers CLI routing and output services in the container.
 *
 * Parallels Bootstrap\Routing for HTTP. Reads the same app.namespace
 * config to build the ConventionResolver, and reads routes.cli for
 * custom CLI aliases.
 *
 * Reads from config/app.php:
 *   - namespace (required) — the app's root namespace
 *
 * Reads from config/routes.php:
 *   - cli (optional) — name => {class, type} CLI route aliases
 */
class CliRouting implements Bootstrapper
{
    public function bootstrap(Application $container): void
    {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);

        $this->registerRouter($container, $config);
        $this->registerFormats($container);
        $this->registerOutput($container, $config);
        $this->registerHydrator($container);
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

        // Reuse existing ConventionResolver if already registered (e.g., by HTTP Routing).
        if ($container->has(ConventionResolver::class)) {
            /** @var ConventionResolver $resolver */
            $resolver = $container->get(ConventionResolver::class);
        } else {
            $resolver = new ConventionResolver(rootNamespace: $namespace);
            $container->instance(ConventionResolver::class, $resolver);
        }

        $routeMap = new CliRouteMap();

        /** @var array<string, array{class: string, type?: string}>|null $cliRoutes */
        $cliRoutes = $config->get('routes.cli');
        foreach ($cliRoutes ?? [] as $name => $definition) {
            $routeMap->register(
                name: $name,
                dtoClass: $definition['class'],
                type: $definition['type'] ?? 'command',
            );
        }

        $container->instance(CliRouteMap::class, $routeMap);

        $container->factory(Router::class, function () use ($resolver, $routeMap) {
            return new CliRouter($resolver, $routeMap);
        });
    }

    private function registerFormats(Application $container): void
    {
        $container->factory(CliFormatRegistry::class, function () use ($container) {
            $registry = new CliFormatRegistry($container);
            $registry->register('cli', CliRenderer::class);
            $registry->register('table', TableRenderer::class);
            $registry->register('json', JsonRenderer::class);
            $registry->register('csv', CsvRenderer::class);
            $registry->register('text', PlainTextRenderer::class);
            return $registry;
        });

        $container->service(CliRenderer::class);
        $container->service(TableRenderer::class);
        $container->service(JsonRenderer::class);
        $container->service(CsvRenderer::class);
    }

    private function registerOutput(Application $container, Configuration $config): void
    {
        if (!$container->has(Output::class)) {
            $container->instance(Output::class, new ConsoleOutput());
        }

        /** @var mixed $debug */
        $debug = $config->get('app.debug');
        $isDebug = $debug === true || $debug === 'true';

        $container->factory(CliExceptionWriter::class, function () use ($container, $isDebug) {
            /** @var Output $output */
            $output = $container->get(Output::class);
            return new CliExceptionWriter($output, $isDebug);
        });
    }

    private function registerHydrator(Application $container): void
    {
        if (!$container->has(Hydrator::class)) {
            $container->service(Hydrator::class);
        }
    }
}
