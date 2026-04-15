<?php

declare(strict_types=1);

namespace Arcanum\Auth;

use Arcanum\Session\ActiveSession;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves identity from the session.
 *
 * Reads the identity ID stored in the session by a previous login,
 * then calls the IdentityProvider to look up the full Identity.
 *
 * Returns null if the session has no identity or the provider
 * can't find the user.
 */
final class SessionGuard implements Guard
{
    public function __construct(
        private readonly ActiveSession $session,
        private readonly IdentityProvider $provider,
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

        return $this->provider->findById($identityId);
    }
}
