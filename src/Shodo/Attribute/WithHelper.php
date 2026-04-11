<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Attribute;

/**
 * Declare a template helper that should be available when rendering this DTO.
 *
 * Repeat the attribute once per helper. Both the helper class and its
 * template alias are explicit — no auto-derivation. The class is resolved
 * from the container at render time and merged onto the helper set
 * produced by the global registry and any domain-discovered helpers.
 * Attribute-declared helpers win over both because the DTO has explicitly
 * named them.
 *
 * ```php
 * #[WithHelper(EnvCheckHelper::class, 'Env')]
 * #[WithHelper(IncantationHelper::class, 'Tip')]
 * final class Index { }
 * ```
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class WithHelper
{
    public function __construct(
        public readonly string $className,
        public readonly string $alias,
    ) {
    }
}
