<?php

declare(strict_types=1);

namespace Arcanum\Forge\Migration;

use Arcanum\Forge\Connection;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates database migrations: running, rolling back, and reporting status.
 *
 * The migrator reads .sql files from the migrations directory, parses them
 * via MigrationParser, tracks state via MigrationRepository, and executes
 * SQL via the Forge Connection interface.
 *
 * Each migration runs inside a transaction by default (both the SQL and
 * the version recording). Opt out per-migration with `-- @transaction off`.
 *
 * Before running pending migrations, the migrator validates checksums of
 * all applied migrations against their files on disk. If any file has been
 * modified after being applied, the migrator halts and reports the mismatch.
 */
final class Migrator
{
    private readonly MigrationParser $parser;

    public function __construct(
        private readonly Connection $connection,
        private readonly MigrationRepository $repository,
        private readonly string $migrationsPath,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->parser = new MigrationParser();
    }

    /**
     * Run all pending migrations.
     *
     * @param (callable(MigrationFile, float): void)|null $onMigrate
     *     Called after each migration completes, with the file and elapsed ms.
     */
    public function migrate(?callable $onMigrate = null): MigrationResult
    {
        $this->repository->ensureTable();

        $files = $this->scanFiles();
        $applied = $this->repository->applied();

        // Checksum validation — halt on any mismatch.
        $checksumMismatch = $this->validateChecksums($files, $applied);
        if ($checksumMismatch !== null) {
            $this->logger?->warning('Checksum mismatch', [
                'file' => $checksumMismatch['file'],
                'expected' => $checksumMismatch['expected'],
                'actual' => $checksumMismatch['actual'],
            ]);
            return new MigrationResult([], [$checksumMismatch['error']]);
        }

        $pending = $this->filterPending($files, $applied);

        if ($pending === []) {
            return new MigrationResult([], []);
        }

        $ran = [];

        foreach ($pending as $file) {
            $start = hrtime(true);

            try {
                $this->executeMigration($file, 'up');
            } catch (MigrationFailed $e) {
                $this->logger?->error('Migration failed', [
                    'file' => $file->filename,
                    'error' => $e->getMessage(),
                ]);
                return new MigrationResult($ran, [$e->getMessage()]);
            }

            $elapsedMs = (hrtime(true) - $start) / 1_000_000;
            $ran[] = $file->filename;

            $this->logger?->info('Migration applied', [
                'file' => $file->filename,
                'elapsed_ms' => round($elapsedMs, 2),
            ]);

            if ($onMigrate !== null) {
                $onMigrate($file, $elapsedMs);
            }
        }

        return new MigrationResult($ran, []);
    }

    /**
     * Roll back the most recent N migrations.
     *
     * @param (callable(MigrationFile, float): void)|null $onRollback
     */
    public function rollback(int $steps = 1, ?callable $onRollback = null): MigrationResult
    {
        $this->repository->ensureTable();

        $applied = $this->repository->applied();
        $files = $this->scanFiles();
        $filesByVersion = [];
        foreach ($files as $file) {
            $filesByVersion[$file->version] = $file;
        }

        // Take the last N applied, in reverse order.
        $toRollback = array_reverse(array_values($applied));
        $toRollback = array_slice($toRollback, 0, $steps);

        if ($toRollback === []) {
            return new MigrationResult([], []);
        }

        $ran = [];

        foreach ($toRollback as $record) {
            if (!isset($filesByVersion[$record->version])) {
                return new MigrationResult($ran, [sprintf(
                    'Migration file not found for version %s (%s). '
                        . 'Cannot rollback without the down SQL.',
                    $record->version,
                    $record->filename,
                )]);
            }

            $file = $filesByVersion[$record->version];
            $start = hrtime(true);

            try {
                $this->executeRollback($file);
            } catch (MigrationFailed $e) {
                $this->logger?->error('Migration failed', [
                    'file' => $file->filename,
                    'error' => $e->getMessage(),
                ]);
                return new MigrationResult($ran, [$e->getMessage()]);
            }

            $elapsedMs = (hrtime(true) - $start) / 1_000_000;
            $ran[] = $file->filename;

            $this->logger?->info('Migration rolled back', [
                'file' => $file->filename,
                'elapsed_ms' => round($elapsedMs, 2),
            ]);

            if ($onRollback !== null) {
                $onRollback($file, $elapsedMs);
            }
        }

        return new MigrationResult($ran, []);
    }

    /**
     * Get the current migration status.
     */
    public function status(): MigrationStatus
    {
        $this->repository->ensureTable();

        $files = $this->scanFiles();
        $applied = $this->repository->applied();

        return new MigrationStatus(
            applied: array_values($applied),
            pending: $this->filterPending($files, $applied),
        );
    }

    /**
     * Get pending migrations (not yet applied).
     *
     * @return list<MigrationFile>
     */
    public function pending(): array
    {
        $this->repository->ensureTable();

        return $this->filterPending(
            $this->scanFiles(),
            $this->repository->applied(),
        );
    }

    /**
     * Scan the migrations directory and parse all .sql files.
     *
     * @return list<MigrationFile> Sorted by version ascending.
     */
    private function scanFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $entries = scandir($this->migrationsPath);
        if ($entries === false) {
            return [];
        }

        $files = [];
        foreach ($entries as $entry) {
            if (!str_ends_with($entry, '.sql')) {
                continue;
            }

            $path = $this->migrationsPath . DIRECTORY_SEPARATOR . $entry;
            $contents = file_get_contents($path);

            if ($contents === false) {
                continue;
            }

            $files[] = $this->parser->parse($path, $contents);
        }

        usort($files, static fn (MigrationFile $a, MigrationFile $b) => $a->version <=> $b->version);

        return $files;
    }

    /**
     * Validate that applied migration files haven't been modified.
     *
     * @param list<MigrationFile>                  $files
     * @param array<string, AppliedMigration>      $applied
     * @return array{file: string, expected: string, actual: string, error: string}|null
     */
    private function validateChecksums(array $files, array $applied): ?array
    {
        $filesByVersion = [];
        foreach ($files as $file) {
            $filesByVersion[$file->version] = $file;
        }

        foreach ($applied as $record) {
            if (!isset($filesByVersion[$record->version])) {
                continue; // File deleted — not a checksum error, just missing.
            }

            $file = $filesByVersion[$record->version];
            if ($file->checksum !== $record->checksum) {
                return [
                    'file' => $record->filename,
                    'expected' => $record->checksum,
                    'actual' => $file->checksum,
                    'error' => sprintf(
                        'Migration "%s" has been modified after it was applied '
                            . '(expected checksum %s, got %s). '
                            . 'Applied migrations must not be edited.',
                        $record->filename,
                        $record->checksum,
                        $file->checksum,
                    ),
                ];
            }
        }

        return null;
    }

    /**
     * Filter to migrations not yet applied.
     *
     * @param list<MigrationFile>             $files
     * @param array<string, AppliedMigration> $applied
     * @return list<MigrationFile>
     */
    private function filterPending(array $files, array $applied): array
    {
        return array_values(array_filter(
            $files,
            static fn (MigrationFile $f) => !isset($applied[$f->version]),
        ));
    }

    /**
     * Execute a migration's up SQL and record it.
     *
     * @throws MigrationFailed
     */
    private function executeMigration(MigrationFile $file, string $direction): void
    {
        $sql = $file->upSql;

        if ($file->transactional) {
            $this->connection->beginTransaction();
        }

        try {
            $this->connection->execute($sql);
            $this->repository->record($file->version, $file->filename, $file->checksum);

            if ($file->transactional) {
                $this->connection->commit();
            }
        } catch (\Throwable $e) {
            if ($file->transactional) {
                $this->connection->rollBack();
            }

            throw new MigrationFailed($file->filename, $direction, $e);
        }
    }

    /**
     * Execute a migration's down SQL and remove the record.
     *
     * @throws MigrationFailed
     */
    private function executeRollback(MigrationFile $file): void
    {
        if ($file->transactional) {
            $this->connection->beginTransaction();
        }

        try {
            if ($file->downSql !== '') {
                $this->connection->execute($file->downSql);
            }
            $this->repository->remove($file->version);

            if ($file->transactional) {
                $this->connection->commit();
            }
        } catch (\Throwable $e) {
            if ($file->transactional) {
                $this->connection->rollBack();
            }

            throw new MigrationFailed($file->filename, 'down', $e);
        }
    }
}
