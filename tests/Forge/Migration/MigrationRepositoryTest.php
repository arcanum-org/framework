<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge\Migration;

use Arcanum\Forge\Migration\AppliedMigration;
use Arcanum\Forge\Migration\MigrationRepository;
use Arcanum\Forge\PdoConnection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(MigrationRepository::class)]
#[UsesClass(AppliedMigration::class)]
#[UsesClass(PdoConnection::class)]
#[UsesClass(\Arcanum\Forge\WriteResult::class)]
#[UsesClass(\Arcanum\Flow\Sequence\Cursor::class)]
final class MigrationRepositoryTest extends TestCase
{
    private PdoConnection $connection;
    private MigrationRepository $repository;

    protected function setUp(): void
    {
        $this->connection = new PdoConnection('sqlite::memory:');
        $this->repository = new MigrationRepository($this->connection, 'sqlite');
        $this->repository->ensureTable();
    }

    public function testEnsureTableIsIdempotent(): void
    {
        // Act — call again, should not throw
        $this->repository->ensureTable();

        // Assert — table exists and is queryable
        $applied = $this->repository->applied();
        $this->assertSame([], $applied);
    }

    public function testRecordAndRetrieveAppliedMigration(): void
    {
        // Act
        $this->repository->record('20260409120000000', '20260409120000000_create_users.sql', 'abc123');

        // Assert
        $applied = $this->repository->applied();
        $this->assertCount(1, $applied);
        $first = reset($applied);
        $this->assertInstanceOf(AppliedMigration::class, $first);
        $this->assertSame('20260409120000000', $first->version);
        $this->assertSame('20260409120000000_create_users.sql', $first->filename);
        $this->assertSame('abc123', $first->checksum);
        $this->assertNotEmpty($first->appliedAt);
    }

    public function testAppliedReturnsSortedByVersion(): void
    {
        // Arrange — insert out of order
        $this->repository->record('20260409130000000', 'b.sql', 'bbb');
        $this->repository->record('20260409120000000', 'a.sql', 'aaa');
        $this->repository->record('20260409140000000', 'c.sql', 'ccc');

        // Act
        $applied = $this->repository->applied();

        // Assert
        $versions = array_map(
            static fn (AppliedMigration $m) => $m->version,
            array_values($applied),
        );
        $this->assertSame(['20260409120000000', '20260409130000000', '20260409140000000'], $versions);
    }

    public function testRemoveDeletesMigrationRecord(): void
    {
        // Arrange
        $this->repository->record('20260409120000000', 'a.sql', 'aaa');
        $this->repository->record('20260409130000000', 'b.sql', 'bbb');

        // Act
        $this->repository->remove('20260409120000000');

        // Assert
        $applied = $this->repository->applied();
        $this->assertCount(1, $applied);
        $this->assertArrayNotHasKey('20260409120000000', $applied);
        $this->assertArrayHasKey('20260409130000000', $applied);
    }

    public function testAppliedReturnsEmptyWhenNoMigrations(): void
    {
        // Act & Assert
        $this->assertSame([], $this->repository->applied());
    }

    public function testThrowsOnUnsupportedDriver(): void
    {
        // Arrange
        $repo = new MigrationRepository($this->connection, 'oracle');

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported database driver "oracle"');
        $repo->ensureTable();
    }
}
