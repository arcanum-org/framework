<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Sequence;

/**
 * Mutable counter used by Cursor tests that need to observe closure execution
 * from the outside. A real class (rather than a stdClass or ref) keeps PHPStan
 * from narrowing the property across the closure boundary.
 */
final class SourceInvocationCounter
{
    public int $count = 0;
}
