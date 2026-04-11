<?php

declare(strict_types=1);

namespace Arcanum\Forge\Migration;

use Arcanum\Glitch\ArcanumException;

/**
 * Thrown when an already-applied migration file has been modified on disk.
 *
 * Checksum validation prevents silent drift between environments.
 * The developer must either restore the original file or manually
 * update the checksum in the arcanum_migrations table.
 */
class ChecksumMismatch extends \RuntimeException implements ArcanumException
{
    public function __construct(
        public readonly string $version,
        public readonly string $filename,
        public readonly string $expected,
        public readonly string $actual,
    ) {
        parent::__construct(sprintf(
            'Migration "%s" has been modified after it was applied '
                . '(expected checksum %s, got %s). '
                . 'Applied migrations must not be edited.',
            $filename,
            $expected,
            $actual,
        ));
    }

    public function getTitle(): string
    {
        return 'Migration Checksum Mismatch';
    }

    public function getSuggestion(): ?string
    {
        return 'Restore the original file contents, or if the change is intentional, '
            . 'roll back the migration and re-apply it.';
    }
}
