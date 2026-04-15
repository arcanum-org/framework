<?php

declare(strict_types=1);

namespace Arcanum\Auth;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves identity from a Bearer token in the Authorization header.
 *
 * Extracts the token from "Authorization: Bearer <token>" and calls
 * the IdentityProvider to validate it and return the Identity.
 * The provider handles token lookup, validation, and expiry — the
 * guard only extracts and delegates.
 *
 * Returns null if no Authorization header, not a Bearer scheme,
 * or the provider can't validate the token.
 */
final class TokenGuard implements Guard
{
    public function __construct(
        private readonly IdentityProvider $provider,
    ) {
    }

    public function resolve(ServerRequestInterface $request): Identity|null
    {
        $header = $request->getHeaderLine('Authorization');

        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = substr($header, 7);

        if ($token === '') {
            return null;
        }

        return $this->provider->findByToken($token);
    }
}
