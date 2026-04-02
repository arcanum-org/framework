<?php

declare(strict_types=1);

namespace Arcanum\Auth;

use Arcanum\Auth\Attribute\RequiresAuth;
use Arcanum\Auth\Attribute\RequiresPolicy;
use Arcanum\Auth\Attribute\RequiresRole;
use Arcanum\Flow\Continuum\Progression;
use Arcanum\Flow\Conveyor\HandlerProxy;
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\StatusCode;
use Arcanum\Ignition\Transport;
use Psr\Container\ContainerInterface;

/**
 * Conveyor before-middleware that enforces authentication and role requirements.
 *
 * Reads #[RequiresAuth], #[RequiresRole], and #[RequiresPolicy] attributes
 * from the DTO class. Same pattern as TransportGuard: resolve DTO class,
 * reflect for attributes, throw on violation, call $next() on success.
 *
 * - No auth attributes → public, passes through.
 * - #[RequiresAuth] without identity → 401 (HTTP) or RuntimeException (CLI).
 * - #[RequiresRole] without identity → 401 (implicit auth requirement).
 * - #[RequiresRole] with identity but wrong roles → 403 (HTTP) or RuntimeException (CLI).
 * - #[RequiresPolicy] without identity → 401 (implicit auth requirement).
 * - #[RequiresPolicy] denied → 403 (HTTP) or RuntimeException (CLI).
 */
final class AuthorizationGuard implements Progression
{
    public function __construct(
        private readonly ActiveIdentity $activeIdentity,
        private readonly Transport $transport,
        private readonly ContainerInterface $container,
    ) {
    }

    public function __invoke(object $payload, callable $next): void
    {
        $class = $this->resolveDtoClass($payload);

        if ($class !== null) {
            $this->check($class, $payload);
        }

        $next();
    }

    /**
     * @param class-string $class
     */
    private function check(string $class, object $payload): void
    {
        $ref = new \ReflectionClass($class);

        $requiresAuth = $ref->getAttributes(RequiresAuth::class) !== [];
        $roleAttributes = $ref->getAttributes(RequiresRole::class);
        $policyAttributes = $ref->getAttributes(RequiresPolicy::class);

        // No auth attributes at all → public, passes through.
        if (!$requiresAuth && $roleAttributes === [] && $policyAttributes === []) {
            return;
        }

        $identity = $this->activeIdentity->resolve();

        // No identity → 401.
        if ($identity === null) {
            $this->reject(
                StatusCode::Unauthorized,
                'Authentication required.',
            );
        }

        // Check roles if specified.
        if ($roleAttributes !== []) {
            /** @var RequiresRole $requirement */
            $requirement = $roleAttributes[0]->newInstance();
            $this->checkRoles($identity, $requirement->roles);
        }

        // Check policies if specified.
        foreach ($policyAttributes as $attribute) {
            /** @var RequiresPolicy $requirement */
            $requirement = $attribute->newInstance();
            $this->checkPolicy($identity, $payload, $requirement->policyClass);
        }
    }

    /**
     * @param list<string> $requiredRoles
     */
    private function checkRoles(Identity $identity, array $requiredRoles): void
    {
        $identityRoles = $identity->roles();

        foreach ($requiredRoles as $role) {
            if (in_array($role, $identityRoles, true)) {
                return;
            }
        }

        $this->reject(
            StatusCode::Forbidden,
            sprintf(
                'Access denied: requires role %s.',
                implode(' or ', array_map(fn(string $r) => "'$r'", $requiredRoles)),
            ),
        );
    }

    /**
     * @param class-string<Policy> $policyClass
     */
    private function checkPolicy(Identity $identity, object $dto, string $policyClass): void
    {
        /** @var Policy $policy */
        $policy = $this->container->get($policyClass);

        if (!$policy->authorize($identity, $dto)) {
            $this->reject(
                StatusCode::Forbidden,
                'Access denied by policy.',
            );
        }
    }

    /**
     * @return never
     */
    private function reject(StatusCode $status, string $message): void
    {
        if ($this->transport === Transport::Http) {
            throw new HttpException($status, $message);
        }

        throw new \RuntimeException($message);
    }

    /**
     * @return class-string|null
     */
    private function resolveDtoClass(object $payload): string|null
    {
        $class = $payload instanceof HandlerProxy
            ? $payload->handlerBaseName()
            : get_class($payload);

        if (!class_exists($class)) {
            return null;
        }

        return $class;
    }
}
