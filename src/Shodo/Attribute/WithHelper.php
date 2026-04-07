<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Attribute;

/**
 * Declare a template helper that should be available when rendering this DTO.
 *
 * Repeat the attribute once per helper. The helper class is resolved from the
 * container at render time and merged onto the helper set produced by the
 * global registry and any domain-discovered helpers — attribute-declared
 * helpers win over both, because the DTO has explicitly named them.
 *
 * The alias defaults to the helper's class basename with a trailing
 * `Helper` stripped (`EnvCheckHelper` → `EnvCheck`). Pass `alias:` to
 * override.
 *
 * ```php
 * #[WithHelper(EnvCheckHelper::class)]
 * #[WithHelper(IncantationHelper::class, alias: 'Tip')]
 * final class Index { }
 * ```
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class WithHelper
{
    public function __construct(
        public readonly string $className,
        public readonly ?string $alias = null,
    ) {
    }

    /**
     * Resolve the alias for this attribute.
     *
     * Uses the explicit alias when provided, otherwise derives one from the
     * class basename by stripping a trailing `Helper` suffix.
     */
    public function resolvedAlias(): string
    {
        if ($this->alias !== null) {
            return $this->alias;
        }

        $basename = strrchr($this->className, '\\');
        $basename = $basename === false ? $this->className : substr($basename, 1);

        if (str_ends_with($basename, 'Helper') && $basename !== 'Helper') {
            return substr($basename, 0, -strlen('Helper'));
        }

        return $basename;
    }
}
