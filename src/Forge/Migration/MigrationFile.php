<?php

declare(strict_types=1);

namespace Arcanum\Forge\Migration;

/**
 * A parsed migration file.
 *
 * Immutable value object representing one migration — the version
 * (timestamp prefix), human-readable name, raw up/down SQL, whether
 * it should run inside a transaction, and a checksum for integrity
 * validation.
 */
final readonly class MigrationFile
{
    public function __construct(
        /** Timestamp prefix, e.g. "20260409120000". */
        public string $version,
        /** Human-readable name, e.g. "create_users". */
        public string $name,
        /** Full filename, e.g. "20260409120000_create_users.sql". */
        public string $filename,
        /** SQL to run when migrating up. */
        public string $upSql,
        /** SQL to run when rolling back. */
        public string $downSql,
        /** Whether to wrap execution in a transaction. */
        public bool $transactional,
        /** md5 checksum of the raw file contents. */
        public string $checksum,
    ) {
    }
}
