<?php

declare(strict_types=1);

namespace Arcanum\Forge\Migration;

/**
 * A snapshot of migration state: what's applied and what's pending.
 */
final readonly class MigrationStatus
{
    /**
     * @param list<AppliedMigration> $applied
     * @param list<MigrationFile>    $pending
     */
    public function __construct(
        public array $applied,
        public array $pending,
    ) {
    }
}
