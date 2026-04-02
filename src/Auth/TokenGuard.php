<?php

declare(strict_types=1);

namespace Arcanum\Auth;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves identity from a Bearer token in the Authorization header.
 *
 * Extracts the token from "Authorization: Bearer <token>" and calls
 * the app-provided resolver to validate it and return the Identity.
 * The resolver handles token lookup, validation, and expiry — the
 * guard only extracts and delegates.
 *
 * Returns null if no Authorization header, not a Bearer scheme,
 * or the resolver can't validate the token.
 */
final class TokenGuard implements Guard
{
    /**
     * @param \Closure(string): (Identity|null) $resolver
     */
    public function __construct(
        private readonly \Closure $resolver,
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

        return ($this->resolver)($token);
    }
}
