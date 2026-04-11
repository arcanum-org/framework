<?php

declare(strict_types=1);

namespace Arcanum\Rune\Event;

use Arcanum\Echo\Event;
use Arcanum\Rune\Input;

/**
 * Dispatched when a CLI command is parsed, before dispatch.
 *
 * Read-only: listeners observe the incoming command for logging,
 * metrics, or audit trails.
 */
class CommandReceived extends Event
{
    public function __construct(
        private readonly Input $input,
    ) {
    }

    public function getInput(): Input
    {
        return $this->input;
    }
}
