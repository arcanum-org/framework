<?php

declare(strict_types=1);

namespace Arcanum\Forge\Migration;

use Arcanum\Forge\Connection;

/**
 * Manages the arcanum_migrations state table.
 *
 * Tracks which migrations have been applied, their checksums for
 * integrity validation, and timestamps. The table DDL is driver-aware
 * (SQLite, MySQL, PostgreSQL) and created automatically on first use.
 */
final class MigrationRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $driver,
    ) {
    }

    /**
     * Create the migrations table if it doesn't exist.
     */
    public function ensureTable(): void
    {
        $ddl = match ($this->driver) {
            'sqlite' => <<<'SQL'
                CREATE TABLE IF NOT EXISTS arcanum_migrations (
                    version TEXT NOT NULL PRIMARY KEY,
                    filename TEXT NOT NULL,
                    checksum TEXT NOT NULL,
                    applied_at TEXT NOT NULL DEFAULT (datetime('now'))
                )
                SQL,
            'mysql' => <<<'SQL'
                CREATE TABLE IF NOT EXISTS arcanum_migrations (
                    version VARCHAR(14) NOT NULL PRIMARY KEY,
                    filename VARCHAR(255) NOT NULL,
                    checksum VARCHAR(32) NOT NULL,
                    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,
            'pgsql' => <<<'SQL'
                CREATE TABLE IF NOT EXISTS arcanum_migrations (
                    version VARCHAR(14) NOT NULL PRIMARY KEY,
                    filename VARCHAR(255) NOT NULL,
                    checksum VARCHAR(32) NOT NULL,
                    applied_at TIMESTAMP NOT NULL DEFAULT NOW()
                )
                SQL,
            default => throw new \RuntimeException(sprintf(
                'Unsupported database driver "%s" for migrations. '
                    . 'Supported: sqlite, mysql, pgsql.',
                $this->driver,
            )),
        };

        $this->connection->execute($ddl);
    }

    /**
     * All applied migrations, keyed by version.
     *
     * @return array<string, AppliedMigration>
     */
    public function applied(): array
    {
        $rows = $this->connection->query(
            'SELECT version, filename, checksum, applied_at '
            . 'FROM arcanum_migrations ORDER BY version ASC',
        );

        $applied = [];
        foreach ($rows as $row) {
            /** @var array{version: string, filename: string, checksum: string, applied_at: string} $row */
            $applied[$row['version']] = new AppliedMigration(
                version: $row['version'],
                filename: $row['filename'],
                checksum: $row['checksum'],
                appliedAt: $row['applied_at'],
            );
        }

        return $applied;
    }

    /**
     * Record a migration as applied.
     */
    public function record(string $version, string $filename, string $checksum): void
    {
        $this->connection->execute(
            'INSERT INTO arcanum_migrations (version, filename, checksum) '
                . 'VALUES (:version, :filename, :checksum)',
            [
                'version' => $version,
                'filename' => $filename,
                'checksum' => $checksum,
            ],
        );
    }

    /**
     * Remove a migration record (used during rollback).
     */
    public function remove(string $version): void
    {
        $this->connection->execute(
            'DELETE FROM arcanum_migrations WHERE version = :version',
            ['version' => $version],
        );
    }
}
