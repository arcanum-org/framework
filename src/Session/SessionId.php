<?php

declare(strict_types=1);

namespace Arcanum\Session;

use Arcanum\Toolkit\Random;

/**
 * Cryptographically secure session identifier.
 *
 * Wraps a hex-encoded random string. Provides generation and basic
 * format validation so session IDs are never raw user input.
 */
final class SessionId
{
    private const BYTES = 20;

    public readonly string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Generate a fresh session ID.
     */
    public static function generate(): self
    {
        return new self(Random::hex(self::BYTES));
    }

    /**
     * Restore a session ID from a cookie value.
     *
     * Returns null if the value is not a valid session ID format.
     */
    public static function fromString(string $value): self|null
    {
        if (preg_match('/\A[0-9a-f]{' . (self::BYTES * 2) . '}\z/', $value) !== 1) {
            return null;
        }

        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
