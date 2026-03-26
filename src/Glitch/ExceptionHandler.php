<?php

declare(strict_types=1);

namespace Arcanum\Glitch;

interface ExceptionHandler
{
    /**
     * Handle an exception.
     */
    public function handleException(\Throwable $ex): void;
}
