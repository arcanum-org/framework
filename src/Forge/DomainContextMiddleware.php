<?php

declare(strict_types=1);

namespace Arcanum\Forge;

use Arcanum\Flow\Continuum\Progression;

/**
 * Conveyor middleware that sets the domain context from the DTO namespace.
 *
 * Extracts the domain segment from the dispatched DTO's class name and
 * writes it to DomainContext before the handler runs. This scopes
 * Database::model to the correct domain's Model directory.
 */
final class DomainContextMiddleware implements Progression
{
    public function __construct(
        private readonly DomainContext $context,
        private readonly string $namespace,
    ) {
    }

    /**
     * @param callable(): void $next
     */
    public function __invoke(object $payload, callable $next): void
    {
        $domain = DomainContext::extractDomain($payload::class, $this->namespace);

        if ($domain !== '') {
            $this->context->set($domain);
        }

        $next();
    }
}
