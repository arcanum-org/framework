<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Widgets\Query;

use Arcanum\Flow\Conveyor\Query;

/**
 * Handler-only fixture — no paired List DTO class exists.
 * Used to test dynamic DTO fallback in the router.
 */
final class ListHandler
{
    /** @return array<string, mixed> */
    public function __invoke(Query $query): array
    {
        return ['widgets' => [], 'page' => $query->get('page', 1)];
    }
}
