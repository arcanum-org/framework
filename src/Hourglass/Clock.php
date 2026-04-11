<?php

declare(strict_types=1);

namespace Arcanum\Hourglass;

use Psr\Clock\ClockInterface;

/**
 * Wall-clock time abstraction.
 *
 * Extends PSR-20 ClockInterface so any code expecting a PSR clock works
 * out of the box. Arcanum code depends on this interface so the package
 * controls its own surface — methods can be added later without breaking
 * the PSR contract.
 */
interface Clock extends ClockInterface
{
}
