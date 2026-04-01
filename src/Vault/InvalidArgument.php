<?php

declare(strict_types=1);

namespace Arcanum\Vault;

/**
 * Thrown when a cache key violates PSR-16 constraints.
 *
 * Implements the PSR-16 InvalidArgumentException interface so consumers
 * can catch the PSR interface type.
 */
final class InvalidArgument extends \InvalidArgumentException implements \Psr\SimpleCache\InvalidArgumentException
{
}
