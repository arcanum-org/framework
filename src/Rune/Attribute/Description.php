<?php

declare(strict_types=1);

namespace Arcanum\Rune\Attribute;

/**
 * Provides a human-readable description for CLI help output.
 *
 * Can be applied to a DTO class (describes the command/query) or to
 * individual constructor parameters (describes the argument). Ignored
 * by HTTP — purely a CLI presentation concern.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PARAMETER)]
final class Description
{
    public function __construct(
        public readonly string $text,
    ) {
    }
}
