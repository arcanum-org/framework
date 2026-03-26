<?php

declare(strict_types=1);

namespace Arcanum\Flow\Continuum;

class ContinuationCollection implements Collection
{
    /**
     * Create a new collection of continuations.
     *
     * @param array<string,Continuation> $continuations
     */
    public function __construct(protected array $continuations = [])
    {
    }

    /**
     * Add a continuation to the collection.
     */
    public function add(string $key, Progression $stage): Continuation
    {
        return $this->continuation($key)->add($stage);
    }

    /**
     * Send a payload through a continuation.
     */
    public function send(string $key, object $payload): object
    {
        return $this->continuation($key)->send($payload);
    }

    /**
     * Get a continuation from the collection.
     */
    public function continuation(string $key, ?Continuation $default = null): Continuation
    {
        if (!isset($this->continuations[$key])) {
            $this->continuations[$key] = $default ?? new Continuum();
        }
        return $this->continuations[$key];
    }
}
