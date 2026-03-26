<?php

declare(strict_types=1);

namespace Arcanum\Flow\Pipeline;

use Arcanum\Flow\Stage;

class PipelayerSystem implements System
{
    /**
     * Create a new system of pipelines.
     *
     * @param array<string,Pipelayer> $pipelines
     */
    public function __construct(protected array $pipelines = [])
    {
    }

    /**
     * Add a stage to a pipeline.
     *
     * @param Stage|callable(object): object $stage
     */
    public function pipe(string $key, callable|Stage $stage): Pipelayer
    {
        return $this->pipeline($key)->pipe($stage);
    }

    /**
     * Send a payload through a pipeline.
     */
    public function send(string $key, object $payload): object
    {
        return $this->pipeline($key)->send($payload);
    }

    /**
     * Get a pipeline from the system.
     */
    public function pipeline(string $key, ?Pipelayer $default = null): Pipelayer
    {
        if (!isset($this->pipelines[$key])) {
            $this->pipelines[$key] = $default ?? new Pipeline();
        }
        return $this->pipelines[$key];
    }

    /**
     * Remove a pipeline from the system.
     */
    public function purge(string $key): void
    {
        unset($this->pipelines[$key]);
    }
}
