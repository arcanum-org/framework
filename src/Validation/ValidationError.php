<?php

declare(strict_types=1);

namespace Arcanum\Validation;

/**
 * A single validation failure on a single field.
 */
final class ValidationError
{
    public function __construct(
        public readonly string $field,
        public readonly string $message,
    ) {
    }
}
