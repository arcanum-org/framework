<?php

declare(strict_types=1);

namespace Arcanum\Session;

/**
 * Request-scoped holder for the current Session.
 *
 * Registered as a singleton in the container. The SessionMiddleware
 * writes the Session on the way in; downstream code reads it.
 * This avoids circular dependency issues with the container
 * (the Session doesn't exist until middleware runs).
 */
final class ActiveSession
{
    private Session|null $session = null;

    public function set(Session $session): void
    {
        $this->session = $session;
    }

    public function get(): Session
    {
        if ($this->session === null) {
            throw new SessionNotStarted();
        }

        return $this->session;
    }

    public function has(): bool
    {
        return $this->session !== null;
    }
}
