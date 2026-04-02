<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Cabinet\Application;
use Arcanum\Forge\ConnectionFactory;
use Arcanum\Forge\ConnectionManager;
use Arcanum\Forge\Database as DatabaseService;
use Arcanum\Forge\DomainContext;
use Arcanum\Gather\Configuration;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Ignition\Kernel;

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

        $container->instance(
            DatabaseService::class,
            new DatabaseService(
                connections: $manager,
                context: $context,
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

        // Convert namespace to directory path: App\Domain → app/Domain
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $namespace);

        // Lowercase the first segment to match PSR-4 directory convention.
        $firstSep = strpos($relativePath, DIRECTORY_SEPARATOR);
        if ($firstSep !== false) {
            $relativePath = lcfirst(substr($relativePath, 0, $firstSep))
                . substr($relativePath, $firstSep);
        } else {
            $relativePath = lcfirst($relativePath);
        }

        $domainRoot = $kernel->rootDirectory() . DIRECTORY_SEPARATOR . $relativePath;

        return new DomainContext(domainRoot: $domainRoot);
    }
}
