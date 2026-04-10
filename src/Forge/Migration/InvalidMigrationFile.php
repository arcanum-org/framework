<?php

declare(strict_types=1);

namespace Arcanum\Forge\Migration;

use Arcanum\Glitch\ArcanumException;

/**
 * Thrown when a migration file has an invalid format — missing markers,
 * malformed filename, or empty up section.
 */
class InvalidMigrationFile extends \RuntimeException implements ArcanumException
{
    public function getTitle(): string
    {
        return 'Invalid Migration File';
    }

    public function getSuggestion(): ?string
    {
        return 'Migration files must contain both "-- @migrate up" and '
            . '"-- @migrate down" markers. Use "php arcanum migrate:create <name>" '
            . 'to scaffold a correctly formatted file.';
    }
}
