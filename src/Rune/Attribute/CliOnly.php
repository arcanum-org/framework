<?php

declare(strict_types=1);

namespace Arcanum\Rune\Attribute;

/**
 * Marks a command/query DTO as CLI-only.
 *
 * When TransportGuard middleware is active, HTTP requests to a
 * DTO with this attribute will be rejected with 405 Method Not Allowed.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class CliOnly
{
}
