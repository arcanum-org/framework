<?php

declare(strict_types=1);

namespace Arcanum\Toolkit\Hashing;

/**
 * Password hashing contract.
 *
 * Wraps PHP's `password_hash` / `password_verify` / `password_needs_rehash`
 * behind a clean interface for dependency injection and testing.
 */
interface Hasher
{
    /**
     * Hash a plaintext value.
     */
    public function hash(string $value): string;

    /**
     * Verify a plaintext value against a hash.
     */
    public function verify(string $value, string $hash): bool;

    /**
     * Check if a hash needs to be re-hashed (e.g., after an algorithm or cost change).
     */
    public function needsRehash(string $hash): bool;
}
