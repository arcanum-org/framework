<?php

declare(strict_types=1);

namespace Arcanum\Gather;

/**
 * Configuration is a special registry that can be used to store
 * configuration variables. When storing arrays, this Registry will
 * store them under the key as-is, but will also store each element
 * of the array under the key with a ".", for example:
 *
 * ```php
 * $config = new Configuration();
 * $config->set('foo', ['bar' => 'baz']);
 *
 * // $config->get('foo') === ['bar' => 'baz']
 * // $config->get('foo.bar') === 'baz'
 * ```
 */
class Configuration extends Registry
{
    public function __construct(
        protected array $data = [],
    ) {
    }

    /**
     * Get the value stored at the given key. Will return null if the key does
     * not exist.
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (array_key_exists($offset, $this->data)) {
            return $this->data[$offset];
        }

        $parts = explode('.', $offset);
        $value = $this->data;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }

            $value = $value[$part];
        }

        return $value;
    }

    /**
     * Set the value at the given key.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $parts = explode('.', (string)$offset);
        $data = &$this->data;

        foreach ($parts as $part) {
            if (!is_array($data)) {
                $data = [];
            }

            if (!array_key_exists($part, $data)) {
                $data[$part] = [];
            }

            $data = &$data[$part];
        }

        $data = $value;
    }

    /**
     * Check if the given key exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        if (array_key_exists($offset, $this->data)) {
            return true;
        }

        $parts = explode('.', (string)$offset);
        $value = $this->data;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return false;
            }

            $value = $value[$part];
        }

        return true;
    }

    /**
     * Remove the value at the given key.
     */
    public function offsetUnset(mixed $offset): void
    {
        if (array_key_exists($offset, $this->data)) {
            unset($this->data[$offset]);
            return;
        }

        // get all except the last part
        $parts = explode('.', (string)$offset);
        $last = array_pop($parts);
        $value = &$this->data;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return;
            }

            $value = &$value[$part];
        }

        if (is_array($value)) {
            unset($value[$last]);
        }
    }
}
