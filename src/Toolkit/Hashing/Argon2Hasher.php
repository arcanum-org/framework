<?php

declare(strict_types=1);

namespace Arcanum\Toolkit\Hashing;

/**
 * Argon2id password hashing via PHP's `password_hash`.
 *
 * Argon2id is the recommended algorithm — it combines Argon2i's
 * resistance to side-channel attacks with Argon2d's resistance
 * to GPU cracking.
 */
final class Argon2Hasher implements Hasher
{
    public function __construct(
        private readonly int $memoryCost = 65536,
        private readonly int $timeCost = 4,
        private readonly int $threads = 1,
    ) {
    }

    public function hash(string $value): string
    {
        return password_hash($value, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->memoryCost,
            'time_cost' => $this->timeCost,
            'threads' => $this->threads,
        ]);
    }

    public function verify(string $value, string $hash): bool
    {
        return password_verify($value, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->memoryCost,
            'time_cost' => $this->timeCost,
            'threads' => $this->threads,
        ]);
    }
}
