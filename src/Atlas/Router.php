<?php

declare(strict_types=1);

namespace Arcanum\Atlas;

/**
 * Maps an input source to a Route.
 *
 * The Router interface is transport-agnostic. Concrete implementations
 * adapt specific input sources (e.g., ServerRequestInterface for HTTP,
 * CLI arguments for console commands) and delegate to the convention
 * system to produce a Route.
 */
interface Router
{
    public function resolve(object $input): Route;
}
