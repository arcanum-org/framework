<?php

declare(strict_types=1);

namespace Arcanum\Toolkit\Signing;

/**
 * Message authentication (signing) contract.
 *
 * Used by signed URLs, signed cookies, CSRF tokens, and any
 * tamper-detection scenario.
 */
interface Signer
{
    /**
     * Produce a MAC (message authentication code) for the payload.
     */
    public function sign(string $payload): string;

    /**
     * Verify that a MAC matches the payload.
     *
     * Uses constant-time comparison to prevent timing attacks.
     */
    public function verify(string $payload, string $signature): bool;
}
