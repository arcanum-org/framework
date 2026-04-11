<?php

declare(strict_types=1);

namespace Arcanum\Flow\Conveyor;

interface Bus
{
    /**
     * Dispatch an object to a handler.
     *
     * The object to be dispatched should be a simple DTO, and the
     * return value should be a simple DTO.
     *
     * @param string $prefix Handler name prefix. When non-empty, the bus
     *                       tries the prefixed handler first (e.g., prefix
     *                       'Delete' + DoSomething → DeleteDoSomethingHandler)
     *                       and falls back to the unprefixed handler.
     */
    public function dispatch(object $object, string $prefix = ''): object;
}
