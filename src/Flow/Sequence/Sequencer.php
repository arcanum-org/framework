<?php

declare(strict_types=1);

namespace Arcanum\Flow\Sequence;

/**
 * Abstract ordered iterable.
 *
 * Two shapes implement this interface: {@see Cursor} (lazy, single-pass) and
 * {@see Series} (eager, multi-pass). The interface contains only operations
 * that are honest on both shapes — anything that would force a Cursor to
 * silently materialize lives on Series alone.
 *
 * @template-covariant T
 * @extends \IteratorAggregate<int, T>
 */
interface Sequencer extends \IteratorAggregate
{
    /**
     * Return the first element, or null if the sequence is empty.
     *
     * O(1) on both shapes. On a Cursor this peeks one row, closes the
     * cursor, and marks it consumed.
     *
     * @return T|null
     */
    public function first(): mixed;

    /**
     * Apply a callback to every element. Terminal.
     *
     * @param callable(T): void $callback
     */
    public function each(callable $callback): void;

    /**
     * Map every element through a callback.
     *
     * Lazy on Cursor, eager on Series.
     *
     * @template U
     * @param callable(T): U $callback
     * @return Sequencer<U>
     */
    public function map(callable $callback): Sequencer;

    /**
     * Keep only elements for which the callback returns true.
     *
     * Lazy on Cursor, eager on Series.
     *
     * @param callable(T): bool $callback
     * @return Sequencer<T>
     */
    public function filter(callable $callback): Sequencer;

    /**
     * Group elements into chunks of the given size. Final chunk may be smaller.
     *
     * Lazy on Cursor, eager on Series.
     *
     * @param int<1, max> $size
     * @return Sequencer<list<T>>
     */
    public function chunk(int $size): Sequencer;

    /**
     * Yield at most the first $count elements.
     *
     * Lazy on Cursor, eager on Series.
     *
     * @param int<0, max> $count
     * @return Sequencer<T>
     */
    public function take(int $count): Sequencer;

    /**
     * Materialize into a multi-pass Series.
     *
     * On a Cursor this walks the underlying generator into a list and returns
     * a fresh Series; throws {@see CursorAlreadyConsumed} if the cursor has
     * already been iterated. On a Series this returns $this.
     *
     * @return Series<T>
     */
    public function toSeries(): Series;
}
