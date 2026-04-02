<?php

declare(strict_types=1);

namespace Arcanum\Auth;

/**
 * Request-scoped holder for the authenticated Identity.
 *
 * Same pattern as ActiveSession. Registered as a singleton in the
 * container. AuthMiddleware (HTTP) or CliAuthResolver (CLI) writes
 * the Identity on the way in; AuthorizationGuard and handlers read it.
 */
final class ActiveIdentity
{
    private Identity|null $identity = null;

    public function set(Identity $identity): void
    {
        $this->identity = $identity;
    }

    /**
     * Get the resolved Identity.
     *
     * @throws \RuntimeException If no identity has been resolved.
     */
    public function get(): Identity
    {
        if ($this->identity === null) {
            throw new \RuntimeException(
                'No authenticated identity. Use resolve() to check without throwing.'
            );
        }

        return $this->identity;
    }

    /**
     * Get the resolved Identity, or null if absent.
     *
     * Used by AuthorizationGuard to distinguish "not authenticated"
     * from "authenticated" without catching exceptions.
     */
    public function resolve(): Identity|null
    {
        return $this->identity;
    }

    public function has(): bool
    {
        return $this->identity !== null;
    }
}
