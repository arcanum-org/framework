<?php

declare(strict_types=1);

namespace Arcanum\Cabinet;

/**
 * Provider Registry Interface
 * ---------------------------
 *
 * The provider registry interface defines the methods required to register
 * providers with the application container.
 */
interface ProviderRegistry
{
    /**
     * Register a service provider.
     *
     * @param string $serviceName
     */
    public function provider(string $serviceName, string|Provider $provider): void;
}
