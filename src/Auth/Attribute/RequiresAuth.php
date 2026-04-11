<?php

declare(strict_types=1);

namespace Arcanum\Auth\Attribute;

/**
 * Marks a DTO as requiring an authenticated Identity.
 *
 * When AuthorizationGuard encounters this attribute on a DTO class
 * and no Identity has been resolved, the request is rejected:
 * - HTTP: 401 Unauthorized
 * - CLI: RuntimeException with exit code 1
 *
 * DTOs without this attribute are public — no auth check is performed.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class RequiresAuth
{
}
