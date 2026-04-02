<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge;

use Arcanum\Forge\Connection;
use Arcanum\Forge\Model;
use Arcanum\Forge\Result;
use Arcanum\Forge\Sql;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Model::class)]
#[UsesClass(Connection::class)]
#[UsesClass(Result::class)]
#[UsesClass(Sql::class)]
final class ModelTest extends TestCase
{
    private string $modelDir;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->modelDir = sys_get_temp_dir() . '/arcanum_model_test_' . uniqid();
        mkdir($this->modelDir, 0777, true);

        $this->connection = new Connection(dsn: 'sqlite::memory:');
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

    private function createProductsTable(Connection $conn): void
    {
        $conn->run(
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

    private function model(?Connection $read = null, ?Connection $write = null): Model
    {
        return new Model(
            directory: $this->modelDir,
            readConnection: $read ?? $this->connection,
            writeConnection: $write ?? $this->connection,
        );
    }

    public function testMethodCallResolvesToSqlFile(): void
    {
        // Arrange
        $this->writeSql('AllProducts.sql', 'SELECT id, name FROM products');
        $model = $this->model();

        // Act
        $result = $model->allProducts();

        // Assert
        $this->assertInstanceOf(Result::class, $result);
    }

    public function testCamelCaseToPascalCaseConversion(): void
    {
        // Arrange — method name "insertProduct" should resolve to "InsertProduct.sql"
        $this->writeSql('InsertProduct.sql', 'INSERT INTO products (name) VALUES (:name)');
        $model = $this->model();

        // Act
        $result = $model->insertProduct(['name' => 'Widget']);

        // Assert
        $this->assertSame('1', $result->lastInsertId());
    }

    public function testParametersBoundAsNamed(): void
    {
        // Arrange
        $this->writeSql('InsertProduct.sql', 'INSERT INTO products (name, price) VALUES (:name, :price)');
        $this->writeSql('ProductByName.sql', 'SELECT name, price FROM products WHERE name = :name');
        $model = $this->model();

        // Act
        $model->insertProduct(['name' => 'Widget', 'price' => 9.99]);
        $result = $model->productByName(['name' => 'Widget']);

        // Assert
        $this->assertNotNull($result->first());
        $this->assertSame('Widget', $result->first()['name']);
    }

    public function testSelectUsesReadConnection(): void
    {
        // Arrange — separate read and write connections
        $readConn = new Connection(dsn: 'sqlite::memory:');
        $writeConn = new Connection(dsn: 'sqlite::memory:');
        $this->createProductsTable($readConn);
        $readConn->run('INSERT INTO products (name) VALUES (:name)', ['name' => 'ReadOnly']);
        $this->createProductsTable($writeConn);

        $this->writeSql('AllProducts.sql', 'SELECT name FROM products');
        $model = $this->model(read: $readConn, write: $writeConn);

        // Act
        $result = $model->allProducts();

        // Assert — should read from read connection which has data
        $this->assertSame(1, $result->count());
        $this->assertNotNull($result->first());
        $this->assertSame('ReadOnly', $result->first()['name']);
    }

    public function testInsertUsesWriteConnection(): void
    {
        // Arrange — separate read and write connections
        $readConn = new Connection(dsn: 'sqlite::memory:');
        $writeConn = new Connection(dsn: 'sqlite::memory:');
        $this->createProductsTable($readConn);
        $this->createProductsTable($writeConn);

        $this->writeSql('InsertProduct.sql', 'INSERT INTO products (name) VALUES (:name)');
        $this->writeSql('AllProducts.sql', 'SELECT name FROM products');
        $model = $this->model(read: $readConn, write: $writeConn);

        // Act
        $model->insertProduct(['name' => 'WriteOnly']);

        // Assert — read connection should be empty, write should have the row
        $readResult = $readConn->run('SELECT count(*) as cnt FROM products');
        $this->assertNotNull($readResult->first());
        $this->assertSame(0, $readResult->first()['cnt']);

        $writeResult = $writeConn->run('SELECT count(*) as cnt FROM products');
        $this->assertNotNull($writeResult->first());
        $this->assertSame(1, $writeResult->first()['cnt']);
    }

    public function testMissingFileThrowsWithHelpfulMessage(): void
    {
        // Arrange
        $model = $this->model();

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Model method 'findUser' not found — expected file:");
        $this->expectExceptionMessage('FindUser.sql');
        $model->findUser();
    }

    public function testSqlFileContentsCached(): void
    {
        // Arrange
        $this->writeSql('AllProducts.sql', 'SELECT id, name FROM products');
        $model = $this->model();

        // Act — call twice
        $model->allProducts();

        // Delete the file between calls
        unlink($this->modelDir . '/AllProducts.sql');

        // Second call should use cached content
        $result = $model->allProducts();

        // Assert
        $this->assertInstanceOf(Result::class, $result);
    }

    public function testParameterlessSqlWorks(): void
    {
        // Arrange
        $this->connection->run('INSERT INTO products (name) VALUES (:name)', ['name' => 'Widget']);
        $this->writeSql('CountProducts.sql', 'SELECT count(*) as cnt FROM products');
        $model = $this->model();

        // Act
        $result = $model->countProducts();

        // Assert
        $this->assertSame(1, $result->scalar());
    }

    public function testCastInt(): void
    {
        // Arrange
        $this->connection->run('INSERT INTO products (name, quantity) VALUES (:name, :quantity)', [
            'name' => 'Widget',
            'quantity' => 5,
        ]);
        $this->writeSql('ProductQuantities.sql', "-- @cast quantity int\nSELECT name, quantity FROM products");
        $model = $this->model();

        // Act
        $result = $model->productQuantities();

        // Assert — SQLite returns strings by default, @cast should convert
        $this->assertNotNull($result->first());
        $this->assertSame(5, $result->first()['quantity']);
    }

    public function testCastFloat(): void
    {
        // Arrange
        $this->connection->run('INSERT INTO products (name, price) VALUES (:name, :price)', [
            'name' => 'Widget',
            'price' => 19.99,
        ]);
        $this->writeSql('ProductPrices.sql', "-- @cast price float\nSELECT name, price FROM products");
        $model = $this->model();

        // Act
        $result = $model->productPrices();

        // Assert
        $this->assertNotNull($result->first());
        $this->assertSame(19.99, $result->first()['price']);
    }

    public function testCastBoolTruthyValues(): void
    {
        // Arrange
        $this->connection->run('INSERT INTO products (name, active) VALUES (:name, :active)', [
            'name' => 'Active',
            'active' => 1,
        ]);
        $this->connection->run('INSERT INTO products (name, active) VALUES (:name, :active)', [
            'name' => 'Inactive',
            'active' => 0,
        ]);
        $this->writeSql('ProductStatus.sql', "-- @cast active bool\nSELECT name, active FROM products ORDER BY name");
        $model = $this->model();

        // Act
        $result = $model->productStatus();
        $rows = $result->rows();

        // Assert
        $this->assertTrue($rows[0]['active']);   // "Active" has active=1
        $this->assertFalse($rows[1]['active']);   // "Inactive" has active=0
    }

    public function testCastJson(): void
    {
        // Arrange
        $this->connection->run('INSERT INTO products (name, metadata) VALUES (:name, :metadata)', [
            'name' => 'Widget',
            'metadata' => '{"color":"blue","weight":1.5}',
        ]);
        $this->writeSql('ProductMeta.sql', "-- @cast metadata json\nSELECT name, metadata FROM products");
        $model = $this->model();

        // Act
        $result = $model->productMeta();

        // Assert
        $this->assertNotNull($result->first());
        $this->assertSame(['color' => 'blue', 'weight' => 1.5], $result->first()['metadata']);
    }

    public function testNoCastReturnsRawPdoValues(): void
    {
        // Arrange
        $this->connection->run('INSERT INTO products (name, quantity) VALUES (:name, :quantity)', [
            'name' => 'Widget',
            'quantity' => 5,
        ]);
        $this->writeSql('RawProducts.sql', 'SELECT name, quantity FROM products');
        $model = $this->model();

        // Act
        $result = $model->rawProducts();

        // Assert — SQLite returns strings for integers when no @cast
        $this->assertNotNull($result->first());
        $this->assertIsInt($result->first()['quantity']);
    }

    public function testMultipleCastAnnotations(): void
    {
        // Arrange
        $sql = 'INSERT INTO products (name, price, quantity, active) '
            . 'VALUES (:name, :price, :quantity, :active)';
        $this->connection->run($sql, [
            'name' => 'Widget',
            'price' => 9.99,
            'quantity' => 3,
            'active' => 1,
        ]);
        $this->writeSql(
            'FullProducts.sql',
            "-- @cast price float\n-- @cast quantity int\n-- @cast active bool\n"
            . "SELECT name, price, quantity, active FROM products",
        );
        $model = $this->model();

        // Act
        $result = $model->fullProducts();

        // Assert
        $row = $result->first();
        $this->assertNotNull($row);
        $this->assertSame(9.99, $row['price']);
        $this->assertSame(3, $row['quantity']);
        $this->assertTrue($row['active']);
    }

    public function testCastOnWriteQueryIgnored(): void
    {
        // Arrange — @cast annotation on an INSERT should be ignored
        $this->writeSql('InsertProduct.sql', "-- @cast name int\nINSERT INTO products (name) VALUES (:name)");
        $model = $this->model();

        // Act
        $result = $model->insertProduct(['name' => 'Widget']);

        // Assert — returns a normal write result, no cast applied
        $this->assertSame('1', $result->lastInsertId());
    }
}
