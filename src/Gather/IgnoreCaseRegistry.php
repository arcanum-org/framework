<?php

declare(strict_types=1);

namespace Arcanum\Gather;

class IgnoreCaseRegistry extends Registry
{
    /**
     * Map of lowercase keys to original keys.
     *
     * @var array<string, string>
     */
    protected array $keyMap = [];

    /**
     * Construct a Registry.
     *
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        parent::__construct($data);

        foreach (array_keys($data) as $key) {
            $this->keyMap[strtolower($key)] = $key;
        }
    }

    /**
     * Get the original key for the given key.
     */
    public function getKey(string $key): string
    {
        return $this->keyMap[strtolower($key)] ?? $key;
    }

    /**
     * Get the value stored at the given key. The key is case-insensitive.
     *
     * Will return null if the key does not exist.
     */
    public function get(string $id): mixed
    {
        $id = $this->keyMap[strtolower($id)] ?? $id;

        return parent::get($id);
    }

    /**
     * Check if the given key exists. The key is case-insensitive.
     */
    public function has(string $id): bool
    {
        $id = $this->keyMap[strtolower($id)] ?? $id;

        return parent::has($id);
    }

    /**
     * Set the value at the given key. The key is case-insensitive.
     */
    public function set(string $id, mixed $value): void
    {
        $lowerID = strtolower($id);
        if (!isset($this->keyMap[$lowerID])) {
            $this->keyMap[$lowerID] = $id;
        }
        parent::set($this->keyMap[$lowerID], $value);
    }

    /**
     * Check if the given key exists. The key is case-insensitive.
     */
    public function offsetExists(mixed $offset): bool
    {
        $offset = $this->keyMap[strtolower($offset)] ?? $offset;

        return parent::offsetExists($offset);
    }

    /**
     * Get the value stored at the given key. The key is case-insensitive.
     *
     * Will return null if the key does not exist.
     */
    public function offsetGet(mixed $offset): mixed
    {
        $offset = $this->keyMap[strtolower($offset)] ?? $offset;

        return parent::offsetGet($offset);
    }

    /**
     * Set the value at the given key. The key is case-insensitive.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $lowerOffset = strtolower((string)$offset);
        if (!isset($this->keyMap[$lowerOffset])) {
            $this->keyMap[$lowerOffset] = (string)$offset;
        }

        parent::offsetSet($this->keyMap[$lowerOffset], $value);
    }

    /**
     * Remove the value at the given key. The key is case-insensitive.
     */
    public function offsetUnset(mixed $offset): void
    {
        $lowerOffset = strtolower((string)$offset);
        $offset = $this->keyMap[$lowerOffset] ?? $offset;
        unset($this->keyMap[$lowerOffset]);
        parent::offsetUnset($offset);
    }

    /**
     * Return the value at the given key as a string. The key is
     * case-insensitive.
     */
    public function asString(string $key, string $fallback = ''): string
    {
        $key = $this->keyMap[strtolower($key)] ?? $key;

        return parent::asString($key, $fallback);
    }

    /**
     * Return the value at the given key as an integer. The key is
     * case-insensitive.
     */
    public function asInt(string $key, int $fallback = 0): int
    {
        $key = $this->keyMap[strtolower($key)] ?? $key;

        return parent::asInt($key, $fallback);
    }

    /**
     * Return the value at the given key as a float. The key is
     * case-insensitive.
     */
    public function asFloat(string $key, float $fallback = 0.0): float
    {
        $key = $this->keyMap[strtolower($key)] ?? $key;

        return parent::asFloat($key, $fallback);
    }

    /**
     * Return the value at the given key as a boolean. The key is
     * case-insensitive.
     */
    public function asBool(string $key, bool $fallback = false): bool
    {
        $key = $this->keyMap[strtolower($key)] ?? $key;

        return parent::asBool($key, $fallback);
    }

    /**
     * Return the data for serialization.
     *
     * @return array<string, mixed> $data
     */
    public function __serialize(): array
    {
        $data = $this->data;
        $data['@!*__keyMap'] = $this->keyMap;
        return $data;
    }

    /**
     * Accept the data for unserialization.
     *
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->data = $data;
        if (isset($data['@!*__keyMap']) && is_array($data['@!*__keyMap'])) {
            $keyMap = [];
            foreach ($data['@!*__keyMap'] as $k => $v) {
                if (is_string($k) && is_string($v)) {
                    $keyMap[$k] = $v;
                }
            }
            $this->keyMap = $keyMap;
            unset($this->data['@!*__keyMap']);
        }
    }

    /**
     * Return the data as an array.
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        unset($data['@!*__keyMap']);
        return $data;
    }
}
