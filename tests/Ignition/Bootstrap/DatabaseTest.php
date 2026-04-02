<?php

declare(strict_types=1);

namespace Arcanum\Test\Ignition\Bootstrap;

use Arcanum\Cabinet\Container;
use Arcanum\Forge\ConnectionFactory;
use Arcanum\Forge\ConnectionManager;
use Arcanum\Forge\Database as DatabaseService;
use Arcanum\Forge\DomainContext;
use Arcanum\Gather\Configuration;
use Arcanum\Gather\Registry;
use Arcanum\Ignition\Bootstrap\Database;
use Arcanum\Ignition\Kernel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Database::class)]
#[UsesClass(Container::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(Registry::class)]
#[UsesClass(ConnectionManager::class)]
#[UsesClass(ConnectionFactory::class)]
#[UsesClass(DatabaseService::class)]
#[UsesClass(DomainContext::class)]
#[UsesClass(\Arcanum\Forge\PdoConnection::class)]
#[UsesClass(\Arcanum\Cabinet\PrototypeProvider::class)]
#[UsesClass(\Arcanum\Toolkit\Strings::class)]
final class DatabaseTest extends TestCase
{
    private function kernelStub(string $rootDir = '/app'): Kernel
    {
        $kernel = $this->createMock(Kernel::class);
        $kernel->method('rootDirectory')->willReturn($rootDir);

        return $kernel;
    }

    private function containerWith(Configuration $config, Kernel $kernel): Container
    {
        $container = new Container();
        $container->instance(Configuration::class, $config);
        $container->instance(Kernel::class, $kernel);

        return $container;
    }

    public function testRegistersDatabaseInContainer(): void
    {
        // Arrange
        $config = new Configuration([
            'app' => ['namespace' => 'App\\Domain'],
            'database' => [
                'default' => 'sqlite',
                'connections' => [
                    'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:'],
                ],
            ],
        ]);
        $container = $this->containerWith($config, $this->kernelStub());

        // Act
        (new Database())->bootstrap($container);

        // Assert
        $this->assertTrue($container->has(DatabaseService::class));
        $this->assertTrue($container->has(ConnectionManager::class));
        $this->assertTrue($container->has(DomainContext::class));
    }

    public function testConnectionManagerConfiguredFromConfig(): void
    {
        // Arrange
        $config = new Configuration([
            'app' => ['namespace' => 'App\\Domain'],
            'database' => [
                'default' => 'main',
                'connections' => [
                    'main' => ['driver' => 'sqlite', 'database' => ':memory:'],
                    'analytics' => ['driver' => 'sqlite', 'database' => ':memory:'],
                ],
                'domains' => ['Analytics' => 'analytics'],
            ],
        ]);
        $container = $this->containerWith($config, $this->kernelStub());

        // Act
        (new Database())->bootstrap($container);

        /** @var ConnectionManager $manager */
        $manager = $container->get(ConnectionManager::class);

        // Assert
        $this->assertSame('main', $manager->defaultConnectionName());
        $this->assertSame(['main', 'analytics'], $manager->connectionNames());
        $this->assertSame(['Analytics' => 'analytics'], $manager->domainMapping());
    }

    public function testSkipsGracefullyWithNoConfig(): void
    {
        // Arrange — no 'database' key in config
        $config = new Configuration([
            'app' => ['namespace' => 'App\\Domain'],
        ]);
        $container = $this->containerWith($config, $this->kernelStub());

        // Act
        (new Database())->bootstrap($container);

        // Assert — nothing registered, no error
        $this->assertFalse($container->has(DatabaseService::class));
        $this->assertFalse($container->has(ConnectionManager::class));
        $this->assertFalse($container->has(DomainContext::class));
    }

    public function testDomainContextHasCorrectRootPath(): void
    {
        // Arrange
        $config = new Configuration([
            'app' => ['namespace' => 'App\\Domain'],
            'database' => [
                'default' => 'sqlite',
                'connections' => [
                    'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:'],
                ],
            ],
        ]);
        $container = $this->containerWith($config, $this->kernelStub('/project'));

        // Act
        (new Database())->bootstrap($container);

        /** @var DomainContext $context */
        $context = $container->get(DomainContext::class);
        $context->set('Shop');

        // Assert
        $expected = '/project' . DIRECTORY_SEPARATOR . 'app'
            . DIRECTORY_SEPARATOR . 'Domain'
            . DIRECTORY_SEPARATOR . 'Shop'
            . DIRECTORY_SEPARATOR . 'Model';
        $this->assertSame($expected, $context->modelPath());
    }

    public function testDefaultsToSqliteMemoryWithEmptyConnections(): void
    {
        // Arrange — database key exists but no connections
        $config = new Configuration([
            'app' => ['namespace' => 'App\\Domain'],
            'database' => [],
        ]);
        $container = $this->containerWith($config, $this->kernelStub());

        // Act
        (new Database())->bootstrap($container);

        /** @var ConnectionManager $manager */
        $manager = $container->get(ConnectionManager::class);

        // Assert
        $this->assertSame('sqlite', $manager->defaultConnectionName());
        $this->assertSame(['sqlite'], $manager->connectionNames());
    }
}
