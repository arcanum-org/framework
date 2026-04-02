<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge;

use Arcanum\Forge\Connection;
use Arcanum\Forge\Result;
use Arcanum\Forge\Sql;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Connection::class)]
#[UsesClass(Result::class)]
#[UsesClass(Sql::class)]
final class ConnectionTest extends TestCase
{
    private function sqlite(): Connection
    {
        return new Connection(dsn: 'sqlite::memory:');
    }

    public function testRunReturnsResultForSelect(): void
    {
        // Arrange
        $conn = $this->sqlite();
        $conn->run('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');
        $conn->run('INSERT INTO items (name) VALUES (:name)', ['name' => 'Widget']);

        // Act
        $result = $conn->run('SELECT id, name FROM items');

        // Assert
        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(1, $result->count());
        $this->assertNotNull($result->first());
        $this->assertSame('Widget', $result->first()['name']);
    }

    public function testRunBindsNamedParameters(): void
    {
        // Arrange
        $conn = $this->sqlite();
        $conn->run('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT, color TEXT)');
        $conn->run('INSERT INTO items (name, color) VALUES (:name, :color)', [
            'name' => 'Widget',
            'color' => 'blue',
        ]);
        $conn->run('INSERT INTO items (name, color) VALUES (:name, :color)', [
            'name' => 'Gadget',
            'color' => 'red',
        ]);

        // Act
        $result = $conn->run('SELECT name FROM items WHERE color = :color', ['color' => 'blue']);

        // Assert
        $this->assertSame(1, $result->count());
        $this->assertNotNull($result->first());
        $this->assertSame('Widget', $result->first()['name']);
    }

    public function testRunReturnsAffectedRowsForWrite(): void
    {
        // Arrange
        $conn = $this->sqlite();
        $conn->run('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');
        $conn->run('INSERT INTO items (name) VALUES (:name)', ['name' => 'Widget']);
        $conn->run('INSERT INTO items (name) VALUES (:name)', ['name' => 'Gadget']);

        // Act
        $result = $conn->run('UPDATE items SET name = :name', ['name' => 'Updated']);

        // Assert
        $this->assertSame(2, $result->affectedRows());
    }

    public function testRunReturnsLastInsertIdForInsert(): void
    {
        // Arrange
        $conn = $this->sqlite();
        $conn->run('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');

        // Act
        $result = $conn->run('INSERT INTO items (name) VALUES (:name)', ['name' => 'Widget']);

        // Assert
        $this->assertSame('1', $result->lastInsertId());
    }

    public function testLazyConnection(): void
    {
        // Arrange
        $conn = $this->sqlite();

        // Act & Assert — not connected until first use
        $this->assertFalse($conn->isConnected());

        $conn->run('SELECT 1');

        $this->assertTrue($conn->isConnected());
    }

    public function testTransactionCommits(): void
    {
        // Arrange
        $conn = $this->sqlite();
        $conn->run('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');

        // Act
        $conn->beginTransaction();
        $conn->run('INSERT INTO items (name) VALUES (:name)', ['name' => 'Widget']);
        $conn->commit();

        // Assert
        $result = $conn->run('SELECT name FROM items');
        $this->assertSame(1, $result->count());
        $this->assertNotNull($result->first());
        $this->assertSame('Widget', $result->first()['name']);
    }

    public function testTransactionRollsBack(): void
    {
        // Arrange
        $conn = $this->sqlite();
        $conn->run('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');

        // Act
        $conn->beginTransaction();
        $conn->run('INSERT INTO items (name) VALUES (:name)', ['name' => 'Widget']);
        $conn->rollBack();

        // Assert
        $result = $conn->run('SELECT name FROM items');
        $this->assertSame(0, $result->count());
    }

    public function testSelectWithLeadingCommentUsesReadPath(): void
    {
        // Arrange
        $conn = $this->sqlite();
        $conn->run('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');
        $conn->run('INSERT INTO items (name) VALUES (:name)', ['name' => 'Widget']);

        // Act — SQL with leading comments should still be treated as a read
        $result = $conn->run("-- fetch all items\nSELECT name FROM items");

        // Assert
        $this->assertSame(1, $result->count());
        $this->assertNotNull($result->first());
        $this->assertSame('Widget', $result->first()['name']);
    }

    public function testParameterlessSqlWorks(): void
    {
        // Arrange
        $conn = $this->sqlite();
        $conn->run('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT DEFAULT \'test\')');
        $conn->run('INSERT INTO items DEFAULT VALUES');

        // Act
        $result = $conn->run('SELECT name FROM items');

        // Assert
        $this->assertNotNull($result->first());
        $this->assertSame('test', $result->first()['name']);
    }
}
