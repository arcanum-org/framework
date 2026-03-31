<?php

declare(strict_types=1);

namespace Arcanum\Flow\Conveyor;

/**
 * A DTO that overrides the default handler resolution convention.
 *
 * Normally, Conveyor derives the handler class from get_class($dto).
 * Objects implementing HandlerProxy provide an explicit base name
 * for handler resolution instead, allowing dynamic or proxy DTOs
 * to dispatch to the correct handler.
 */
interface HandlerProxy
{
    /**
     * The fully-qualified class name to use as the base for handler resolution.
     *
     * The handler will be derived from this name using the standard convention:
     * base name + optional prefix + 'Handler'.
     */
    public function handlerBaseName(): string;
}
