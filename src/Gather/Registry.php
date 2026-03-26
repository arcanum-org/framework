<?php

declare(strict_types=1);

namespace Arcanum\Gather;

/** @phpstan-consistent-constructor */
class Registry implements Coercible, Serializable
{
    /**
     * Construct a Registry.
     *
     * @param array<string, mixed> $data
     */
    public function __construct(
        protected array $data = [],
    ) {
    }

    /**
     * Return the data for serialization.
     *
     * @return array<string, mixed> $data
     */
    public function __serialize(): array
    {
        return $this->data;
    }

    /**
     * Accept the data for unserialization.
     *
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->data = $data;
    }

    /**
     * Return the data as a JSON string.
     */
    public function __toString(): string
    {
        return json_encode($this->data, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_FORCE_OBJECT);
    }

    /**
     * Return the data for JSON serialization.
     */
    public function jsonSerialize(): mixed
    {
        return $this->data;
    }

    /**
     * Get the value stored at the given key. Will return null if the key does
     * not exist.
     */
    public function get(string $id): mixed
    {
        return $this->offsetGet($id);
    }

    /**
     * Check if the given key exists.
     */
    public function has(string $id): bool
    {
        return $this->offsetExists($id);
    }

    /**
     * Set the value at the given key.
     */
    public function set(string $id, mixed $value): void
    {
        $this->offsetSet($id, $value);
    }

    /**
     * Return the data as an array.
     *
     * @return array<string, mixed> $data
     */
    public function toArray(): array
    {
        return $this->__serialize();
    }

    /**
     * Return the data as an array iterator.
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->data);
    }

    /**
     * Return the number of items in the registry.
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Check if the given key exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    /**
     * Get the value stored at the given key. Will return null if the key does
     * not exist.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    /**
     * Set the value at the given key.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    /**
     * Remove the value at the given key.
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * Return the value at the given key as a string with only alpha characters.
     */
    public function asAlpha(string $key, string $fallback = ''): string
    {
        return preg_replace('/[^a-zA-Z]/', '', $this->asString($key, $fallback)) ?? '';
    }

    /**
     * Return the value at the given key as a string with only alphanumeric
     * characters.
     */
    public function asAlnum(string $key, string $fallback = ''): string
    {
        return preg_replace('/[^a-zA-Z0-9]/', '', $this->asString($key, $fallback)) ?? '';
    }

    /**
     * Return the value at the given key as a string with only numeric
     * characters.
     */
    public function asDigits(string $key, string $fallback = ''): string
    {
        return preg_replace('/[^0-9]/', '', $this->asString($key, $fallback)) ?? '';
    }

    /**
     * Return the value at the given key as a string.
     */
    public function asString(string $key, string $fallback = ''): string
    {
        $value = $this->offsetGet($key) ?? $fallback;
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }
        if (is_array($value)) {
            return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT);
        }
        return $fallback;
    }

    /**
     * Return the value at the given key as an integer.
     */
    public function asInt(string $key, int $fallback = 0): int
    {
        $value = $this->offsetGet($key) ?? $fallback;
        if (is_scalar($value)) {
            return (int) $value;
        }
        return $fallback;
    }

    /**
     * Return the value at the given key as a float.
     */
    public function asFloat(string $key, float $fallback = 0.0): float
    {
        $value = $this->offsetGet($key) ?? $fallback;
        if (is_scalar($value)) {
            return (float) $value;
        }
        return $fallback;
    }

    /**
     * Return the value at the given key as a boolean.
     */
    public function asBool(string $key, bool $fallback = false): bool
    {
        return (bool) ($this->offsetGet($key) ?? $fallback);
    }
}
