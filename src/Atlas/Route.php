<?php

declare(strict_types=1);

namespace Arcanum\Atlas;

final class Route
{
    /**
     * @param string $dtoClass The fully-qualified Command or Query class name.
     * @param string $handlerPrefix Method-specific prefix for handler resolution
     *                              (e.g., 'Post', 'Delete', 'Patch'). Empty string for
     *                              GET (Query) and PUT (default Command).
     * @param string $format The response format parsed from the URL extension
     *                       (e.g., 'json', 'html', 'csv').
     */
    public function __construct(
        public readonly string $dtoClass,
        public readonly string $handlerPrefix = '',
        public readonly string $format = 'json',
    ) {
    }

    public function isQuery(): bool
    {
        return $this->handlerPrefix === '' && !str_contains($this->dtoClass, '\\Command\\');
    }

    public function isCommand(): bool
    {
        return str_contains($this->dtoClass, '\\Command\\');
    }

    /**
     * Return a new Route with a different format.
     */
    public function withFormat(string $format): self
    {
        return new self(
            dtoClass: $this->dtoClass,
            handlerPrefix: $this->handlerPrefix,
            format: $format,
        );
    }
}
