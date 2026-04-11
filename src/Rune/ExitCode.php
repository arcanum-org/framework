<?php

declare(strict_types=1);

namespace Arcanum\Rune;

/**
 * Standard CLI exit codes.
 *
 * Exit codes follow POSIX conventions: 0 for success, non-zero for failure.
 */
enum ExitCode: int
{
    case Success = 0;
    case Failure = 1;
    case Invalid = 2;
}
