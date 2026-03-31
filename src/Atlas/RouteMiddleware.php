<?php

declare(strict_types=1);

namespace Arcanum\Atlas;

/**
 * Holds the combined, ordered middleware for a single route.
 *
 * Three lists of class-strings, one per middleware layer:
 *   - http:   PSR-15 MiddlewareInterface classes (HTTP layer)
 *   - before: Progression classes that run before the handler (Conveyor layer)
 *   - after:  Progression classes that run after the handler (Conveyor layer)
 */
final class RouteMiddleware
{
    /**
     * @param list<string> $http PSR-15 MiddlewareInterface class names.
     * @param list<string> $before Progression class names (run before handler).
     * @param list<string> $after Progression class names (run after handler).
     */
    public function __construct(
        public readonly array $http = [],
        public readonly array $before = [],
        public readonly array $after = [],
    ) {
    }

    /**
     * Whether all three middleware lists are empty.
     */
    public function isEmpty(): bool
    {
        return $this->http === [] && $this->before === [] && $this->after === [];
    }

    /**
     * Merge directory middleware (this, outer) with attribute middleware (inner).
     *
     * For http and before: directory entries run first (outermost).
     * For after: inner entries run first, then directory (outermost runs last).
     */
    public function merge(self $inner): self
    {
        return new self(
            http: [...$this->http, ...$inner->http],
            before: [...$this->before, ...$inner->before],
            after: [...$inner->after, ...$this->after],
        );
    }
}
