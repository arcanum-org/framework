<?php

declare(strict_types=1);

namespace Arcanum\Flow\Conveyor;

use Arcanum\Gather\Coercible;
use Arcanum\Gather\Registry;

/**
 * Base class for dynamic DTOs that wrap arbitrary request data.
 *
 * Command, Query, and Page all share the same structure: a Registry
 * of request data accessible via typed Coercible methods, plus
 * HandlerProxy for routing to the correct handler.
 */
abstract class DynamicDTO implements HandlerProxy, Coercible
{
    private Registry $registry;

    /**
     * @param string $handlerBaseName The virtual DTO class name for handler resolution.
     * @param array<string, mixed> $data Request data.
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

    public function get(string $id): mixed
    {
        return $this->registry->get($id);
    }

    public function has(string $id): bool
    {
        return $this->registry->has($id);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->registry->toArray();
    }

    public function asAlpha(string $key, string $fallback = ''): string
    {
        return $this->registry->asAlpha($key, $fallback);
    }

    public function asAlnum(string $key, string $fallback = ''): string
    {
        return $this->registry->asAlnum($key, $fallback);
    }

    public function asDigits(string $key, string $fallback = ''): string
    {
        return $this->registry->asDigits($key, $fallback);
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

    public function getIterator(): \Traversable
    {
        return $this->registry->getIterator();
    }

    public function count(): int
    {
        return $this->registry->count();
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->registry->offsetExists($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->registry->offsetGet($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->registry->offsetSet($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->registry->offsetUnset($offset);
    }
}
