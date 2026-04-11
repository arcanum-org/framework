<?php

declare(strict_types=1);

namespace Arcanum\Flow\Conveyor;

/**
 * Wraps arbitrary query handler return values so they can
 * flow through the Conveyor pipeline, which requires objects.
 *
 * Handlers can return arrays, scalars, or any mixed value.
 * The QueryResult preserves the original data for rendering.
 */
final class QueryResult
{
    public function __construct(
        public readonly mixed $data,
    ) {
    }
}
