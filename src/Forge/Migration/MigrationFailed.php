<?php

declare(strict_types=1);

namespace Arcanum\Forge\Migration;

use Arcanum\Glitch\ArcanumException;

/**
 * Thrown when a migration's SQL fails to execute.
 *
 * Wraps the underlying database exception with migration context
 * (which version, which direction).
 */
class MigrationFailed extends \RuntimeException implements ArcanumException
{
    public function __construct(
        public readonly string $filename,
        public readonly string $direction,
        \Throwable $previous,
    ) {
        parent::__construct(sprintf(
            'Migration "%s" failed during %s: %s',
            $filename,
            $direction,
            $previous->getMessage(),
        ), 0, $previous);
    }

    public function getTitle(): string
    {
        return 'Migration Failed';
    }

    public function getSuggestion(): ?string
    {
        return 'Check the SQL in the migration file. If the migration was '
            . 'transactional, no changes were applied. If it used '
            . '"-- @transaction off", the database may be in a partial state.';
    }
}
