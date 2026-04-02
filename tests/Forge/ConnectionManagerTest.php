<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge;

use Arcanum\Forge\Connection;
use Arcanum\Forge\ConnectionFactory;
use Arcanum\Forge\ConnectionManager;
use Arcanum\Forge\Result;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(ConnectionManager::class)]
#[UsesClass(Connection::class)]
#[UsesClass(ConnectionFactory::class)]
#[UsesClass(Result::class)]
final class ConnectionManagerTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function sqliteConfig(): array
    {
        return ['driver' => 'sqlite', 'database' => ':memory:'];
    }

    /**
     * @param array<string, array<string, string>> $connections
     * @param array<string, string> $domains
     */
    private function manager(
        string $default = 'main',
        array $connections = [],
        string|null $read = null,
        string|null $write = null,
        array $domains = [],
    ): ConnectionManager {
        if ($connections === []) {
            $connections = ['main' => $this->sqliteConfig()];
        }

        return new ConnectionManager(
            defaultConnection: $default,
            connections: $connections,
            factory: new ConnectionFactory(),
            readConnection: $read,
            writeConnection: $write,
            domains: $domains,
        );
    }

    public function testResolvesDefaultConnection(): void
    {
        // Arrange
        $manager = $this->manager();

        // Act
        $conn = $manager->connection();

        // Assert
        $this->assertInstanceOf(Connection::class, $conn);
    }

    public function testResolvesNamedConnection(): void
    {
        // Arrange
        $manager = $this->manager(
            connections: [
                'main' => $this->sqliteConfig(),
                'secondary' => $this->sqliteConfig(),
            ],
        );

        // Act
        $main = $manager->connection('main');
        $secondary = $manager->connection('secondary');

        // Assert
        $this->assertInstanceOf(Connection::class, $main);
        $this->assertInstanceOf(Connection::class, $secondary);
        $this->assertNotSame($main, $secondary);
    }

    public function testLazilyInstantiatesConnections(): void
    {
        // Arrange
        $manager = $this->manager();

        // Act
        $first = $manager->connection('main');
        $second = $manager->connection('main');

        // Assert
        $this->assertSame($first, $second);
    }

    public function testThrowsOnUnconfiguredConnection(): void
    {
        // Arrange
        $manager = $this->manager();

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection "nonexistent" is not configured');
        $manager->connection('nonexistent');
    }

    public function testConnectionForDomainResolvesMapping(): void
    {
        // Arrange
        $manager = $this->manager(
            connections: [
                'main' => $this->sqliteConfig(),
                'analytics' => $this->sqliteConfig(),
            ],
            domains: ['Analytics' => 'analytics'],
        );

        // Act
        $conn = $manager->connectionForDomain('Analytics');

        // Assert — should be the analytics connection, not main
        $this->assertSame($conn, $manager->connection('analytics'));
    }

    public function testConnectionForDomainFallsBackToDefault(): void
    {
        // Arrange
        $manager = $this->manager(
            domains: ['Analytics' => 'main'],
        );

        // Act
        $conn = $manager->connectionForDomain('Shop');

        // Assert — unmapped domain gets default
        $this->assertSame($conn, $manager->connection());
    }

    public function testReadConnectionReturnsConfiguredReplica(): void
    {
        // Arrange
        $manager = $this->manager(
            connections: [
                'main' => $this->sqliteConfig(),
                'replica' => $this->sqliteConfig(),
            ],
            read: 'replica',
        );

        // Act
        $read = $manager->readConnection();

        // Assert
        $this->assertSame($read, $manager->connection('replica'));
        $this->assertNotSame($read, $manager->connection('main'));
    }

    public function testReadConnectionFallsBackToDefault(): void
    {
        // Arrange — no read replica configured
        $manager = $this->manager();

        // Act
        $read = $manager->readConnection();

        // Assert
        $this->assertSame($read, $manager->connection());
    }

    public function testWriteConnectionReturnsConfiguredPrimary(): void
    {
        // Arrange
        $manager = $this->manager(
            connections: [
                'main' => $this->sqliteConfig(),
                'primary' => $this->sqliteConfig(),
            ],
            write: 'primary',
        );

        // Act
        $write = $manager->writeConnection();

        // Assert
        $this->assertSame($write, $manager->connection('primary'));
        $this->assertNotSame($write, $manager->connection('main'));
    }

    public function testWriteConnectionFallsBackToDefault(): void
    {
        // Arrange — no write primary configured
        $manager = $this->manager();

        // Act
        $write = $manager->writeConnection();

        // Assert
        $this->assertSame($write, $manager->connection());
    }

    public function testDefaultConnectionName(): void
    {
        // Arrange
        $manager = $this->manager(default: 'primary');

        // Act & Assert
        $this->assertSame('primary', $manager->defaultConnectionName());
    }

    public function testConnectionNamesReturnsAll(): void
    {
        // Arrange
        $manager = $this->manager(
            connections: [
                'main' => $this->sqliteConfig(),
                'analytics' => $this->sqliteConfig(),
            ],
        );

        // Act & Assert
        $this->assertSame(['main', 'analytics'], $manager->connectionNames());
    }

    public function testDriverNameReturnsConfiguredDriver(): void
    {
        // Arrange
        $manager = $this->manager(
            connections: ['main' => ['driver' => 'sqlite', 'database' => ':memory:']],
        );

        // Act & Assert
        $this->assertSame('sqlite', $manager->driverName('main'));
    }

    public function testDriverNameReturnsUnknownForMissing(): void
    {
        // Arrange
        $manager = $this->manager();

        // Act & Assert
        $this->assertSame('unknown', $manager->driverName('nonexistent'));
    }

    public function testDomainMapping(): void
    {
        // Arrange
        $manager = $this->manager(
            domains: ['Analytics' => 'analytics', 'Shop' => 'main'],
        );

        // Act & Assert
        $this->assertSame(
            ['Analytics' => 'analytics', 'Shop' => 'main'],
            $manager->domainMapping(),
        );
    }

    public function testReadWriteSplitWithRealQueries(): void
    {
        // Arrange
        $manager = $this->manager(
            connections: [
                'main' => $this->sqliteConfig(),
                'replica' => $this->sqliteConfig(),
            ],
            read: 'replica',
            write: 'main',
        );

        // Act — write to primary, read from replica
        $write = $manager->writeConnection();
        $write->run('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');
        $write->run('INSERT INTO items (name) VALUES (:name)', ['name' => 'Widget']);

        $read = $manager->readConnection();
        $read->run('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');

        // Assert — replica has its own state (separate in-memory databases)
        $readResult = $read->run('SELECT count(*) as cnt FROM items');
        $this->assertNotNull($readResult->first());
        $this->assertSame(0, $readResult->first()['cnt']);

        $writeResult = $write->run('SELECT count(*) as cnt FROM items');
        $this->assertNotNull($writeResult->first());
        $this->assertSame(1, $writeResult->first()['cnt']);
    }
}
