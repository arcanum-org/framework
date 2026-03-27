<?php

declare(strict_types=1);

namespace Arcanum\Routing;

final class Route
{
    /**
     * @param string $dtoClass The fully-qualified Command or Query class name.
     * @param string $handlerPrefix Method-specific prefix for handler resolution
     *                              (e.g., 'Post', 'Delete', 'Patch'). Empty string for
     *                              GET (Query) and PUT (default Command).
     * @param array<string, string> $pathParameters Extracted path parameters keyed by
     *                                              name (e.g., ['id' => '123']).
     * @param string $format The response format parsed from the URL extension
     *                       (e.g., 'json', 'html', 'csv').
     */
    public function __construct(
        public readonly string $dtoClass,
        public readonly string $handlerPrefix = '',
        public readonly array $pathParameters = [],
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
            pathParameters: $this->pathParameters,
            format: $format,
        );
    }

    /**
     * Return a new Route with additional path parameters merged in.
     *
     * @param array<string, string> $parameters
     */
    public function withPathParameters(array $parameters): self
    {
        return new self(
            dtoClass: $this->dtoClass,
            handlerPrefix: $this->handlerPrefix,
            pathParameters: array_merge($this->pathParameters, $parameters),
            format: $this->format,
        );
    }
}
