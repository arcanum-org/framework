<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Gather\IgnoreCaseRegistry;

class Headers extends IgnoreCaseRegistry
{
    /**
     * Construct a Registry of headers.
     *
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $cleanData = [];
        foreach ($data as $header => $values) {
            $cleanData[$this->cleanHeader($header)] = $this->cleanValues($values);
        }
        parent::__construct($cleanData);
    }

    /**
     * Return the headers for serialization.
     *
     * Ensures that the first header is the Host header, if it is present.
     *
     * @return array<string, string[]> $data
     */
    public function __serialize(): array
    {
        /**
         * We can be sure of this array structure, because we guard against
         * non string values in the setters.
         *
         * @var array<string, string[]> $headers
         */
        $headers = parent::__serialize();
        $hostKey = $this->getKey('Host');

        if (isset($headers[$hostKey])) {
            $headers = [$hostKey => $headers[$hostKey]] + $headers;
        }

        return $headers;
    }

    /**
     * Set the value at the given header. The header is case-insensitive.
     */
    public function set(string $header, mixed $value): void
    {
        parent::set($this->cleanHeader($header), $this->cleanValues($value));
    }

    /**
     * Set the value at the given header. The header is case-insensitive.
     */
    public function offsetSet(mixed $header, mixed $value): void
    {
        parent::offsetSet($this->cleanHeader($header), $this->cleanValues($value));
    }

    /**
     * Ensure the header name confirms.
     */
    protected function cleanHeader(mixed $header): string
    {
        if (!is_string($header)) {
            throw new \InvalidArgumentException('Header names must be strings.');
        }

        if (!preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/D', $header)) {
            throw new \InvalidArgumentException("Invalid header name: $header");
        }

        return $header;
    }

    /**
     * @return string[]
     */
    protected function cleanValues(mixed $values): array
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        foreach ($values as $i => $item) {
            if (!is_string($item)) {
                throw new \InvalidArgumentException('Header values must be arrays of strings.');
            }

            $values[$i] = trim($item, "\t ");

            if ($values[$i] === '') {
                throw new \InvalidArgumentException('Header values must not be empty.');
            }

            if (preg_match("/\r\n|\r|\n/", $values[$i])) {
                throw new \InvalidArgumentException(
                    'Header values must not contain CR or LF characters.'
                );
            }

            if (!preg_match('/^[\x20\x09\x21-\x7E\x80-\xFF]*$/D', $values[$i])) {
                throw new \InvalidArgumentException("Invalid header value: $item");
            }
        }

        return $values;
    }
}
