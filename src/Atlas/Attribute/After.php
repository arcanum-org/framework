<?php

declare(strict_types=1);

namespace Arcanum\Atlas\Attribute;

/**
 * Declares a Conveyor Progression that runs after the handler.
 *
 * Middleware declared via this attribute operates on the result object
 * at the Conveyor layer — it can transform, enrich, or audit the
 * handler's return value.
 *
 * @example
 *   #[After(AuditLog::class)]
 *   final class PlaceOrder { ... }
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class After
{
    public function __construct(
        public readonly string $class,
    ) {
    }
}
