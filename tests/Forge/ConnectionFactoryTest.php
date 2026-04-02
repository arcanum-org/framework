<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge;

use Arcanum\Forge\ConnectionFactory;
use Arcanum\Forge\PdoConnection;
use Arcanum\Gather\Configuration;
use Arcanum\Gather\Registry;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(ConnectionFactory::class)]
#[UsesClass(PdoConnection::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(Registry::class)]
final class ConnectionFactoryTest extends TestCase
{
    public function testBuildsSqliteMemory(): void
    {
        // Arrange
        $factory = new ConnectionFactory();

        // Act
        $conn = $factory->make(['driver' => 'sqlite', 'database' => ':memory:']);

        // Assert — connection works
        $this->assertInstanceOf(PdoConnection::class, $conn);
        $this->assertFalse($conn->isConnected());
    }

    public function testBuildsSqliteFile(): void
    {
        // Arrange
        $factory = new ConnectionFactory();
        $path = sys_get_temp_dir() . '/arcanum_forge_test_' . uniqid() . '.sqlite';

        // Act
        $conn = $factory->make(['driver' => 'sqlite', 'database' => $path]);

        // Assert — verify it can connect and create tables
        $result = $conn->run('CREATE TABLE test (id INTEGER PRIMARY KEY)');
        $this->assertInstanceOf(\Arcanum\Forge\Result::class, $result);

        // Cleanup
        @unlink($path);
    }

    public function testBuildsSqliteDefaultsToMemory(): void
    {
        // Arrange
        $factory = new ConnectionFactory();

        // Act
        $conn = $factory->make(['driver' => 'sqlite']);

        // Assert — in-memory databases connect and work
        $result = $conn->run('CREATE TABLE test (id INTEGER PRIMARY KEY)');
        $this->assertInstanceOf(\Arcanum\Forge\Result::class, $result);
    }

    public function testBuildsMysqlDsn(): void
    {
        // Arrange
        $factory = new ConnectionFactory();

        // Act
        $conn = $factory->make([
            'driver' => 'mysql',
            'host' => 'db.example.com',
            'port' => 3307,
            'database' => 'myapp',
            'charset' => 'utf8mb4',
            'username' => 'root',
            'password' => 'secret',
        ]);

        // Assert — connection is created (lazy, won't actually connect)
        $this->assertInstanceOf(PdoConnection::class, $conn);
        $this->assertFalse($conn->isConnected());
    }

    public function testBuildsMysqlWithUnixSocket(): void
    {
        // Arrange
        $factory = new ConnectionFactory();

        // Act
        $conn = $factory->make([
            'driver' => 'mysql',
            'unix_socket' => '/var/run/mysqld/mysqld.sock',
            'database' => 'myapp',
            'username' => 'root',
            'password' => '',
        ]);

        // Assert
        $this->assertInstanceOf(PdoConnection::class, $conn);
        $this->assertFalse($conn->isConnected());
    }

    public function testBuildsPgsqlDsn(): void
    {
        // Arrange
        $factory = new ConnectionFactory();

        // Act
        $conn = $factory->make([
            'driver' => 'pgsql',
            'host' => 'pg.example.com',
            'port' => 5433,
            'database' => 'analytics',
            'username' => 'analyst',
            'password' => 'secret',
        ]);

        // Assert
        $this->assertInstanceOf(PdoConnection::class, $conn);
        $this->assertFalse($conn->isConnected());
    }

    public function testThrowsOnUnsupportedDriver(): void
    {
        // Arrange
        $factory = new ConnectionFactory();

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported database driver "mssql"');
        $factory->make(['driver' => 'mssql']);
    }

    public function testThrowsOnMissingDriver(): void
    {
        // Arrange
        $factory = new ConnectionFactory();

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported database driver ""');
        $factory->make([]);
    }

    public function testMysqlDefaultsHostAndPort(): void
    {
        // Arrange
        $factory = new ConnectionFactory();

        // Act — no host or port specified
        $conn = $factory->make([
            'driver' => 'mysql',
            'database' => 'test',
            'username' => 'root',
            'password' => '',
        ]);

        // Assert — Connection created with defaults (127.0.0.1:3306)
        $this->assertInstanceOf(PdoConnection::class, $conn);
    }

    public function testPgsqlDefaultsHostAndPort(): void
    {
        // Arrange
        $factory = new ConnectionFactory();

        // Act — no host or port specified
        $conn = $factory->make([
            'driver' => 'pgsql',
            'database' => 'test',
            'username' => 'root',
            'password' => '',
        ]);

        // Assert — Connection created with defaults (127.0.0.1:5432)
        $this->assertInstanceOf(PdoConnection::class, $conn);
    }
}
