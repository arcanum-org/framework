<?php

declare(strict_types=1);

namespace Arcanum\Toolkit;

/**
 * Hex encoding utilities.
 *
 * Provides encoding and validation for lowercase hex strings.
 * Used by Random for hex-encoded random values, and by Session
 * for validating session IDs and CSRF tokens.
 */
final class Hex
{
    /**
     * Encode raw bytes as a lowercase hex string.
     */
    public static function encode(string $bytes): string
    {
        return bin2hex($bytes);
    }

    /**
     * Check whether a string is a valid hex encoding of the given byte length.
     *
     * @param positive-int $bytes Expected byte count (hex length = bytes * 2).
     */
    public static function isValid(string $value, int $bytes): bool
    {
        return preg_match('/\A[0-9a-f]{' . ($bytes * 2) . '}\z/', $value) === 1;
    }
}
