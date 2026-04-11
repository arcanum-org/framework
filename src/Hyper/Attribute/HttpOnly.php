<?php

declare(strict_types=1);

namespace Arcanum\Hyper\Attribute;

/**
 * Marks a command/query DTO as HTTP-only.
 *
 * When TransportGuard middleware is active, CLI invocations of a
 * DTO with this attribute will be rejected with a clear error message.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class HttpOnly
{
}
