<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Helpers;

/**
 * Template helper for array/collection operations.
 *
 * Usage in templates:
 *   {{ Arr::count($items) }}
 *   {{ Arr::join($items, ', ') }}
 *   {{ Arr::first($items) }}
 */
final class ArrHelper
{
    /**
     * @param array<mixed>|\Countable $items
     */
    public function count(array|\Countable $items): int
    {
        return count($items);
    }

    /**
     * @param array<string> $items
     */
    public function join(array $items, string $separator): string
    {
        return implode($separator, $items);
    }

    /**
     * @param array<mixed> $items
     */
    public function first(array $items): mixed
    {
        if ($items === []) {
            return null;
        }

        return reset($items);
    }

    /**
     * @param array<mixed> $items
     */
    public function last(array $items): mixed
    {
        if ($items === []) {
            return null;
        }

        return end($items);
    }
}
