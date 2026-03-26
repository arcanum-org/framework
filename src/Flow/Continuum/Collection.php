<?php

declare(strict_types=1);

namespace Arcanum\Flow\Continuum;

interface Collection
{
    /**
     * Add a continuation to the collection.
     */
    public function add(string $key, Progression $stage): Continuation;

    /**
     * Send a payload through a continuation.
     */
    public function send(string $key, object $payload): object;

    /**
     * Get a continuation from the collection.
     */
    public function continuation(string $key, ?Continuation $default = null): Continuation;
}
