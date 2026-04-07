<?php

declare(strict_types=1);

namespace Arcanum\Flow\Sequence;

/**
 * Thrown when a Cursor is iterated, materialized, or peeked a second time.
 *
 * Cursors are single-pass by contract. If you need multi-pass access, call
 * {@see Cursor::toSeries()} on a fresh cursor to materialize it into a
 * {@see Series} first.
 */
final class CursorAlreadyConsumed extends \LogicException
{
    public static function create(): self
    {
        return new self(
            'Cursor has already been consumed. Cursors are single-pass; '
            . 'call toSeries() on a fresh cursor to materialize a multi-pass Series.'
        );
    }
}
