<?php

declare(strict_types=1);

namespace Arcanum\Cabinet;

use Psr\Container\ContainerInterface;

/**
 * Arcanum Apllication Interface
 * ------------------------------
 *
 * The application interface is the primary service container for the
 * Arcanum framework. It extends the PSR-11 container interface and
 * provides additional functionality for registering services, factories,
 * providers, decorators, prototypes, and middleware.
 *
 * @extends \ArrayAccess<string, mixed>
 */
interface Application extends
    \ArrayAccess,
    ContainerInterface,
    DecoratorRegistry,
    FactoryRegistry,
    InstanceRegistry,
    MiddlewareRegistry,
    PrototypeRegistry,
    ProviderRegistry,
    ServiceRegistry
{
}
