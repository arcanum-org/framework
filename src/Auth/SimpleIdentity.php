<?php

declare(strict_types=1);

namespace Arcanum\Auth;

/**
 * Concrete Identity for guards that resolve from tokens or sessions.
 *
 * Carries an ID and a list of roles. Apps with richer user models
 * should implement Identity directly on their User class.
 */
final class SimpleIdentity implements Identity
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private readonly string $id,
        private readonly array $roles = [],
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function roles(): array
    {
        return $this->roles;
    }
}
