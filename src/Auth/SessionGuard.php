<?php

declare(strict_types=1);

namespace Arcanum\Auth;

use Arcanum\Session\ActiveSession;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves identity from the session.
 *
 * Reads the identity ID stored in the session by a previous login,
 * then calls the app-provided resolver to look up the full Identity.
 * The resolver is the bridge to the app's user storage — database,
 * cache, config file, whatever.
 *
 * Returns null if the session has no identity or the resolver
 * can't find the user.
 */
final class SessionGuard implements Guard
{
    /**
     * @param \Closure(string): (Identity|null) $resolver
     */
    public function __construct(
        private readonly ActiveSession $session,
        private readonly \Closure $resolver,
    ) {
    }

    public function resolve(ServerRequestInterface $request): Identity|null
    {
        if (!$this->session->has()) {
            return null;
        }

        $identityId = $this->session->get()->identityId();

        if ($identityId === '') {
            return null;
        }

        return ($this->resolver)($identityId);
    }
}
