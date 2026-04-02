<?php

declare(strict_types=1);

namespace Arcanum\Auth\Attribute;

use Arcanum\Auth\Policy;

/**
 * Marks a DTO as requiring a Policy check.
 *
 * The AuthorizationGuard resolves the Policy from the container
 * and calls authorize(). Returning false results in 403 Forbidden.
 *
 * Implicitly requires authentication — policies always receive an Identity.
 *
 * Multiple #[RequiresPolicy] attributes are supported — all must pass.
 *
 * ```php
 * #[RequiresPolicy(OwnsPostPolicy::class)]
 * final class EditPost { ... }
 * ```
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class RequiresPolicy
{
    /**
     * @param class-string<Policy> $policyClass
     */
    public function __construct(
        public readonly string $policyClass,
    ) {
    }
}
