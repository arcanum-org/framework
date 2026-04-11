<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge\Migration;

use Arcanum\Forge\Migration\InvalidMigrationFile;
use Arcanum\Forge\Migration\MigrationFile;
use Arcanum\Forge\Migration\MigrationParser;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(MigrationParser::class)]
#[UsesClass(MigrationFile::class)]
#[UsesClass(InvalidMigrationFile::class)]
final class MigrationParserTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = __DIR__ . '/Fixture';
    }

    private function readFixture(string $filename): string
    {
        $contents = file_get_contents($this->fixtureDir . '/' . $filename);
        $this->assertIsString($contents);

        return $contents;
    }

    public function testParsesValidMigration(): void
    {
        // Arrange
        $parser = new MigrationParser();
        $path = $this->fixtureDir . '/20260409120000_create_users.sql';
        $contents = $this->readFixture('20260409120000_create_users.sql');

        // Act
        $result = $parser->parse($path, $contents);

        // Assert
        $this->assertSame('20260409120000', $result->version);
        $this->assertSame('create_users', $result->name);
        $this->assertSame('20260409120000_create_users.sql', $result->filename);
        $this->assertStringContainsString('CREATE TABLE users', $result->upSql);
        $this->assertStringContainsString('DROP TABLE users', $result->downSql);
        $this->assertTrue($result->transactional);
        $this->assertSame(md5($contents), $result->checksum);
    }

    public function testParsesTransactionOff(): void
    {
        // Arrange
        $parser = new MigrationParser();
        $path = $this->fixtureDir . '/20260409130000_no_transaction.sql';
        $contents = $this->readFixture(basename($path));

        // Act
        $result = $parser->parse($path, $contents);

        // Assert
        $this->assertFalse($result->transactional);
        $this->assertStringContainsString('CREATE INDEX', $result->upSql);
        $this->assertStringNotContainsString('@transaction', $result->upSql);
        $this->assertStringContainsString('DROP INDEX', $result->downSql);
        $this->assertStringNotContainsString('@transaction', $result->downSql);
    }

    public function testThrowsOnBadFilename(): void
    {
        // Arrange
        $parser = new MigrationParser();
        $path = $this->fixtureDir . '/bad_name.sql';
        $contents = $this->readFixture(basename($path));

        // Act & Assert
        $this->expectException(InvalidMigrationFile::class);
        $this->expectExceptionMessage('does not match the expected format');
        $parser->parse($path, $contents);
    }

    public function testThrowsOnMissingMarkers(): void
    {
        // Arrange
        $parser = new MigrationParser();
        $path = $this->fixtureDir . '/20260409140000_no_markers.sql';
        $contents = $this->readFixture(basename($path));

        // Act & Assert
        $this->expectException(InvalidMigrationFile::class);
        $this->expectExceptionMessage('missing the "-- @migrate up" marker');
        $parser->parse($path, $contents);
    }

    public function testThrowsOnMissingDownMarker(): void
    {
        // Arrange
        $parser = new MigrationParser();
        $path = $this->fixtureDir . '/20260409150000_missing_down.sql';
        $contents = $this->readFixture(basename($path));

        // Act & Assert
        $this->expectException(InvalidMigrationFile::class);
        $this->expectExceptionMessage('missing the "-- @migrate down" marker');
        $parser->parse($path, $contents);
    }

    public function testChecksumIsConsistent(): void
    {
        // Arrange
        $parser = new MigrationParser();
        $path = $this->fixtureDir . '/20260409120000_create_users.sql';
        $contents = $this->readFixture(basename($path));

        // Act
        $first = $parser->parse($path, $contents);
        $second = $parser->parse($path, $contents);

        // Assert
        $this->assertSame($first->checksum, $second->checksum);
    }

    public function testChecksumChangesWhenContentsChange(): void
    {
        // Arrange
        $parser = new MigrationParser();
        $path = '/tmp/20260409160000_test.sql';
        $original = "-- @migrate up\nSELECT 1;\n\n-- @migrate down\nSELECT 2;\n";
        $modified = "-- @migrate up\nSELECT 99;\n\n-- @migrate down\nSELECT 2;\n";

        // Act
        $a = $parser->parse($path, $original);
        $b = $parser->parse($path, $modified);

        // Assert
        $this->assertNotSame($a->checksum, $b->checksum);
    }

    public function testEmptyDownSqlIsAllowed(): void
    {
        // Arrange
        $parser = new MigrationParser();
        $path = '/tmp/20260409170000_seed_data.sql';
        $contents = "-- @migrate up\nINSERT INTO config (key, value) VALUES ('ver', '1');\n\n-- @migrate down\n";

        // Act
        $result = $parser->parse($path, $contents);

        // Assert
        $this->assertSame('', $result->downSql);
    }
}
