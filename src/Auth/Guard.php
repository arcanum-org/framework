<?php

declare(strict_types=1);

namespace Arcanum\Auth;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves an Identity from an HTTP request.
 *
 * Each guard implements a single authentication strategy (session,
 * bearer token, API key). Returns null if the guard can't authenticate
 * from this request — no token present, no session identity, etc.
 *
 * Guards never reject requests. That's authorization's job.
 */
interface Guard
{
    public function resolve(ServerRequestInterface $request): Identity|null;
}
