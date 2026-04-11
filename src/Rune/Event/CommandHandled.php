<?php

declare(strict_types=1);

namespace Arcanum\Rune\Event;

use Arcanum\Echo\Event;
use Arcanum\Rune\Input;

/**
 * Dispatched after a CLI command completes successfully.
 *
 * Read-only: listeners observe the outcome for logging, metrics,
 * or audit trails.
 */
class CommandHandled extends Event
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
