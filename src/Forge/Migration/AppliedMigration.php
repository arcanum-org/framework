<?php

declare(strict_types=1);

namespace Arcanum\Forge\Migration;

/**
 * A migration that has been applied to the database.
 *
 * Represents one row from the arcanum_migrations state table.
 */
final readonly class AppliedMigration
{
    public function __construct(
        public string $version,
        public string $filename,
        public string $checksum,
        public string $appliedAt,
    ) {
    }
}
