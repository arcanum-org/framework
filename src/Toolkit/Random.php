<?php

declare(strict_types=1);

namespace Arcanum\Toolkit;

/**
 * Cryptographically secure random value generation.
 *
 * All methods delegate to PHP's `random_bytes()`, which uses the OS CSPRNG.
 * These are the building blocks for CSRF tokens, API keys, session IDs, and nonces.
 */
final class Random
{
    /**
     * Generate raw random bytes.
     *
     * @param positive-int $length
     */
    public static function bytes(int $length): string
    {
        return random_bytes($length);
    }

    /**
     * Generate a hex-encoded random string.
     *
     * Output length is `$bytes * 2` characters.
     *
     * @param positive-int $bytes
     */
    public static function hex(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Generate a URL-safe base64-encoded random string (no padding).
     *
     * @param positive-int $bytes
     */
    public static function base64url(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
