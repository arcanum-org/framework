<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge;

use Arcanum\Forge\PdoConnection;
use Arcanum\Forge\WriteResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdoConnection::class)]
#[UsesClass(WriteResult::class)]
final class PdoConnectionExecuteTest extends TestCase
{
    private function sqlite(): PdoConnection
    {
        return new PdoConnection(dsn: 'sqlite::memory:');
    }

    public function testExecuteReturnsWriteResult(): void
    {
        $conn = $this->sqlite();

        $result = $conn->execute('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');

        $this->assertInstanceOf(WriteResult::class, $result);
    }

    public function testInsertReturnsAffectedRowsAndLastInsertId(): void
    {
        $conn = $this->sqlite();
        $conn->execute('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');

        $result = $conn->execute(
            'INSERT INTO items (name) VALUES (:name)',
            ['name' => 'Widget'],
        );

        $this->assertSame(1, $result->affectedRows());
        $this->assertSame('1', $result->lastInsertId());
    }

    public function testUpdateReturnsAffectedRows(): void
    {
        $conn = $this->sqlite();
        $conn->execute('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');
        $conn->execute('INSERT INTO items (name) VALUES (:name)', ['name' => 'A']);
        $conn->execute('INSERT INTO items (name) VALUES (:name)', ['name' => 'B']);

        $result = $conn->execute('UPDATE items SET name = :name', ['name' => 'Updated']);

        $this->assertSame(2, $result->affectedRows());
    }

    public function testDeleteReturnsAffectedRows(): void
    {
        $conn = $this->sqlite();
        $conn->execute('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');
        $conn->execute('INSERT INTO items (name) VALUES (:name)', ['name' => 'A']);
        $conn->execute('INSERT INTO items (name) VALUES (:name)', ['name' => 'B']);
        $conn->execute('INSERT INTO items (name) VALUES (:name)', ['name' => 'C']);

        $result = $conn->execute('DELETE FROM items WHERE name = :name', ['name' => 'B']);

        $this->assertSame(1, $result->affectedRows());
    }

    public function testMultipleInsertsIncrementLastInsertId(): void
    {
        $conn = $this->sqlite();
        $conn->execute('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');

        $first = $conn->execute('INSERT INTO items (name) VALUES (:n)', ['n' => 'A']);
        $second = $conn->execute('INSERT INTO items (name) VALUES (:n)', ['n' => 'B']);

        $this->assertSame('1', $first->lastInsertId());
        $this->assertSame('2', $second->lastInsertId());
    }

    public function testTransactionCommits(): void
    {
        $conn = $this->sqlite();
        $conn->execute('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');

        $conn->beginTransaction();
        $conn->execute('INSERT INTO items (name) VALUES (:n)', ['n' => 'Widget']);
        $conn->commit();

        $row = $conn->query('SELECT name FROM items')->first();
        $this->assertNotNull($row);
        $this->assertSame('Widget', $row['name']);
    }

    public function testTransactionRollsBack(): void
    {
        $conn = $this->sqlite();
        $conn->execute('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');

        $conn->beginTransaction();
        $conn->execute('INSERT INTO items (name) VALUES (:n)', ['n' => 'Widget']);
        $conn->rollBack();

        $row = $conn->query('SELECT name FROM items')->first();
        $this->assertNull($row);
    }

    public function testDdlExecution(): void
    {
        $conn = $this->sqlite();

        $conn->execute('CREATE TABLE items (id INTEGER PRIMARY KEY)');
        $conn->execute('ALTER TABLE items ADD COLUMN name TEXT');
        $result = $conn->execute(
            'INSERT INTO items (name) VALUES (:n)',
            ['n' => 'ok'],
        );

        $this->assertSame(1, $result->affectedRows());
    }
}
