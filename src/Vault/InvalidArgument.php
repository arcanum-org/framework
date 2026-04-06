<?php

declare(strict_types=1);

namespace Arcanum\Vault;

use Arcanum\Glitch\ArcanumException;

/**
 * Thrown when a cache key violates PSR-16 constraints.
 *
 * Implements the PSR-16 InvalidArgumentException interface so consumers
 * can catch the PSR interface type.
 */
final class InvalidArgument extends \InvalidArgumentException implements
    ArcanumException,
    \Psr\SimpleCache\InvalidArgumentException
{
    public function getTitle(): string
    {
        return 'Invalid Cache Key';
    }

    public function getSuggestion(): string
    {
        return 'Cache keys must be non-empty strings without'
            . ' the reserved characters {}()/\@:';
    }
}
