<?php

declare(strict_types=1);

namespace Arcanum\Flow\Conveyor;

use Arcanum\Gather\Registry;

/**
 * A dynamic Query DTO for handlers that don't define an explicit DTO class.
 *
 * When only a handler exists (e.g., ProductsHandler without Products),
 * the framework creates a Query from the query parameters and dispatches
 * it to the handler. Data is accessed via typed accessors inherited from Registry.
 *
 * ```php
 * class ProductsHandler {
 *     public function __invoke(Query $query): array {
 *         $page = $query->asInt('page', 1);
 *         $category = $query->asString('category');
 *     }
 * }
 * ```
 */
final class Query implements HandlerProxy
{
    private Registry $registry;

    /**
     * @param string $handlerBaseName The virtual DTO class name for handler resolution.
     * @param array<string, mixed> $data Request data (query params).
     */
    public function __construct(
        private readonly string $handlerBaseName,
        array $data = [],
    ) {
        $this->registry = new Registry($data);
    }

    public function handlerBaseName(): string
    {
        return $this->handlerBaseName;
    }

    public function get(string $key): mixed
    {
        return $this->registry->get($key);
    }

    public function has(string $key): bool
    {
        return $this->registry->has($key);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->registry->toArray();
    }

    public function asString(string $key, string $fallback = ''): string
    {
        return $this->registry->asString($key, $fallback);
    }

    public function asInt(string $key, int $fallback = 0): int
    {
        return $this->registry->asInt($key, $fallback);
    }

    public function asFloat(string $key, float $fallback = 0.0): float
    {
        return $this->registry->asFloat($key, $fallback);
    }

    public function asBool(string $key, bool $fallback = false): bool
    {
        return $this->registry->asBool($key, $fallback);
    }
}
