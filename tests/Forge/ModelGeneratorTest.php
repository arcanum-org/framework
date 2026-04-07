<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge;

use Arcanum\Forge\ModelGenerator;
use Arcanum\Forge\Sql;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Writer;
use Arcanum\Toolkit\Strings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(ModelGenerator::class)]
#[UsesClass(Sql::class)]
#[UsesClass(Strings::class)]
#[UsesClass(Reader::class)]
#[UsesClass(Writer::class)]
#[UsesClass(\Arcanum\Parchment\FileSystem::class)]
#[UsesClass(\Arcanum\Shodo\TemplateCompiler::class)]
final class ModelGeneratorTest extends TestCase
{
    private string $modelDir;

    protected function setUp(): void
    {
        $this->modelDir = sys_get_temp_dir() . '/arcanum_gen_test_' . uniqid();
        mkdir($this->modelDir, 0777, true);
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

    private function writeSql(string $filename, string $sql): void
    {
        file_put_contents($this->modelDir . '/' . $filename, $sql);
    }

    public function testGeneratesClassWithMethods(): void
    {
        // Arrange
        $this->writeSql('AllProducts.sql', 'SELECT * FROM products');
        $this->writeSql(
            'InsertProduct.sql',
            'INSERT INTO products (name) VALUES (:name)',
        );
        $generator = new ModelGenerator();

        // Act
        $source = $generator->generate(
            $this->modelDir,
            'App\\Domain\\Shop\\Model\\Model',
        );

        // Assert
        $this->assertStringContainsString('namespace App\\Domain\\Shop\\Model;', $source);
        $this->assertStringContainsString('class Model extends BaseModel', $source);
        $this->assertStringNotContainsString('function __construct(', $source);
        $this->assertStringContainsString('function allProducts(', $source);
        $this->assertStringContainsString('function insertProduct(', $source);
    }

    public function testMethodHasTypedParamsFromAnnotations(): void
    {
        // Arrange
        $this->writeSql(
            'Search.sql',
            "-- @param category string\n-- @param min_price float\n"
            . "SELECT * FROM products"
            . " WHERE category = :category AND price >= :min_price",
        );
        $generator = new ModelGenerator();

        // Act
        $source = $generator->generate(
            $this->modelDir,
            'App\\Domain\\Shop\\Model\\Model',
        );

        // Assert
        $this->assertStringContainsString(
            'function search(string $category, float $minPrice)',
            $source,
        );
    }

    public function testMethodDefaultsToStringWithoutAnnotations(): void
    {
        // Arrange
        $this->writeSql(
            'GetById.sql',
            'SELECT * FROM products WHERE id = :id',
        );
        $generator = new ModelGenerator();

        // Act
        $source = $generator->generate(
            $this->modelDir,
            'App\\Domain\\Shop\\Model\\Model',
        );

        // Assert
        $this->assertStringContainsString(
            'function getById(string $id)',
            $source,
        );
    }

    public function testMethodPassesSqlPathToExecute(): void
    {
        // Arrange
        $this->writeSql(
            'GetById.sql',
            'SELECT * FROM products WHERE id = :id',
        );
        $generator = new ModelGenerator();

        // Act
        $source = $generator->generate(
            $this->modelDir,
            'App\\Domain\\Shop\\Model\\Model',
        );

        // Assert
        $this->assertStringContainsString(
            "__DIR__ . '/GetById.sql'",
            $source,
        );
        $this->assertStringContainsString("'id' => \$id", $source);
    }

    public function testParameterlessSqlGeneratesNoParams(): void
    {
        // Arrange
        $this->writeSql('CountAll.sql', 'SELECT count(*) FROM products');
        $generator = new ModelGenerator();

        // Act
        $source = $generator->generate(
            $this->modelDir,
            'App\\Domain\\Shop\\Model\\Model',
        );

        // Assert
        $this->assertStringContainsString('function countAll(): Sequencer', $source);
        $this->assertStringContainsString("__DIR__ . '/CountAll.sql', []", $source);
    }

    // ── Read/write method shape ─────────────────────────────────

    public function testReadMethodReturnsSequencerAndCallsRead(): void
    {
        // Arrange
        $this->writeSql('AllProducts.sql', 'SELECT * FROM products');
        $generator = new ModelGenerator();

        // Act
        $source = $generator->generate(
            $this->modelDir,
            'App\\Domain\\Shop\\Model\\Model',
        );

        // Assert
        $this->assertStringContainsString(
            '@return Sequencer<array<string, mixed>>',
            $source,
        );
        $this->assertStringContainsString(
            'function allProducts(): Sequencer',
            $source,
        );
        $this->assertStringContainsString(
            "return \$this->read(__DIR__ . '/AllProducts.sql'",
            $source,
        );
    }

    public function testWriteMethodReturnsWriteResultAndCallsWrite(): void
    {
        // Arrange
        $this->writeSql(
            'InsertProduct.sql',
            'INSERT INTO products (name) VALUES (:name)',
        );
        $generator = new ModelGenerator();

        // Act
        $source = $generator->generate(
            $this->modelDir,
            'App\\Domain\\Shop\\Model\\Model',
        );

        // Assert
        $this->assertStringContainsString(
            'function insertProduct(string $name): WriteResult',
            $source,
        );
        $this->assertStringContainsString(
            "return \$this->write(__DIR__ . '/InsertProduct.sql'",
            $source,
        );
    }

    public function testReadOnlyModelOmitsWriteResultImport(): void
    {
        // Arrange
        $this->writeSql('AllProducts.sql', 'SELECT * FROM products');
        $this->writeSql('GetById.sql', 'SELECT * FROM products WHERE id = :id');
        $generator = new ModelGenerator();

        // Act
        $source = $generator->generate(
            $this->modelDir,
            'App\\Domain\\Shop\\Model\\Model',
        );

        // Assert
        $this->assertStringContainsString('use Arcanum\\Flow\\Sequence\\Sequencer;', $source);
        $this->assertStringNotContainsString('use Arcanum\\Forge\\WriteResult;', $source);
    }

    public function testWriteOnlyModelOmitsSequencerImport(): void
    {
        // Arrange
        $this->writeSql(
            'InsertProduct.sql',
            'INSERT INTO products (name) VALUES (:name)',
        );
        $this->writeSql(
            'DeleteProduct.sql',
            'DELETE FROM products WHERE id = :id',
        );
        $generator = new ModelGenerator();

        // Act
        $source = $generator->generate(
            $this->modelDir,
            'App\\Domain\\Shop\\Model\\Model',
        );

        // Assert
        $this->assertStringContainsString('use Arcanum\\Forge\\WriteResult;', $source);
        $this->assertStringNotContainsString('use Arcanum\\Flow\\Sequence\\Sequencer;', $source);
    }

    public function testMixedModelImportsBoth(): void
    {
        // Arrange
        $this->writeSql('AllProducts.sql', 'SELECT * FROM products');
        $this->writeSql(
            'InsertProduct.sql',
            'INSERT INTO products (name) VALUES (:name)',
        );
        $generator = new ModelGenerator();

        // Act
        $source = $generator->generate(
            $this->modelDir,
            'App\\Domain\\Shop\\Model\\Model',
        );

        // Assert
        $this->assertStringContainsString('use Arcanum\\Flow\\Sequence\\Sequencer;', $source);
        $this->assertStringContainsString('use Arcanum\\Forge\\WriteResult;', $source);
        $this->assertStringContainsString('function allProducts(): Sequencer', $source);
        $this->assertStringContainsString('function insertProduct(string $name): WriteResult', $source);
    }

    public function testGenerateAndWriteCreatesFile(): void
    {
        // Arrange
        $this->writeSql('AllProducts.sql', 'SELECT * FROM products');
        $outputPath = $this->modelDir . '/Model.php';
        $generator = new ModelGenerator();

        // Act
        $result = $generator->generateAndWrite(
            $this->modelDir,
            'App\\Domain\\Shop\\Model\\Model',
            $outputPath,
        );

        // Assert
        $this->assertTrue($result);
        $this->assertFileExists($outputPath);
        $this->assertStringContainsString(
            'class Model extends BaseModel',
            file_get_contents($outputPath) ?: '',
        );
    }

    public function testGenerateAndWriteReturnsFalseForEmptyDir(): void
    {
        // Arrange — no SQL files
        $generator = new ModelGenerator();

        // Act
        $result = $generator->generateAndWrite(
            $this->modelDir,
            'App\\Domain\\Shop\\Model\\Model',
            $this->modelDir . '/Model.php',
        );

        // Assert
        $this->assertFalse($result);
    }

    public function testIsStaleReturnsTrueWhenNoClassFile(): void
    {
        // Arrange
        $this->writeSql('Test.sql', 'SELECT 1');
        $generator = new ModelGenerator();

        // Act & Assert
        $this->assertTrue(
            $generator->isStale($this->modelDir, $this->modelDir . '/Model.php'),
        );
    }

    public function testIsStaleReturnsFalseWhenClassIsNewer(): void
    {
        // Arrange
        $this->writeSql('Test.sql', 'SELECT 1');
        // Write class file after SQL
        sleep(1);
        file_put_contents($this->modelDir . '/Model.php', '<?php // generated');
        $generator = new ModelGenerator();

        // Act & Assert
        $this->assertFalse(
            $generator->isStale($this->modelDir, $this->modelDir . '/Model.php'),
        );
    }

    public function testSnakeCaseParamsConvertToCamelCase(): void
    {
        // Arrange
        $this->writeSql(
            'Search.sql',
            "SELECT * FROM products"
            . " WHERE min_price = :min_price AND max_price = :max_price",
        );
        $generator = new ModelGenerator();

        // Act
        $source = $generator->generate(
            $this->modelDir,
            'App\\Domain\\Shop\\Model\\Model',
        );

        // Assert
        $this->assertStringContainsString('$minPrice', $source);
        $this->assertStringContainsString('$maxPrice', $source);
        $this->assertStringContainsString("'min_price' => \$minPrice", $source);
        $this->assertStringContainsString("'max_price' => \$maxPrice", $source);
    }

    public function testGeneratedFileHasAutoGeneratedComment(): void
    {
        // Arrange
        $this->writeSql('Test.sql', 'SELECT 1');
        $generator = new ModelGenerator();

        // Act
        $source = $generator->generate(
            $this->modelDir,
            'App\\Domain\\Shop\\Model\\Model',
        );

        // Assert
        $this->assertStringContainsString(
            'auto-generated by forge:models',
            $source,
        );
    }

    // ── Sub-model discovery ─────────────────────────────────────

    public function testDiscoverSubModelDirsFindsDirectoriesWithSql(): void
    {
        // Arrange
        mkdir($this->modelDir . '/Products', 0777, true);
        mkdir($this->modelDir . '/Orders', 0777, true);
        mkdir($this->modelDir . '/EmptyDir', 0777, true);
        file_put_contents($this->modelDir . '/Products/FindAll.sql', 'SELECT * FROM products');
        file_put_contents($this->modelDir . '/Orders/Create.sql', 'INSERT INTO orders (total) VALUES (:total)');
        $generator = new ModelGenerator();

        // Act
        $dirs = $generator->discoverSubModelDirs($this->modelDir);

        // Assert
        $this->assertCount(2, $dirs);
        $this->assertArrayHasKey('Orders', $dirs);
        $this->assertArrayHasKey('Products', $dirs);
        $this->assertArrayNotHasKey('EmptyDir', $dirs);
    }

    public function testDiscoverSubModelDirsReturnsEmptyForFlatDir(): void
    {
        // Arrange
        $this->writeSql('AllProducts.sql', 'SELECT * FROM products');
        $generator = new ModelGenerator();

        // Act
        $dirs = $generator->discoverSubModelDirs($this->modelDir);

        // Assert
        $this->assertSame([], $dirs);
    }

    public function testDiscoverSubModelDirsReturnsEmptyForMissingDir(): void
    {
        // Arrange
        $generator = new ModelGenerator();

        // Act
        $dirs = $generator->discoverSubModelDirs('/nonexistent/path');

        // Assert
        $this->assertSame([], $dirs);
    }

    // ── Sub-model generation ────────────────────────────────────

    public function testGenerateSubModelHasNoConstructor(): void
    {
        // Arrange
        $subDir = $this->modelDir . '/Products';
        mkdir($subDir, 0777, true);
        file_put_contents($subDir . '/FindAll.sql', 'SELECT * FROM products');
        file_put_contents(
            $subDir . '/FindById.sql',
            "-- @param id int\nSELECT * FROM products WHERE id = :id",
        );
        $generator = new ModelGenerator();

        // Act
        $source = $generator->generate(
            $subDir,
            'App\\Domain\\Shop\\Model\\Products\\Products',
        );

        // Assert
        $this->assertStringContainsString('namespace App\\Domain\\Shop\\Model\\Products;', $source);
        $this->assertStringContainsString('class Products extends BaseModel', $source);
        $this->assertStringNotContainsString('function __construct(', $source);
        $this->assertStringContainsString('function findAll(', $source);
        $this->assertStringContainsString('function findById(int $id)', $source);
        $this->assertStringContainsString("__DIR__ . '/FindAll.sql'", $source);
        $this->assertStringContainsString("__DIR__ . '/FindById.sql'", $source);
    }

    public function testGenerateAndWriteSubModelCreatesFile(): void
    {
        // Arrange
        $subDir = $this->modelDir . '/Products';
        mkdir($subDir, 0777, true);
        file_put_contents($subDir . '/FindAll.sql', 'SELECT * FROM products');
        $outputPath = $subDir . '/Products.php';
        $generator = new ModelGenerator();

        // Act
        $result = $generator->generateAndWrite(
            $subDir,
            'App\\Domain\\Shop\\Model\\Products\\Products',
            $outputPath,
        );

        // Assert
        $this->assertTrue($result);
        $this->assertFileExists($outputPath);
        $this->assertStringContainsString(
            'class Products extends BaseModel',
            file_get_contents($outputPath) ?: '',
        );
    }

    public function testGenerateAndWriteSubModelReturnsFalseForEmptyDir(): void
    {
        // Arrange
        $subDir = $this->modelDir . '/Products';
        mkdir($subDir, 0777, true);
        $generator = new ModelGenerator();

        // Act
        $result = $generator->generateAndWrite(
            $subDir,
            'App\\Domain\\Shop\\Model\\Products\\Products',
            $subDir . '/Products.php',
        );

        // Assert
        $this->assertFalse($result);
    }

    // ── Mixed structure ─────────────────────────────────────────

    public function testRootModelExcludesSubdirectorySqlFiles(): void
    {
        // Arrange — root SQL + subdirectory SQL
        $this->writeSql('GetCart.sql', 'SELECT * FROM cart');
        mkdir($this->modelDir . '/Products', 0777, true);
        file_put_contents($this->modelDir . '/Products/FindAll.sql', 'SELECT * FROM products');
        $generator = new ModelGenerator();

        // Act
        $source = $generator->generate(
            $this->modelDir,
            'App\\Domain\\Shop\\Model\\Model',
        );

        // Assert — root Model has getCart but NOT findAll
        $this->assertStringContainsString('function getCart(', $source);
        $this->assertStringNotContainsString('function findAll(', $source);
    }

    public function testIsStaleWorksForSubModelDirectory(): void
    {
        // Arrange
        $subDir = $this->modelDir . '/Products';
        mkdir($subDir, 0777, true);
        file_put_contents($subDir . '/FindAll.sql', 'SELECT * FROM products');
        $generator = new ModelGenerator();

        // Act & Assert — no class file yet, should be stale
        $this->assertTrue(
            $generator->isStale($subDir, $subDir . '/Products.php'),
        );

        // Write class file after SQL
        sleep(1);
        file_put_contents($subDir . '/Products.php', '<?php // generated');

        // Now it should not be stale
        $this->assertFalse(
            $generator->isStale($subDir, $subDir . '/Products.php'),
        );
    }
}
