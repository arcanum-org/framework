<?php

declare(strict_types=1);

namespace Arcanum\Auth\Attribute;

/**
 * Marks a DTO as requiring specific roles on the authenticated Identity.
 *
 * Implicitly requires authentication — if no Identity is resolved,
 * the result is 401 (not 403). If an Identity is present but lacks
 * any of the listed roles, the result is 403 Forbidden.
 *
 * Multiple roles are OR'd — the identity needs at least one.
 *
 * ```php
 * #[RequiresRole('admin', 'moderator')]
 * final class BanUser { ... }
 * ```
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class RequiresRole
{
    /** @var list<string> */
    public readonly array $roles;

    public function __construct(string ...$roles)
    {
        $this->roles = array_values($roles);
    }
}
