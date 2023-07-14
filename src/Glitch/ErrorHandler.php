<?php

declare(strict_types=1);

namespace Arcanum\Glitch;

interface ErrorHandler
{
    /**
     * Handle an error.
     */
    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool;
}
