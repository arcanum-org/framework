<?php

declare(strict_types=1);

namespace Arcanum\Flow\Sequence;

use Arcanum\Glitch\ArcanumException;

/**
 * Thrown when a Cursor is iterated, materialized, or peeked a second time.
 *
 * Cursors are single-pass by contract. If you need multi-pass access, call
 * {@see Cursor::toSeries()} on a fresh cursor to materialize it into a
 * {@see Series} first.
 */
final class CursorAlreadyConsumed extends \LogicException implements ArcanumException
{
    public static function create(): self
    {
        return new self(
            'Cursor has already been consumed. Cursors are single-pass; '
            . 'call toSeries() on a fresh cursor to materialize a multi-pass Series.'
        );
    }

    public function getTitle(): string
    {
        return 'Cursor Already Consumed';
    }

    public function getSuggestion(): string
    {
        return 'Cursors iterate exactly once. Call toSeries() on a fresh cursor '
            . 'before any other operation to materialize a multi-pass Series.';
    }
}
