<?php

declare(strict_types=1);

namespace Arcanum\Auth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware that resolves the authenticated Identity.
 *
 * Calls the configured Guard to resolve an Identity from the request.
 * If an Identity is returned, it's stored in the ActiveIdentity holder.
 * If not, ActiveIdentity remains empty — the middleware never rejects.
 *
 * Authorization (deciding whether the route requires auth) is handled
 * downstream by AuthorizationGuard in the Conveyor pipeline.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Guard $guard,
        private readonly ActiveIdentity $activeIdentity,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $identity = $this->guard->resolve($request);

        if ($identity !== null) {
            $this->activeIdentity->set($identity);
        }

        return $handler->handle($request);
    }
}
