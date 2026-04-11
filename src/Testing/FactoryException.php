<?php

declare(strict_types=1);

namespace Arcanum\Testing;

use Arcanum\Glitch\ArcanumException;

/**
 * Thrown when `Factory::make()` cannot synthesize a value for a constructor
 * parameter — typically because the parameter is constrained by a rule that
 * is not auto-generatable (`#[Pattern]`, `#[Callback]`), declares an
 * unsupported type (union, intersection, untyped), or names an unknown class.
 *
 * The fix is always the same: pass an explicit override for that parameter.
 */
final class FactoryException extends \RuntimeException implements ArcanumException
{
    public function getTitle(): string
    {
        return 'Test Factory Synthesis Failed';
    }

    public function getSuggestion(): string
    {
        return 'Pass an explicit override for the parameter via the second argument to Factory::make().';
    }
}
