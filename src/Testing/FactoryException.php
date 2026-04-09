<?php

declare(strict_types=1);

namespace Arcanum\Testing;

/**
 * Thrown when `Factory::make()` cannot synthesize a value for a constructor
 * parameter — typically because the parameter is constrained by a rule that
 * is not auto-generatable (`#[Pattern]`, `#[Callback]`), declares an
 * unsupported type (union, intersection, untyped), or names an unknown class.
 *
 * The fix is always the same: pass an explicit override for that parameter.
 */
final class FactoryException extends \RuntimeException
{
}
