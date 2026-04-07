<?php

declare(strict_types=1);

namespace Arcanum\Flow\Sequence;

/**
 * Idempotent close latch shared between a {@see Cursor} and any cursors
 * derived from it via lazy operations.
 *
 * The latch holds a callback and a one-shot guard. {@see close()} runs the
 * callback the first time it is called and is a no-op on every subsequent
 * call. This guarantees an underlying resource is released exactly once
 * regardless of which derived cursor's iteration, exception, or destructor
 * fires first.
 *
 * @internal
 */
final class CloseLatch
{
    private bool $closed = false;

    /**
     * @param \Closure(): void $onClose
     */
    public function __construct(private readonly \Closure $onClose)
    {
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        ($this->onClose)();
    }
}
