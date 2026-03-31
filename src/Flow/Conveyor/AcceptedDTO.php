<?php

declare(strict_types=1);

namespace Arcanum\Flow\Conveyor;

/**
 * Sentinel for handlers with a nullable return type that returned null.
 *
 * When a handler declares a nullable return type (e.g., `?OrderId`) and
 * returns null, the bus wraps the result in AcceptedDTO. The kernel maps
 * this to 202 Accepted — the request was accepted but processing may be
 * deferred or asynchronous.
 *
 * This is distinct from EmptyDTO (void handlers → 204 No Content).
 */
final class AcceptedDTO
{
}
