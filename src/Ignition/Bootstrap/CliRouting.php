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
use Arcanum\Ignition\Kernel;
use Arcanum\Rune\BuiltInRegistry;
use Arcanum\Rune\CliExceptionWriter;
use Arcanum\Rune\Command\HelpCommand;
use Arcanum\Rune\Command\ListCommand;
use Arcanum\Ignition\ConfigurationCache;
use Arcanum\Rune\Command\CacheClearCommand;
use Arcanum\Rune\Command\MakeKeyCommand;
use Arcanum\Rune\Command\ValidateHandlersCommand;
use Arcanum\Shodo\TemplateCache;
use Arcanum\Vault\CacheManager;
use Arcanum\Rune\ConsoleOutput;
use Arcanum\Rune\Output;
use Arcanum\Shodo\CliFormatRegistry;
use Arcanum\Shodo\CsvFormatter;
use Arcanum\Shodo\JsonFormatter;
use Arcanum\Shodo\KeyValueFormatter;
use Arcanum\Shodo\TableFormatter;
use Arcanum\Flow\Conveyor\Bus;
use Arcanum\Flow\Conveyor\MiddlewareBus;
use Arcanum\Validation\ValidationGuard;

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
        $this->registerBuiltIns($container, $config);
        $this->registerBusMiddleware($container);
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
            $registry->register('cli', KeyValueFormatter::class);
            $registry->register('table', TableFormatter::class);
            $registry->register('json', JsonFormatter::class);
            $registry->register('csv', CsvFormatter::class);
            return $registry;
        });

        $container->service(KeyValueFormatter::class);
        $container->service(TableFormatter::class);
        $container->service(JsonFormatter::class);
        $container->service(CsvFormatter::class);
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

    /**
     * Resolve the source directory for a namespace using PSR-4 convention.
     *
     * Maps the top-level namespace segment to a lowercased directory name
     * (e.g., "App\Domain" → "app/Domain"). This matches standard Composer
     * PSR-4 autoload mappings.
     */
    private function resolveSourceDirectory(Application $container, string $namespace): string
    {
        /** @var Kernel $kernel */
        $kernel = $container->get(Kernel::class);

        $topLevel = strstr($namespace, '\\', true) ?: $namespace;
        $subPath = str_replace('\\', DIRECTORY_SEPARATOR, substr($namespace, strlen($topLevel)));

        return $kernel->rootDirectory()
            . DIRECTORY_SEPARATOR . lcfirst($topLevel)
            . $subPath;
    }

    private function registerBuiltIns(Application $container, Configuration $config): void
    {
        /** @var mixed $namespace */
        $namespace = $config->get('app.namespace');
        $namespace = is_string($namespace) ? $namespace : 'App';

        $container->factory(BuiltInRegistry::class, function () use ($container) {
            $registry = new BuiltInRegistry($container);
            $registry->register('list', ListCommand::class);
            $registry->register('help', HelpCommand::class);
            $registry->register('validate:handlers', ValidateHandlersCommand::class);
            $registry->register('make:key', MakeKeyCommand::class);
            $registry->register('cache:clear', CacheClearCommand::class);
            return $registry;
        });

        // Register the built-in command classes.
        $container->service(HelpCommand::class);

        $container->factory(ListCommand::class, function () use ($container, $namespace) {
            $sourceDir = $this->resolveSourceDirectory($container, $namespace);

            /** @var CliRouteMap|null $routeMap */
            $routeMap = $container->has(CliRouteMap::class)
                ? $container->get(CliRouteMap::class)
                : null;

            /** @var BuiltInRegistry|null $builtInRegistry */
            $builtInRegistry = $container->has(BuiltInRegistry::class)
                ? $container->get(BuiltInRegistry::class)
                : null;

            return new ListCommand(
                sourceDirectory: $sourceDir,
                rootNamespace: $namespace,
                routeMap: $routeMap,
                builtInRegistry: $builtInRegistry,
            );
        });

        $container->factory(ValidateHandlersCommand::class, function () use ($container, $namespace) {
            return new ValidateHandlersCommand(
                sourceDirectory: $this->resolveSourceDirectory($container, $namespace),
                rootNamespace: $namespace,
            );
        });

        $container->factory(MakeKeyCommand::class, function () use ($container) {
            /** @var Kernel $kernel */
            $kernel = $container->get(Kernel::class);
            return new MakeKeyCommand(rootDirectory: $kernel->rootDirectory());
        });

        $container->factory(CacheClearCommand::class, function () use ($container) {
            $cacheManager = $container->has(CacheManager::class)
                ? $container->get(CacheManager::class)
                : null;

            $configCache = $container->has(ConfigurationCache::class)
                ? $container->get(ConfigurationCache::class)
                : null;

            $templateCache = $container->has(TemplateCache::class)
                ? $container->get(TemplateCache::class)
                : null;

            /** @var CacheManager|null $cacheManager */
            /** @var ConfigurationCache|null $configCache */
            /** @var TemplateCache|null $templateCache */
            return new CacheClearCommand($cacheManager, $configCache, $templateCache);
        });
    }

    private function registerBusMiddleware(Application $container): void
    {
        if (!$container->has(Bus::class)) {
            return;
        }

        /** @var Bus $bus */
        $bus = $container->get(Bus::class);

        if ($bus instanceof MiddlewareBus) {
            $bus->before(new ValidationGuard());
        }
    }
}
