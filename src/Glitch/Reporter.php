<?php

declare(strict_types=1);

namespace Arcanum\Glitch;

use Psr\Log\LoggerInterface;

interface Reporter
{
    /**
     * Report an exception.
     */
    public function __invoke(\Throwable $e): void;

    /**
     * Check if this reporter handles the given exception.
     *
     * @param class-string<\Throwable> $exceptionName
     */
    public function handles(string $exceptionName): bool;
}
