<?php

declare(strict_types=1);

namespace Arcanum\Forge;

use Arcanum\Toolkit\Strings;

/**
 * Request-scoped holder for the current domain.
 *
 * Same pattern as ActiveIdentity. Registered as a singleton in the
 * container. DomainContextMiddleware writes the domain on the way in;
 * Database reads it to scope the Model directory.
 */
final class DomainContext
{
    private string $domain = '';

    public function __construct(
        private readonly string $domainRoot = '',
    ) {
    }

    /**
     * Set the current domain.
     *
     * Called by Conveyor middleware with the domain extracted from the
     * DTO namespace (e.g., 'Shop' from App\Domain\Shop\Command\PlaceOrder).
     */
    public function set(string $domain): void
    {
        $this->domain = $domain;
    }

    /**
     * Get the current domain name.
     *
     * @throws \RuntimeException If no domain has been set.
     */
    public function get(): string
    {
        if ($this->domain === '') {
            throw new \RuntimeException(
                'No domain context. DomainContextMiddleware must run before Database is used.'
            );
        }

        return $this->domain;
    }

    /**
     * Whether a domain has been set.
     */
    public function has(): bool
    {
        return $this->domain !== '';
    }

    /**
     * Get the filesystem path to the current domain's Model directory.
     *
     * Combines the domain root (e.g., 'app/Domain') with the domain name
     * and 'Model/' to produce 'app/Domain/Shop/Model'.
     */
    public function modelPath(): string
    {
        return $this->domainRoot
            . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $this->get())
            . DIRECTORY_SEPARATOR . 'Model';
    }

    /**
     * Extract the domain segment from a fully qualified DTO class name.
     *
     * The domain is the namespace segment(s) after the configured root
     * and before Command\, Query\, or the class name itself.
     *
     * Examples (with root 'App\Domain'):
     *   App\Domain\Shop\Command\PlaceOrder → 'Shop'
     *   App\Domain\Admin\Users\Query\ListUsers → 'Admin\Users'
     *
     * @param string $namespace The domain namespace root (e.g., 'App\Domain').
     */
    public static function extractDomain(string $class, string $namespace): string
    {
        $relative = Strings::stripNamespacePrefix($class, $namespace);
        $segments = explode('\\', $relative);

        // Collect segments until we hit a CQRS boundary (Command/Query).
        $domain = [];
        $hitBoundary = false;
        foreach ($segments as $segment) {
            if (in_array($segment, ['Command', 'Query'], true)) {
                $hitBoundary = true;
                break;
            }
            $domain[] = $segment;
        }

        // If we hit the end without Command/Query, the last segment is the class name.
        if (!$hitBoundary) {
            array_pop($domain);
        }

        $result = implode('\\', $domain);

        if ($result === '') {
            throw new \RuntimeException(sprintf(
                "Cannot extract domain from class '%s' — no domain segment found.",
                $class,
            ));
        }

        return $result;
    }
}
