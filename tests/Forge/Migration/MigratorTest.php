<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge\Migration;

use Arcanum\Forge\Migration\AppliedMigration;
use Arcanum\Forge\Migration\MigrationFile;
use Arcanum\Forge\Migration\MigrationParser;
use Arcanum\Forge\Migration\MigrationRepository;
use Arcanum\Forge\Migration\MigrationResult;
use Arcanum\Forge\Migration\MigrationStatus;
use Arcanum\Forge\Migration\Migrator;
use Arcanum\Forge\Migration\InvalidMigrationFile;
use Arcanum\Forge\Migration\MigrationFailed;
use Arcanum\Forge\PdoConnection;
use Arcanum\Forge\WriteResult;
use Arcanum\Flow\Sequence\Cursor;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Migrator::class)]
#[UsesClass(MigrationFile::class)]
#[UsesClass(AppliedMigration::class)]
#[UsesClass(MigrationParser::class)]
#[UsesClass(MigrationRepository::class)]
#[UsesClass(MigrationResult::class)]
#[UsesClass(MigrationStatus::class)]
#[UsesClass(MigrationFailed::class)]
#[UsesClass(InvalidMigrationFile::class)]
#[UsesClass(PdoConnection::class)]
#[UsesClass(WriteResult::class)]
#[UsesClass(Cursor::class)]
final class MigratorTest extends TestCase
{
    private string $migrationsDir;
    private PdoConnection $connection;

    protected function setUp(): void
    {
        $this->migrationsDir = sys_get_temp_dir() . '/arcanum_migrator_test_' . uniqid();
        mkdir($this->migrationsDir, 0755, true);
        $this->connection = new PdoConnection('sqlite::memory:');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->migrationsDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            unlink($dir . '/' . $item);
        }
        rmdir($dir);
    }

    private function createMigrator(): Migrator
    {
        $repo = new MigrationRepository($this->connection, 'sqlite');
        return new Migrator($this->connection, $repo, $this->migrationsDir);
    }

    private function writeMigration(string $filename, string $upSql, string $downSql = ''): void
    {
        $contents = "-- @migrate up\n{$upSql}\n\n-- @migrate down\n{$downSql}\n";
        file_put_contents($this->migrationsDir . '/' . $filename, $contents);
    }

    // ------------------------------------------------------------------
    // migrate
    // ------------------------------------------------------------------

    public function testMigrateRunsPendingMigrations(): void
    {
        // Arrange
        $this->writeMigration(
            '20260409120000_create_users.sql',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL);',
            'DROP TABLE users;',
        );
        $migrator = $this->createMigrator();

        // Act
        $result = $migrator->migrate();

        // Assert
        $this->assertFalse($result->hasErrors());
        $this->assertSame(['20260409120000_create_users.sql'], $result->ran);

        // Verify the table actually exists
        $rows = $this->connection->query('SELECT name FROM sqlite_master WHERE type = \'table\' AND name = \'users\'');
        $row = $rows->first();
        $this->assertNotNull($row);
        $this->assertSame('users', $row['name']);
    }

    public function testMigrateRunsInVersionOrder(): void
    {
        // Arrange — write files out of order
        $this->writeMigration(
            '20260409130000_create_posts.sql',
            'CREATE TABLE posts (id INTEGER PRIMARY KEY);',
            'DROP TABLE posts;',
        );
        $this->writeMigration(
            '20260409120000_create_users.sql',
            'CREATE TABLE users (id INTEGER PRIMARY KEY);',
            'DROP TABLE users;',
        );
        $migrator = $this->createMigrator();

        // Act
        $order = [];
        $migrator->migrate(function (MigrationFile $file) use (&$order): void {
            $order[] = $file->version;
        });

        // Assert — users before posts
        $this->assertSame(['20260409120000', '20260409130000'], $order);
    }

    public function testMigrateSkipsAlreadyApplied(): void
    {
        // Arrange
        $this->writeMigration(
            '20260409120000_create_users.sql',
            'CREATE TABLE users (id INTEGER PRIMARY KEY);',
            'DROP TABLE users;',
        );
        $this->writeMigration(
            '20260409130000_create_posts.sql',
            'CREATE TABLE posts (id INTEGER PRIMARY KEY);',
            'DROP TABLE posts;',
        );
        $migrator = $this->createMigrator();
        $migrator->migrate(); // apply both

        // Act — add a third, run again
        $this->writeMigration(
            '20260409140000_create_tags.sql',
            'CREATE TABLE tags (id INTEGER PRIMARY KEY);',
            'DROP TABLE tags;',
        );
        $result = $migrator->migrate();

        // Assert — only the new one ran
        $this->assertSame(['20260409140000_create_tags.sql'], $result->ran);
    }

    public function testMigrateReturnsEmptyWhenNothingPending(): void
    {
        // Arrange — no migration files
        $migrator = $this->createMigrator();

        // Act
        $result = $migrator->migrate();

        // Assert
        $this->assertFalse($result->hasErrors());
        $this->assertSame([], $result->ran);
    }

    public function testMigrateHaltsOnChecksumMismatch(): void
    {
        // Arrange — apply a migration, then modify the file
        $this->writeMigration(
            '20260409120000_create_users.sql',
            'CREATE TABLE users (id INTEGER PRIMARY KEY);',
            'DROP TABLE users;',
        );
        $migrator = $this->createMigrator();
        $migrator->migrate();

        // Modify the applied file
        $this->writeMigration(
            '20260409120000_create_users.sql',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT);',
            'DROP TABLE users;',
        );

        // Add a new pending migration
        $this->writeMigration(
            '20260409130000_create_posts.sql',
            'CREATE TABLE posts (id INTEGER PRIMARY KEY);',
            'DROP TABLE posts;',
        );

        // Act
        $result = $migrator->migrate();

        // Assert — halted with error, nothing ran
        $this->assertTrue($result->hasErrors());
        $this->assertSame([], $result->ran);
        $this->assertStringContainsString('has been modified', $result->errors[0]);
    }

    public function testMigrateStopsOnSqlError(): void
    {
        // Arrange
        $this->writeMigration(
            '20260409120000_good.sql',
            'CREATE TABLE good (id INTEGER PRIMARY KEY);',
            'DROP TABLE good;',
        );
        $this->writeMigration(
            '20260409130000_bad.sql',
            'INVALID SQL STATEMENT;',
            'SELECT 1;',
        );
        $this->writeMigration(
            '20260409140000_never.sql',
            'CREATE TABLE never (id INTEGER PRIMARY KEY);',
            'DROP TABLE never;',
        );
        $migrator = $this->createMigrator();

        // Act
        $result = $migrator->migrate();

        // Assert — first ran, second failed, third never ran
        $this->assertTrue($result->hasErrors());
        $this->assertSame(['20260409120000_good.sql'], $result->ran);
    }

    // ------------------------------------------------------------------
    // rollback
    // ------------------------------------------------------------------

    public function testRollbackRevertsLastMigration(): void
    {
        // Arrange
        $this->writeMigration(
            '20260409120000_create_users.sql',
            'CREATE TABLE users (id INTEGER PRIMARY KEY);',
            'DROP TABLE users;',
        );
        $migrator = $this->createMigrator();
        $migrator->migrate();

        // Act
        $result = $migrator->rollback();

        // Assert
        $this->assertFalse($result->hasErrors());
        $this->assertSame(['20260409120000_create_users.sql'], $result->ran);

        // Verify table is gone
        $rows = $this->connection->query('SELECT name FROM sqlite_master WHERE type = \'table\' AND name = \'users\'');
        $this->assertNull($rows->first());
    }

    public function testRollbackRevertsMultipleSteps(): void
    {
        // Arrange
        $this->writeMigration(
            '20260409120000_create_users.sql',
            'CREATE TABLE users (id INTEGER PRIMARY KEY);',
            'DROP TABLE users;',
        );
        $this->writeMigration(
            '20260409130000_create_posts.sql',
            'CREATE TABLE posts (id INTEGER PRIMARY KEY);',
            'DROP TABLE posts;',
        );
        $migrator = $this->createMigrator();
        $migrator->migrate();

        // Act
        $result = $migrator->rollback(2);

        // Assert — both rolled back, most recent first
        $this->assertFalse($result->hasErrors());
        $this->assertSame([
            '20260409130000_create_posts.sql',
            '20260409120000_create_users.sql',
        ], $result->ran);
    }

    public function testRollbackErrorsWhenFileMissing(): void
    {
        // Arrange — apply then delete the file
        $this->writeMigration(
            '20260409120000_create_users.sql',
            'CREATE TABLE users (id INTEGER PRIMARY KEY);',
            'DROP TABLE users;',
        );
        $migrator = $this->createMigrator();
        $migrator->migrate();

        unlink($this->migrationsDir . '/20260409120000_create_users.sql');

        // Act
        $result = $migrator->rollback();

        // Assert
        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('file not found', $result->errors[0]);
    }

    public function testRollbackReturnsEmptyWhenNothingApplied(): void
    {
        // Arrange
        $migrator = $this->createMigrator();

        // Act
        $result = $migrator->rollback();

        // Assert
        $this->assertFalse($result->hasErrors());
        $this->assertSame([], $result->ran);
    }

    // ------------------------------------------------------------------
    // status
    // ------------------------------------------------------------------

    public function testStatusShowsAppliedAndPending(): void
    {
        // Arrange
        $this->writeMigration(
            '20260409120000_create_users.sql',
            'CREATE TABLE users (id INTEGER PRIMARY KEY);',
            'DROP TABLE users;',
        );
        $this->writeMigration(
            '20260409130000_create_posts.sql',
            'CREATE TABLE posts (id INTEGER PRIMARY KEY);',
            'DROP TABLE posts;',
        );
        $migrator = $this->createMigrator();
        $migrator->migrate(); // apply both

        // Add a new pending file
        $this->writeMigration(
            '20260409140000_create_tags.sql',
            'CREATE TABLE tags (id INTEGER PRIMARY KEY);',
            'DROP TABLE tags;',
        );

        // Act
        $status = $migrator->status();

        // Assert
        $this->assertCount(2, $status->applied);
        $this->assertCount(1, $status->pending);
        $this->assertSame('20260409140000', $status->pending[0]->version);
    }

    // ------------------------------------------------------------------
    // missing directory
    // ------------------------------------------------------------------

    public function testMigrateHandlesMissingDirectory(): void
    {
        // Arrange — point at a directory that doesn't exist
        $repo = new MigrationRepository($this->connection, 'sqlite');
        $migrator = new Migrator($this->connection, $repo, '/nonexistent/path');

        // Act
        $result = $migrator->migrate();

        // Assert — no error, just nothing to do
        $this->assertFalse($result->hasErrors());
        $this->assertSame([], $result->ran);
    }

    // ------------------------------------------------------------------
    // round trip
    // ------------------------------------------------------------------

    public function testMigrateAndRollbackRoundTrip(): void
    {
        // Arrange
        $this->writeMigration(
            '20260409120000_create_users.sql',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL);',
            'DROP TABLE users;',
        );
        $migrator = $this->createMigrator();

        // Act — migrate up
        $migrator->migrate();
        $this->connection->execute("INSERT INTO users (name) VALUES ('Alice')");
        $row = $this->connection->query('SELECT name FROM users')->first();
        $this->assertNotNull($row);
        $this->assertSame('Alice', $row['name']);

        // Act — rollback
        $migrator->rollback();

        // Assert — table gone, re-migrate brings it back empty
        $migrator->migrate();
        $countRow = $this->connection->query('SELECT COUNT(*) as c FROM users')->first();
        $this->assertNotNull($countRow);
        /** @var array{c: int|string} $countRow */
        $this->assertSame(0, (int) $countRow['c']);
    }

    // ------------------------------------------------------------------
    // Logging
    // ------------------------------------------------------------------

    public function testMigrateLogsMigrationApplied(): void
    {
        // Arrange
        $this->writeMigration(
            '20260410120000_create_items.sql',
            'CREATE TABLE items (id INTEGER PRIMARY KEY)',
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Migration applied', $this->callback(
                fn(array $ctx) => $ctx['file'] === '20260410120000_create_items.sql'
                    && isset($ctx['elapsed_ms']),
            ));

        $repo = new MigrationRepository($this->connection, 'sqlite');
        $migrator = new Migrator($this->connection, $repo, $this->migrationsDir, $logger);

        // Act
        $migrator->migrate();
    }

    public function testRollbackLogsMigrationRolledBack(): void
    {
        // Arrange
        $this->writeMigration(
            '20260410120000_create_items.sql',
            'CREATE TABLE items (id INTEGER PRIMARY KEY)',
            'DROP TABLE items',
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with($this->logicalOr('Migration applied', 'Migration rolled back'), $this->anything());

        $repo = new MigrationRepository($this->connection, 'sqlite');
        $migrator = new Migrator($this->connection, $repo, $this->migrationsDir, $logger);
        $migrator->migrate();

        // Reset expectations for the rollback
        $logger2 = $this->createMock(LoggerInterface::class);
        $logger2->expects($this->once())
            ->method('info')
            ->with('Migration rolled back', $this->callback(
                fn(array $ctx) => $ctx['file'] === '20260410120000_create_items.sql'
                    && isset($ctx['elapsed_ms']),
            ));

        $migrator2 = new Migrator($this->connection, $repo, $this->migrationsDir, $logger2);

        // Act
        $migrator2->rollback();
    }

    public function testMigrateLogsChecksumWarning(): void
    {
        // Arrange — apply a migration, then modify the file
        $this->writeMigration(
            '20260410120000_create_items.sql',
            'CREATE TABLE items (id INTEGER PRIMARY KEY)',
        );

        $repo = new MigrationRepository($this->connection, 'sqlite');
        $migrator = new Migrator($this->connection, $repo, $this->migrationsDir);
        $migrator->migrate();

        // Modify the file after applying
        $this->writeMigration(
            '20260410120000_create_items.sql',
            'CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)',
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Checksum mismatch', $this->callback(
                fn(array $ctx) => str_contains($ctx['error'], '20260410120000_create_items.sql'),
            ));

        $migrator2 = new Migrator($this->connection, $repo, $this->migrationsDir, $logger);

        // Act
        $migrator2->migrate();
    }
}
