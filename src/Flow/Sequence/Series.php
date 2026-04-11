<?php

declare(strict_types=1);

namespace Arcanum\Flow\Sequence;

/**
 * Eager, multi-pass {@see Sequencer} backed by a list.
 *
 * @template-covariant T
 * @implements Sequencer<T>
 */
final class Series implements Sequencer
{
    /**
     * @param list<T> $items
     */
    public function __construct(private readonly array $items)
    {
    }

    /**
     * @return \Traversable<int, T>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->items;
    }

    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    public function each(callable $callback): void
    {
        foreach ($this->items as $item) {
            $callback($item);
        }
    }

    /**
     * @template U
     * @param callable(T): U $callback
     * @return Series<U>
     */
    public function map(callable $callback): Series
    {
        return new Series(array_map($callback, $this->items));
    }

    /**
     * @param callable(T): bool $callback
     * @return Series<T>
     */
    public function filter(callable $callback): Series
    {
        return new Series(array_values(array_filter($this->items, $callback)));
    }

    /**
     * @param int<1, max> $size
     * @return Series<list<T>>
     */
    public function chunk(int $size): Series
    {
        /** @phpstan-ignore smaller.alwaysFalse */
        if ($size < 1) {
            throw new \InvalidArgumentException('Chunk size must be at least 1.');
        }

        return new Series(array_chunk($this->items, $size));
    }

    /**
     * @param int<0, max> $count
     * @return Series<T>
     */
    public function take(int $count): Series
    {
        /** @phpstan-ignore smaller.alwaysFalse */
        if ($count < 0) {
            throw new \InvalidArgumentException('Take count must be zero or greater.');
        }

        return new Series(array_slice($this->items, 0, $count));
    }

    /**
     * @return Series<T>
     */
    public function toSeries(): Series
    {
        return $this;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return list<T>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
