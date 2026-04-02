<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Cabinet\Application;
use Arcanum\Forge\ConnectionFactory;
use Arcanum\Forge\ConnectionManager;
use Arcanum\Forge\Database as DatabaseService;
use Arcanum\Forge\DomainContext;
use Arcanum\Forge\ModelGenerator;
use Arcanum\Gather\Configuration;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Ignition\Kernel;
use Arcanum\Toolkit\Strings;

/**
 * Registers the Database service, ConnectionManager, and DomainContext.
 *
 * Reads `config/database.php` for connection configuration. If no config
 * is present, skips gracefully — apps without a database are unaffected.
 *
 * Registers:
 * - `ConnectionManager` — named connection factory
 * - `DomainContext` — request-scoped domain holder
 * - `Database` — developer-facing service (Database::model, transactions)
 *
 * Must run after `Bootstrap\Cache`.
 */
class Database implements Bootstrapper
{
    public function bootstrap(Application $container): void
    {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);

        // Skip gracefully if no database config exists.
        if (!$config->has('database')) {
            return;
        }

        /** @var Kernel $kernel */
        $kernel = $container->get(Kernel::class);

        $manager = $this->buildConnectionManager($config);
        $context = $this->buildDomainContext($config, $kernel);

        $container->instance(ConnectionManager::class, $manager);
        $container->instance(DomainContext::class, $context);

        $namespace = $config->asString('app.namespace', 'App\\Domain');

        $debug = $config->asBool('app.debug');
        $autoForgeRaw = $config->get('database.auto_forge');
        $autoForge = is_bool($autoForgeRaw) ? $autoForgeRaw : ($debug ? true : null);

        $container->instance(
            DatabaseService::class,
            new DatabaseService(
                connections: $manager,
                context: $context,
                domainNamespace: $namespace,
                autoForge: $autoForge,
                generator: $autoForge !== null
                    ? new ModelGenerator(rootDirectory: $kernel->rootDirectory())
                    : null,
            ),
        );
    }

    private function buildConnectionManager(Configuration $config): ConnectionManager
    {
        $default = $config->asString('database.default', 'sqlite');

        /** @var array<string, array<string, mixed>> $connections */
        $connections = $config->get('database.connections') ?? [];

        // Ensure a default sqlite connection exists if nothing is configured.
        if ($connections === []) {
            $connections = [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ],
            ];
        }

        $read = $config->get('database.read');
        $write = $config->get('database.write');

        /** @var array<string, string> $domains */
        $domains = $config->get('database.domains') ?? [];

        return new ConnectionManager(
            defaultConnection: $default,
            connections: $connections,
            factory: new ConnectionFactory(),
            readConnection: is_string($read) ? $read : null,
            writeConnection: is_string($write) ? $write : null,
            domains: $domains,
        );
    }

    private function buildDomainContext(Configuration $config, Kernel $kernel): DomainContext
    {
        $namespace = $config->asString('app.namespace', 'App\\Domain');
        $domainRoot = $kernel->rootDirectory()
            . DIRECTORY_SEPARATOR . Strings::namespacePath($namespace);

        return new DomainContext(domainRoot: $domainRoot);
    }
}
