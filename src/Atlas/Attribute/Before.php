<?php

declare(strict_types=1);

namespace Arcanum\Atlas\Attribute;

/**
 * Declares a Conveyor Progression that runs before the handler.
 *
 * Middleware declared via this attribute operates on the DTO payload
 * at the Conveyor layer — it can validate, sanitize, or enrich the
 * DTO before the handler receives it.
 *
 * @example
 *   #[Before(ValidateInput::class)]
 *   final class PlaceOrder { ... }
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class Before
{
    public function __construct(
        public readonly string $class,
    ) {
    }
}
