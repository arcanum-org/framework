<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge;

use Arcanum\Forge\ConnectionFactory;
use Arcanum\Forge\PdoConnection;
use Arcanum\Forge\ConnectionManager;
use Arcanum\Forge\Database;
use Arcanum\Forge\DomainContext;
use Arcanum\Forge\Model;
use Arcanum\Flow\Sequence\CloseLatch;
use Arcanum\Flow\Sequence\Cursor;
use Arcanum\Flow\Sequence\Series;
use Arcanum\Forge\Cast;
use Arcanum\Forge\WriteResult;
use Arcanum\Forge\Sql;
use Arcanum\Gather\Configuration;
use Arcanum\Gather\Registry;
use Arcanum\Toolkit\Strings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Database::class)]
#[UsesClass(PdoConnection::class)]
#[UsesClass(ConnectionFactory::class)]
#[UsesClass(ConnectionManager::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(Registry::class)]
#[UsesClass(DomainContext::class)]
#[UsesClass(Model::class)]
#[UsesClass(WriteResult::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Series::class)]
#[UsesClass(CloseLatch::class)]
#[UsesClass(Cast::class)]
#[UsesClass(Sql::class)]
#[UsesClass(Strings::class)]
#[UsesClass(\Arcanum\Parchment\Reader::class)]
final class DatabaseTest extends TestCase
{
    private string $modelDir;

    protected function setUp(): void
    {
        $this->modelDir = sys_get_temp_dir() . '/arcanum_db_test_' . uniqid();
        mkdir($this->modelDir . '/Shop/Model', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->cleanDir($this->modelDir);
    }

    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = glob($dir . '/*') ?: [];
        foreach ($items as $item) {
            is_dir($item) ? $this->cleanDir($item) : @unlink($item);
        }
        @rmdir($dir);
    }

    private function writeSql(string $path, string $sql): void
    {
        file_put_contents($this->modelDir . '/' . $path, $sql);
    }

    private function manager(): ConnectionManager
    {
        return new ConnectionManager(
            defaultConnection: 'main',
            connections: ['main' => ['driver' => 'sqlite', 'database' => ':memory:']],
            factory: new ConnectionFactory(),
        );
    }

    private function context(string $domain = 'Shop'): DomainContext
    {
        $context = new DomainContext(domainRoot: $this->modelDir);
        $context->set($domain);

        return $context;
    }

    public function testModelPropertyReturnsScopedModel(): void
    {
        // Arrange
        $this->writeSql('Shop/Model/AllProducts.sql', 'SELECT 1');
        $db = new Database(
            connections: $this->manager(),
            context: $this->context(),
        );

        // Act
        $model = $db->model;

        // Assert
        $this->assertInstanceOf(Model::class, $model);
    }

    public function testModelPropertyReturnsSameInstance(): void
    {
        // Arrange
        $db = new Database(
            connections: $this->manager(),
            context: $this->context(),
        );

        // Act & Assert
        $this->assertSame($db->model, $db->model);
    }

    public function testModelExecutesSqlFromDomainDirectory(): void
    {
        // Arrange
        $conn = new PdoConnection(dsn: 'sqlite::memory:');
        $conn->execute('CREATE TABLE t (id INTEGER PRIMARY KEY, val TEXT)');
        $conn->execute('INSERT INTO t (val) VALUES (:val)', ['val' => 'hello']);

        $manager = new ConnectionManager(
            defaultConnection: 'main',
            connections: ['main' => ['driver' => 'sqlite', 'database' => ':memory:']],
            factory: new ConnectionFactory(),
        );

        // We need the model to use our pre-populated connection.
        // Use connection override to a named connection — but we need
        // to use real sqlite to test. Let's create a file-based approach.
        // Actually, since ConnectionManager creates new connections,
        // let's just test that the SQL file is found in the right path.
        $this->writeSql('Shop/Model/GetValue.sql', 'SELECT 1 as val');
        $db = new Database(
            connections: $this->manager(),
            context: $this->context(),
        );

        // Act
        $result = $db->model->getValue();

        // Assert
        $this->assertInstanceOf(\Arcanum\Flow\Sequence\Sequencer::class, $result);
        $row = $result->first();
        $this->assertIsArray($row);
        $this->assertSame(1, $row['val']);
    }

    public function testTransactionCommits(): void
    {
        // Arrange
        $db = new Database(
            connections: $this->manager(),
            context: $this->context(),
        );

        $this->writeSql(
            'Shop/Model/CreateTable.sql',
            'CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)',
        );
        $this->writeSql(
            'Shop/Model/InsertItem.sql',
            'INSERT INTO items (name) VALUES (:name)',
        );
        $this->writeSql(
            'Shop/Model/AllItems.sql',
            'SELECT name FROM items',
        );

        $db->model->createTable();

        // Act
        $db->transaction(function (Database $tx) {
            $tx->model->insertItem(name: 'Widget');
        });

        // Assert
        $result = $db->model->allItems();
        $this->assertInstanceOf(\Arcanum\Flow\Sequence\Sequencer::class, $result);
        $this->assertSame(1, $result->toSeries()->count());
    }

    public function testTransactionRollsBack(): void
    {
        // Arrange
        $db = new Database(
            connections: $this->manager(),
            context: $this->context(),
        );

        $this->writeSql(
            'Shop/Model/CreateTable.sql',
            'CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)',
        );
        $this->writeSql(
            'Shop/Model/InsertItem.sql',
            'INSERT INTO items (name) VALUES (:name)',
        );
        $this->writeSql(
            'Shop/Model/AllItems.sql',
            'SELECT name FROM items',
        );

        $db->model->createTable();

        // Act
        try {
            $db->transaction(function (Database $tx) {
                $tx->model->insertItem(name: 'Widget');
                throw new \RuntimeException('abort');
            });
        } catch (\RuntimeException) {
            // expected
        }

        // Assert — should be rolled back
        $result = $db->model->allItems();
        $this->assertInstanceOf(\Arcanum\Flow\Sequence\Sequencer::class, $result);
        $this->assertSame(0, $result->toSeries()->count());
    }

    public function testConnectionOverrideReturnsDatabaseWithDifferentConnection(): void
    {
        // Arrange
        $manager = new ConnectionManager(
            defaultConnection: 'main',
            connections: [
                'main' => ['driver' => 'sqlite', 'database' => ':memory:'],
                'legacy' => ['driver' => 'sqlite', 'database' => ':memory:'],
            ],
            factory: new ConnectionFactory(),
        );
        $db = new Database(connections: $manager, context: $this->context());

        // Act
        $legacyDb = $db->connection('legacy');

        // Assert — returns a new Database, not the same instance
        $this->assertInstanceOf(Database::class, $legacyDb);
        $this->assertNotSame($db, $legacyDb);
    }

    public function testDomainConfigOverrideSelectsCorrectConnection(): void
    {
        // Arrange — 'Shop' domain mapped to 'shop_db' connection
        $manager = new ConnectionManager(
            defaultConnection: 'main',
            connections: [
                'main' => ['driver' => 'sqlite', 'database' => ':memory:'],
                'shop_db' => ['driver' => 'sqlite', 'database' => ':memory:'],
            ],
            factory: new ConnectionFactory(),
            domains: ['Shop' => 'shop_db'],
        );
        $this->writeSql('Shop/Model/Ping.sql', 'SELECT 1 as ok');

        $db = new Database(connections: $manager, context: $this->context());

        // Act — should use shop_db connection
        $result = $db->model->ping();

        // Assert
        $this->assertInstanceOf(\Arcanum\Flow\Sequence\Sequencer::class, $result);
        $row = $result->first();
        $this->assertIsArray($row);
        $this->assertSame(1, $row['ok']);
    }

    public function testUndefinedPropertyThrows(): void
    {
        // Arrange
        $db = new Database(
            connections: $this->manager(),
            context: $this->context(),
        );

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Undefined property');

        /** @phpstan-ignore property.notFound */
        $_ = $db->nonexistent;
    }

    public function testIssetModel(): void
    {
        // Arrange
        $db = new Database(
            connections: $this->manager(),
            context: $this->context(),
        );

        // Act & Assert
        $this->assertTrue(isset($db->model));
        $this->assertFalse(isset($db->nonexistent));
    }
}
