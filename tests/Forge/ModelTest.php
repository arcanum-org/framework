<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge;

use Arcanum\Flow\Sequence\CloseLatch;
use Arcanum\Flow\Sequence\Cursor;
use Arcanum\Flow\Sequence\Sequencer;
use Arcanum\Flow\Sequence\Series;
use Arcanum\Forge\Cast;
use Arcanum\Forge\ConnectionFactory;
use Arcanum\Forge\ConnectionManager;
use Arcanum\Forge\Model;
use Arcanum\Forge\PdoConnection;
use Arcanum\Forge\Sql;
use Arcanum\Forge\WriteResult;
use Arcanum\Toolkit\Strings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Model::class)]
#[UsesClass(ConnectionFactory::class)]
#[UsesClass(ConnectionManager::class)]
#[UsesClass(PdoConnection::class)]
#[UsesClass(WriteResult::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Series::class)]
#[UsesClass(CloseLatch::class)]
#[UsesClass(Cast::class)]
#[UsesClass(Sql::class)]
#[UsesClass(Strings::class)]
#[UsesClass(\Arcanum\Parchment\Reader::class)]
final class ModelTest extends TestCase
{
    private string $modelDir;
    private ConnectionManager $manager;
    private PdoConnection $connection;

    protected function setUp(): void
    {
        $this->modelDir = sys_get_temp_dir() . '/arcanum_model_test_' . uniqid();
        mkdir($this->modelDir, 0777, true);

        $this->manager = new ConnectionManager(
            defaultConnection: 'main',
            connections: ['main' => ['driver' => 'sqlite', 'database' => ':memory:']],
            factory: new ConnectionFactory(),
        );

        /** @var PdoConnection $conn */
        $conn = $this->manager->connection();
        $this->connection = $conn;
        $this->createProductsTable($this->connection);
    }

    protected function tearDown(): void
    {
        $files = glob($this->modelDir . '/*') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($this->modelDir);
    }

    private function createProductsTable(PdoConnection $conn): void
    {
        $conn->execute(
            'CREATE TABLE products ('
            . 'id INTEGER PRIMARY KEY, name TEXT, price REAL, '
            . 'active INTEGER, quantity INTEGER, metadata TEXT'
            . ')'
        );
    }

    private function writeSql(string $filename, string $sql): void
    {
        file_put_contents($this->modelDir . '/' . $filename, $sql);
    }

    private function model(?ConnectionManager $manager = null): Model
    {
        return new Model(
            connections: $manager ?? $this->manager,
            directory: $this->modelDir,
        );
    }

    // ── Method resolution ────────────────────────────────────────

    public function testMethodCallResolvesToSqlFile(): void
    {
        // Arrange
        $this->writeSql('AllProducts.sql', 'SELECT id, name FROM products');
        $model = $this->model();

        // Act
        $result = $model->allProducts();

        // Assert
        $this->assertInstanceOf(Sequencer::class, $result);
    }

    public function testCamelCaseToPascalCaseConversion(): void
    {
        // Arrange
        $this->writeSql(
            'InsertProduct.sql',
            'INSERT INTO products (name) VALUES (:name)',
        );
        $model = $this->model();

        // Act
        $result = $model->insertProduct(name: 'Widget');

        // Assert
        $this->assertInstanceOf(WriteResult::class, $result);
        $this->assertSame('1', $result->lastInsertId());
    }

    public function testMissingFileThrowsWithHelpfulMessage(): void
    {
        // Arrange
        $model = $this->model();

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Model method 'findUser' not found — expected file:",
        );
        $this->expectExceptionMessage('FindUser.sql');
        $model->findUser();
    }

    public function testSqlFileContentsCached(): void
    {
        // Arrange
        $this->writeSql(
            'AllProducts.sql',
            'SELECT id, name FROM products',
        );
        $model = $this->model();

        // Act — call twice
        $model->allProducts();

        // Delete the file between calls
        unlink($this->modelDir . '/AllProducts.sql');

        // Second call should use cached content
        $result = $model->allProducts();

        // Assert
        $this->assertInstanceOf(Sequencer::class, $result);
    }

    public function testParameterlessSqlWorks(): void
    {
        // Arrange
        $this->connection->execute(
            'INSERT INTO products (name) VALUES (:name)',
            ['name' => 'Widget'],
        );
        $this->writeSql(
            'CountProducts.sql',
            'SELECT count(*) as cnt FROM products',
        );
        $model = $this->model();

        // Act
        $result = $model->countProducts();

        // Assert
        $this->assertInstanceOf(Sequencer::class, $result);
        $row = $result->first();
        $this->assertIsArray($row);
        $this->assertSame(1, $row['cnt']);
    }

    // ── Calling convention: named args ───────────────────────────

    public function testNamedArgsBoundToSqlBindings(): void
    {
        // Arrange
        $this->writeSql(
            'InsertProduct.sql',
            'INSERT INTO products (name, price) VALUES (:name, :price)',
        );
        $this->writeSql(
            'ProductByName.sql',
            'SELECT name, price FROM products WHERE name = :name',
        );
        $model = $this->model();

        // Act
        $model->insertProduct(name: 'Widget', price: 9.99);
        $result = $model->productByName(name: 'Widget');

        // Assert
        $this->assertInstanceOf(Sequencer::class, $result);
        $row = $result->first();
        $this->assertIsArray($row);
        $this->assertSame('Widget', $row['name']);
    }

    public function testCamelCaseNamedArgsConvertToSnakeCase(): void
    {
        // Arrange
        $this->writeSql(
            'InsertProduct.sql',
            'INSERT INTO products (name, price) VALUES (:name, :price)',
        );
        $this->writeSql(
            'ProductsByMinPrice.sql',
            'SELECT name FROM products WHERE price >= :min_price',
        );
        $model = $this->model();

        // Act
        $model->insertProduct(name: 'Cheap', price: 5.00);
        $model->insertProduct(name: 'Expensive', price: 50.00);
        $result = $model->productsByMinPrice(minPrice: 10.00);

        // Assert
        $this->assertInstanceOf(Sequencer::class, $result);
        $rows = $result->toSeries()->all();
        $this->assertCount(1, $rows);
        $this->assertIsArray($rows[0]);
        $this->assertSame('Expensive', $rows[0]['name']);
    }

    // ── Calling convention: positional args ──────────────────────

    public function testPositionalArgsFillBindingsInOrder(): void
    {
        // Arrange
        $this->writeSql(
            'InsertProduct.sql',
            'INSERT INTO products (name, price) VALUES (:name, :price)',
        );
        $model = $this->model();

        // Act — positional: 'Widget' → :name, 9.99 → :price
        $result = $model->insertProduct('Widget', 9.99);

        // Assert
        $this->assertInstanceOf(WriteResult::class, $result);
        $this->assertSame('1', $result->lastInsertId());
    }

    // ── Calling convention: mixed args ───────────────────────────

    public function testMixedPositionalAndNamedArgs(): void
    {
        // Arrange
        $this->writeSql(
            'InsertProduct.sql',
            'INSERT INTO products (name, price) VALUES (:name, :price)',
        );
        $this->writeSql(
            'SearchProducts.sql',
            'SELECT name FROM products'
            . ' WHERE name = :name AND price >= :min_price'
            . ' AND active = :active',
        );
        $model = $this->model();

        $model->insertProduct(name: 'Widget', price: 9.99);
        $this->connection->execute(
            'UPDATE products SET active = 1 WHERE name = :name',
            ['name' => 'Widget'],
        );

        // Act — 'Widget' and 0 fill positionally (:name, :min_price), active is named
        $result = $model->searchProducts('Widget', 0, active: 1);

        // Assert
        $this->assertInstanceOf(Sequencer::class, $result);
        $this->assertSame(1, $result->toSeries()->count());
    }

    // ── Read/write connection routing ────────────────────────────

    public function testSelectUsesReadConnection(): void
    {
        // Arrange
        $splitManager = new ConnectionManager(
            defaultConnection: 'main',
            connections: [
                'main' => ['driver' => 'sqlite', 'database' => ':memory:'],
                'read_replica' => ['driver' => 'sqlite', 'database' => ':memory:'],
                'write_primary' => ['driver' => 'sqlite', 'database' => ':memory:'],
            ],
            factory: new ConnectionFactory(),
            readConnection: 'read_replica',
            writeConnection: 'write_primary',
        );

        /** @var PdoConnection $readConn */
        $readConn = $splitManager->readConnection();
        $this->createProductsTable($readConn);
        $readConn->execute(
            'INSERT INTO products (name) VALUES (:name)',
            ['name' => 'ReadOnly'],
        );

        /** @var PdoConnection $writeConn */
        $writeConn = $splitManager->writeConnection();
        $this->createProductsTable($writeConn);

        $this->writeSql('AllProducts.sql', 'SELECT name FROM products');
        $model = $this->model(manager: $splitManager);

        // Act
        $result = $model->allProducts();

        // Assert
        $this->assertInstanceOf(Sequencer::class, $result);
        $rows = $result->toSeries()->all();
        $this->assertCount(1, $rows);
        $this->assertIsArray($rows[0]);
        $this->assertSame('ReadOnly', $rows[0]['name']);
    }

    public function testInsertUsesWriteConnection(): void
    {
        // Arrange
        $splitManager = new ConnectionManager(
            defaultConnection: 'main',
            connections: [
                'main' => ['driver' => 'sqlite', 'database' => ':memory:'],
                'read_replica' => ['driver' => 'sqlite', 'database' => ':memory:'],
                'write_primary' => ['driver' => 'sqlite', 'database' => ':memory:'],
            ],
            factory: new ConnectionFactory(),
            readConnection: 'read_replica',
            writeConnection: 'write_primary',
        );

        /** @var PdoConnection $readConn */
        $readConn = $splitManager->readConnection();
        $this->createProductsTable($readConn);

        /** @var PdoConnection $writeConn */
        $writeConn = $splitManager->writeConnection();
        $this->createProductsTable($writeConn);

        $this->writeSql(
            'InsertProduct.sql',
            'INSERT INTO products (name) VALUES (:name)',
        );
        $model = $this->model(manager: $splitManager);

        // Act
        $model->insertProduct(name: 'WriteOnly');

        // Assert
        $readRow = $readConn->query('SELECT count(*) as cnt FROM products')->first();
        $this->assertNotNull($readRow);
        $this->assertSame(0, $readRow['cnt']);

        $writeRow = $writeConn->query('SELECT count(*) as cnt FROM products')->first();
        $this->assertNotNull($writeRow);
        $this->assertSame(1, $writeRow['cnt']);
    }

    // ── @cast annotations ────────────────────────────────────────

    public function testCastInt(): void
    {
        // Arrange
        $this->connection->execute(
            'INSERT INTO products (name, quantity) VALUES (:name, :quantity)',
            ['name' => 'Widget', 'quantity' => 5],
        );
        $this->writeSql(
            'ProductQuantities.sql',
            "-- @cast quantity int\nSELECT name, quantity FROM products",
        );
        $model = $this->model();

        // Act
        $result = $model->productQuantities();

        // Assert
        $this->assertInstanceOf(Sequencer::class, $result);
        $row = $result->first();
        $this->assertIsArray($row);
        $this->assertSame(5, $row['quantity']);
    }

    public function testCastFloat(): void
    {
        // Arrange
        $this->connection->execute(
            'INSERT INTO products (name, price) VALUES (:name, :price)',
            ['name' => 'Widget', 'price' => 19.99],
        );
        $this->writeSql(
            'ProductPrices.sql',
            "-- @cast price float\nSELECT name, price FROM products",
        );
        $model = $this->model();

        // Act
        $result = $model->productPrices();

        // Assert
        $this->assertInstanceOf(Sequencer::class, $result);
        $row = $result->first();
        $this->assertIsArray($row);
        $this->assertSame(19.99, $row['price']);
    }

    public function testCastBoolTruthyValues(): void
    {
        // Arrange
        $this->connection->execute(
            'INSERT INTO products (name, active) VALUES (:name, :active)',
            ['name' => 'Active', 'active' => 1],
        );
        $this->connection->execute(
            'INSERT INTO products (name, active) VALUES (:name, :active)',
            ['name' => 'Inactive', 'active' => 0],
        );
        $this->writeSql(
            'ProductStatus.sql',
            "-- @cast active bool\n"
            . "SELECT name, active FROM products ORDER BY name",
        );
        $model = $this->model();

        // Act
        $result = $model->productStatus();

        // Assert
        $this->assertInstanceOf(Sequencer::class, $result);
        $rows = $result->toSeries()->all();
        $this->assertIsArray($rows[0]);
        $this->assertIsArray($rows[1]);
        $this->assertTrue($rows[0]['active']);
        $this->assertFalse($rows[1]['active']);
    }

    public function testCastJson(): void
    {
        // Arrange
        $this->connection->execute(
            'INSERT INTO products (name, metadata) VALUES (:name, :metadata)',
            ['name' => 'Widget', 'metadata' => '{"color":"blue","weight":1.5}'],
        );
        $this->writeSql(
            'ProductMeta.sql',
            "-- @cast metadata json\nSELECT name, metadata FROM products",
        );
        $model = $this->model();

        // Act
        $result = $model->productMeta();

        // Assert
        $this->assertInstanceOf(Sequencer::class, $result);
        $row = $result->first();
        $this->assertIsArray($row);
        $this->assertSame(
            ['color' => 'blue', 'weight' => 1.5],
            $row['metadata'],
        );
    }

    public function testNoCastReturnsRawPdoValues(): void
    {
        // Arrange
        $this->connection->execute(
            'INSERT INTO products (name, quantity) VALUES (:name, :quantity)',
            ['name' => 'Widget', 'quantity' => 5],
        );
        $this->writeSql(
            'RawProducts.sql',
            'SELECT name, quantity FROM products',
        );
        $model = $this->model();

        // Act
        $result = $model->rawProducts();

        // Assert
        $this->assertInstanceOf(Sequencer::class, $result);
        $row = $result->first();
        $this->assertIsArray($row);
        $this->assertIsInt($row['quantity']);
    }

    public function testMultipleCastAnnotations(): void
    {
        // Arrange
        $this->connection->execute(
            'INSERT INTO products (name, price, quantity, active) '
            . 'VALUES (:name, :price, :quantity, :active)',
            ['name' => 'Widget', 'price' => 9.99, 'quantity' => 3, 'active' => 1],
        );
        $this->writeSql(
            'FullProducts.sql',
            "-- @cast price float\n-- @cast quantity int\n"
            . "-- @cast active bool\n"
            . "SELECT name, price, quantity, active FROM products",
        );
        $model = $this->model();

        // Act
        $result = $model->fullProducts();

        // Assert
        $this->assertInstanceOf(Sequencer::class, $result);
        $row = $result->first();
        $this->assertIsArray($row);
        $this->assertSame(9.99, $row['price']);
        $this->assertSame(3, $row['quantity']);
        $this->assertTrue($row['active']);
    }

    public function testCastOnWriteQueryIgnored(): void
    {
        // Arrange
        $this->writeSql(
            'InsertProduct.sql',
            "-- @cast name int\nINSERT INTO products (name) VALUES (:name)",
        );
        $model = $this->model();

        // Act
        $result = $model->insertProduct(name: 'Widget');

        // Assert
        $this->assertInstanceOf(WriteResult::class, $result);
        $this->assertSame('1', $result->lastInsertId());
    }

    public function testRejectsPathTraversalInMethodName(): void
    {
        // Arrange
        $model = $this->model();

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid model method name");

        $model->{'../../etc/passwd'}();
    }

    public function testRejectsDotsInMethodName(): void
    {
        // Arrange
        $model = $this->model();

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);

        $model->{'some.file'}();
    }
}
