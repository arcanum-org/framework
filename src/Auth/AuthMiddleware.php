<?php

declare(strict_types=1);

namespace Arcanum\Auth;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

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
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $identity = $this->guard->resolve($request);

        if ($identity !== null) {
            $this->activeIdentity->set($identity);
            $this->logger?->info('Identity resolved', [
                'guard' => $this->resolvedGuardType(),
            ]);

            if ($this->isTokenAuthenticated()) {
                $request = $request->withAttribute('auth.token_authenticated', true);
            }
        } else {
            $this->logger?->debug('No identity resolved');
        }

        return $handler->handle($request);
    }

    private function resolvedGuardType(): string
    {
        if ($this->guard instanceof CompositeGuard) {
            $last = $this->guard->lastResolvedGuard();
            return $last !== null ? $this->guardName($last) : 'composite';
        }

        return $this->guardName($this->guard);
    }

    private function guardName(Guard $guard): string
    {
        $class = get_class($guard);
        $pos = strrpos($class, '\\');
        $short = $pos !== false ? substr($class, $pos + 1) : $class;
        return str_replace('Guard', '', $short) ?: $short;
    }

    private function isTokenAuthenticated(): bool
    {
        if ($this->guard instanceof TokenGuard) {
            return true;
        }

        if ($this->guard instanceof CompositeGuard) {
            return $this->guard->lastResolvedGuard() instanceof TokenGuard;
        }

        return false;
    }
}
