<?php

declare(strict_types=1);

namespace Arcanum\Flow\Pipeline;

use Arcanum\Flow\Stage;

interface System
{
    /**
     * Add a stage to a pipeline.
     *
     * @param Stage|callable(object): object $stage
     */
    public function pipe(string $key, callable|Stage $stage): Pipelayer;

    /**
     * Send a payload through a pipeline.
     */
    public function send(string $key, object $payload): object;

    /**
     * Get a pipeline from the system.
     */
    public function pipeline(string $key, ?Pipelayer $default = null): Pipelayer;

    /**
     * Remove a pipeline from the system.
     */
    public function purge(string $key): void;
}
