<?php

declare(strict_types=1);

namespace Arcanum\Flow\Sequence;

/**
 * Lazy, single-pass {@see Sequencer}.
 *
 * Wraps a generator-producing closure plus a close callback. The cursor
 * iterates the generator at most once and guarantees the close callback runs
 * exactly once across every code path: full iteration, early break, thrown
 * exception, or destruction without iteration.
 *
 * Lazy operations ({@see map}, {@see filter}, {@see chunk}, {@see take})
 * mark the parent cursor consumed and return a new cursor sharing the same
 * close latch. After deriving, the parent handle is dead — use the returned
 * cursor.
 *
 * @template-covariant T
 * @implements Sequencer<T>
 */
final class Cursor implements Sequencer
{
    private bool $consumed = false;

    /**
     * @param \Closure(): \Generator<int, T> $source
     */
    public function __construct(
        private readonly \Closure $source,
        private readonly CloseLatch $latch,
    ) {
    }

    /**
     * Open a cursor over a generator-producing closure.
     *
     * @template U
     * @param \Closure(): \Generator<int, U> $source
     * @param \Closure(): void $onClose
     * @return Cursor<U>
     */
    public static function open(\Closure $source, \Closure $onClose): self
    {
        return new self($source, new CloseLatch($onClose));
    }

    /**
     * @return \Traversable<int, T>
     */
    public function getIterator(): \Traversable
    {
        if ($this->consumed) {
            throw CursorAlreadyConsumed::create();
        }
        $this->consumed = true;
        $source = $this->source;
        try {
            $index = 0;
            foreach ($source() as $item) {
                yield $index++ => $item;
            }
        } finally {
            $this->latch->close();
        }
    }

    public function first(): mixed
    {
        if ($this->consumed) {
            throw CursorAlreadyConsumed::create();
        }
        $this->consumed = true;
        $source = $this->source;
        try {
            foreach ($source() as $item) {
                return $item;
            }
            return null;
        } finally {
            $this->latch->close();
        }
    }

    public function each(callable $callback): void
    {
        foreach ($this as $item) {
            $callback($item);
        }
    }

    /**
     * @template U
     * @param callable(T): U $callback
     * @return Cursor<U>
     */
    public function map(callable $callback): Cursor
    {
        if ($this->consumed) {
            throw CursorAlreadyConsumed::create();
        }
        $this->consumed = true;
        $source = $this->source;

        /** @var \Closure(): \Generator<int, U> $derived */
        $derived = static function () use ($source, $callback): \Generator {
            foreach ($source() as $item) {
                yield $callback($item);
            }
        };

        return new self($derived, $this->latch);
    }

    /**
     * @param callable(T): bool $callback
     * @return Cursor<T>
     */
    public function filter(callable $callback): Cursor
    {
        if ($this->consumed) {
            throw CursorAlreadyConsumed::create();
        }
        $this->consumed = true;
        $source = $this->source;

        /** @var \Closure(): \Generator<int, T> $derived */
        $derived = static function () use ($source, $callback): \Generator {
            foreach ($source() as $item) {
                if ($callback($item)) {
                    yield $item;
                }
            }
        };

        return new self($derived, $this->latch);
    }

    /**
     * @param int<1, max> $size
     * @return Cursor<list<T>>
     */
    public function chunk(int $size): Cursor
    {
        /** @phpstan-ignore smaller.alwaysFalse */
        if ($size < 1) {
            throw new \InvalidArgumentException('Chunk size must be at least 1.');
        }
        if ($this->consumed) {
            throw CursorAlreadyConsumed::create();
        }
        $this->consumed = true;
        $source = $this->source;

        $derived = static function () use ($source, $size): \Generator {
            /** @var list<T> $buffer */
            $buffer = [];
            foreach ($source() as $item) {
                $buffer[] = $item;
                if (count($buffer) === $size) {
                    yield $buffer;
                    $buffer = [];
                }
            }
            if ($buffer !== []) {
                yield $buffer;
            }
        };

        return new self($derived, $this->latch);
    }

    /**
     * @param int<0, max> $count
     * @return Cursor<T>
     */
    public function take(int $count): Cursor
    {
        /** @phpstan-ignore smaller.alwaysFalse */
        if ($count < 0) {
            throw new \InvalidArgumentException('Take count must be zero or greater.');
        }
        if ($this->consumed) {
            throw CursorAlreadyConsumed::create();
        }
        $this->consumed = true;
        $source = $this->source;

        /** @var \Closure(): \Generator<int, T> $derived */
        $derived = static function () use ($source, $count): \Generator {
            if ($count === 0) {
                return;
            }
            $taken = 0;
            foreach ($source() as $item) {
                yield $item;
                if (++$taken === $count) {
                    return;
                }
            }
        };

        return new self($derived, $this->latch);
    }

    /**
     * @return Series<T>
     */
    public function toSeries(): Series
    {
        if ($this->consumed) {
            throw CursorAlreadyConsumed::create();
        }
        $this->consumed = true;
        $source = $this->source;
        try {
            /** @var list<T> $items */
            $items = [];
            foreach ($source() as $item) {
                $items[] = $item;
            }
            return new Series($items);
        } finally {
            $this->latch->close();
        }
    }

    /**
     * Close the underlying resource. Idempotent.
     */
    public function close(): void
    {
        $this->latch->close();
    }

    public function __destruct()
    {
        if (!$this->consumed) {
            $this->latch->close();
        }
    }
}
