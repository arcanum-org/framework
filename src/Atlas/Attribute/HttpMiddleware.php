<?php

declare(strict_types=1);

namespace Arcanum\Atlas\Attribute;

/**
 * Declares PSR-15 HTTP middleware on a DTO class.
 *
 * Middleware declared via this attribute runs at the HTTP layer,
 * wrapping the request handler with a PSR-15 middleware onion.
 * It can short-circuit (e.g., return 401) before the handler runs.
 *
 * @example
 *   #[HttpMiddleware(RequireAuth::class)]
 *   final class PlaceOrder { ... }
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class HttpMiddleware
{
    public function __construct(
        public readonly string $class,
    ) {
    }
}
