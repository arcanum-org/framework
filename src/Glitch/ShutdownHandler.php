<?php

declare(strict_types=1);

namespace Arcanum\Glitch;

interface ShutdownHandler
{
    /**
     * Handle a shutdown.
     */
    public function handleShutdown(): void;
}
