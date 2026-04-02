<?php

declare(strict_types=1);

namespace Arcanum\Session;

use Arcanum\Toolkit\Random;

/**
 * CSRF token value object.
 *
 * Generates and validates tokens used by CsrfMiddleware.
 * Tokens are 64-character hex strings (32 bytes of entropy).
 */
final class CsrfToken
{
    private const BYTES = 32;

    public readonly string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Generate a fresh CSRF token.
     */
    public static function generate(): self
    {
        return new self(Random::hex(self::BYTES));
    }

    /**
     * Restore a token from session storage.
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Constant-time comparison to prevent timing attacks.
     */
    public function matches(string $input): bool
    {
        return hash_equals($this->value, $input);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
