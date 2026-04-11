<?php

declare(strict_types=1);

namespace Arcanum\Flow\Conveyor;

/**
 * A dynamic Query DTO for handlers that don't define an explicit DTO class.
 *
 * When only a handler exists (e.g., ProductsHandler without Products),
 * the framework creates a Query from the query parameters and dispatches
 * it to the handler. Data is accessed via Gather's typed accessors.
 *
 * ```php
 * class ProductsHandler {
 *     public function __invoke(Query $query): array {
 *         $page = $query->asInt('page', 1);
 *         $category = $query->asString('category');
 *     }
 * }
 * ```
 */
final class Query extends DynamicDTO
{
}
