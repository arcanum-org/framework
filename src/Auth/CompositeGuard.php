<?php

declare(strict_types=1);

namespace Arcanum\Auth;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Tries multiple guards in order, returns the first resolved Identity.
 *
 * For apps that serve both HTML (session auth) and API (token auth),
 * the composite guard tries session first, then token. The first
 * guard that returns a non-null Identity wins.
 */
final class CompositeGuard implements Guard
{
    /** @var list<Guard> */
    private readonly array $guards;

    private Guard|null $lastResolved = null;

    public function __construct(Guard ...$guards)
    {
        $this->guards = array_values($guards);
    }

    public function resolve(ServerRequestInterface $request): Identity|null
    {
        $this->lastResolved = null;

        foreach ($this->guards as $guard) {
            $identity = $guard->resolve($request);

            if ($identity !== null) {
                $this->lastResolved = $guard;
                return $identity;
            }
        }

        return null;
    }

    /**
     * The inner guard that resolved the last identity, or null if none matched.
     *
     * Used by AuthMiddleware to determine the authentication method
     * (e.g., whether a TokenGuard resolved, making CSRF unnecessary).
     */
    public function lastResolvedGuard(): Guard|null
    {
        return $this->lastResolved;
    }
}
