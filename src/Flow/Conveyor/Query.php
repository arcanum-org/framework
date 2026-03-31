<?php

declare(strict_types=1);

namespace Arcanum\Flow\Conveyor;

/**
 * A dynamic Query DTO for handlers that don't define an explicit DTO class.
 *
 * When only a handler exists (e.g., ProductsHandler without Products),
 * the framework creates a Query from the query parameters and dispatches
 * it to the handler. Properties are accessed dynamically via __get.
 *
 * ```php
 * class ProductsHandler {
 *     public function __invoke(Query $query): array {
 *         $query->page;     // dynamic property access
 *         $query->category;
 *     }
 * }
 * ```
 */
final class Query implements HandlerProxy
{
    /**
     * @param string $handlerBaseName The virtual DTO class name for handler resolution.
     * @param array<string, mixed> $data Request data (query params).
     */
    public function __construct(
        private readonly string $handlerBaseName,
        private readonly array $data = [],
    ) {
    }

    public function handlerBaseName(): string
    {
        return $this->handlerBaseName;
    }

    /**
     * Get a value by key.
     */
    public function get(string $name, mixed $default = null): mixed
    {
        return $this->data[$name] ?? $default;
    }

    /**
     * Check if a key exists.
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * Get all data as an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
