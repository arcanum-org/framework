<?php

declare(strict_types=1);

namespace Arcanum\Validation;

use Arcanum\Flow\Continuum\Progression;
use Arcanum\Flow\Conveyor\HandlerProxy;

/**
 * Conveyor before-middleware that validates DTOs before handler dispatch.
 *
 * Runs validation rules declared as attributes on DTO constructor parameters.
 * On failure, throws ValidationException which each kernel renders appropriately
 * (422 on HTTP, error message on CLI).
 *
 * HandlerProxy payloads are skipped — dynamic DTOs don't have constructor
 * params to validate.
 */
final class ValidationGuard implements Progression
{
    public function __construct(
        private readonly Validator $validator = new Validator(),
    ) {
    }

    public function __invoke(object $payload, callable $next): void
    {
        $dto = $this->resolveDto($payload);

        if ($dto !== null) {
            $this->validator->validate($dto);
        }

        $next();
    }

    private function resolveDto(object $payload): object|null
    {
        // HandlerProxy instances carry data in a Registry, not constructor params.
        if ($payload instanceof HandlerProxy) {
            return null;
        }

        return $payload;
    }
}
