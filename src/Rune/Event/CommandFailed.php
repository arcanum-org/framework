<?php

declare(strict_types=1);

namespace Arcanum\Rune\Event;

use Arcanum\Echo\Event;
use Arcanum\Rune\Input;

/**
 * Dispatched when an exception is thrown during CLI command handling.
 *
 * Read-only and observational: Glitch still handles exception rendering.
 * Listeners are for reporting, metrics, and notifications.
 */
class CommandFailed extends Event
{
    public function __construct(
        private readonly Input $input,
        private readonly \Throwable $exception,
    ) {
    }

    public function getInput(): Input
    {
        return $this->input;
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }
}
