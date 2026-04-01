<?php

declare(strict_types=1);

namespace Arcanum\Toolkit\Hashing;

/**
 * Bcrypt password hashing via PHP's `password_hash`.
 *
 * Note: bcrypt silently truncates input at 72 bytes.
 * For passwords longer than 72 bytes, consider Argon2Hasher.
 */
final class BcryptHasher implements Hasher
{
    public function __construct(
        private readonly int $cost = 12,
    ) {
    }

    public function hash(string $value): string
    {
        return password_hash($value, PASSWORD_BCRYPT, [
            'cost' => $this->cost,
        ]);
    }

    public function verify(string $value, string $hash): bool
    {
        return password_verify($value, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, [
            'cost' => $this->cost,
        ]);
    }
}
