<?php

declare(strict_types=1);

namespace Arcanum\Cabinet;

/**
 * Service Registry Interface
 * --------------------------
 *
 * The service registry interface defines the methods required to register
 * services with the application container.
 */
interface ServiceRegistry
{
    /**
     * Register a service
     *
     * @param string $serviceName
     * @param class-string|null $implementation
     */
    public function service(string $serviceName, string|null $implementation = null): void;

    /**
     * Register a service while defining its dependencies.
     *
     * @param string $serviceName
     * @param class-string[] $dependencies
     */
    public function serviceWith(string $serviceName, array $dependencies): void;

    /**
     * Specify a constructor dependency for a service.
     *
     * Tells the resolver that when building $when and it needs $needs,
     * use $give instead of auto-resolving. Useful for scalar constructor
     * params (strings, bools, ints) that Codex can't auto-wire.
     *
     * @param class-string|array<class-string> $when
     */
    public function specify(string|array $when, string $needs, mixed $give): void;
}
