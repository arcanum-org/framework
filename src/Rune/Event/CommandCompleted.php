<?php

declare(strict_types=1);

namespace Arcanum\Rune\Event;

use Arcanum\Echo\Event;
use Arcanum\Rune\Input;

/**
 * Dispatched from RuneKernel::terminate() after all command work is done.
 *
 * Read-only. The CLI parallel to ResponseSent — use for cleanup,
 * deferred logging, or metrics that don't need to block the exit.
 */
class CommandCompleted extends Event
{
    public function __construct(
        private readonly Input $input,
        private readonly int $exitCode,
    ) {
    }

    public function getInput(): Input
    {
        return $this->input;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }
}
