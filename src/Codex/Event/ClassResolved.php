<?php

declare(strict_types=1);

namespace Arcanum\Codex\Event;

final class ClassResolved extends \Arcanum\Echo\Event implements CodexEvent
{
    /**
     * @param object $class
     */
    public function __construct(private object $class)
    {
    }

    /**
     * Get the class name.
     *
     * @return class-string
     */
    public function className(): string
    {
        return get_class($this->class);
    }

    /**
     * Get the class.
     *
     * Will return null if the class is not yet resolved.
     */
    public function class(): object
    {
        return $this->class;
    }
}
