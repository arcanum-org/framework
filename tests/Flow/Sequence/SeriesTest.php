<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Arcanum\Flow\Sequence\Series;

#[CoversClass(Series::class)]
final class SeriesTest extends TestCase
{
    public function testCountReturnsLength(): void
    {
        $series = new Series([1, 2, 3]);

        $this->assertSame(3, $series->count());
    }

    public function testAllReturnsBackingList(): void
    {
        $series = new Series(['a', 'b', 'c']);

        $this->assertSame(['a', 'b', 'c'], $series->all());
    }

    public function testIsEmptyTrueForEmptyList(): void
    {
        $series = new Series([]);

        $this->assertTrue($series->isEmpty());
    }

    public function testIsEmptyFalseForNonEmptyList(): void
    {
        $series = new Series([0]);

        $this->assertFalse($series->isEmpty());
    }

    public function testMultiPassIterationWorks(): void
    {
        $series = new Series([1, 2, 3]);

        $first = iterator_to_array($series);
        $second = iterator_to_array($series);

        $this->assertSame($first, $second);
    }

    public function testToSeriesReturnsSameInstance(): void
    {
        $series = new Series([1, 2]);

        $this->assertSame($series, $series->toSeries());
    }

    public function testMapReturnsNewSeries(): void
    {
        $series = new Series([1, 2, 3]);

        $mapped = $series->map(static fn(int $n): int => $n + 1);

        $this->assertNotSame($series, $mapped);
        $this->assertSame([1, 2, 3], $series->all());
        $this->assertSame([2, 3, 4], $mapped->all());
    }

    public function testFilterReturnsNewSeries(): void
    {
        $series = new Series([1, 2, 3, 4]);

        $filtered = $series->filter(static fn(int $n): bool => $n > 2);

        $this->assertNotSame($series, $filtered);
        $this->assertSame([3, 4], $filtered->all());
    }

    public function testChunkReturnsNewSeries(): void
    {
        $series = new Series([1, 2, 3, 4, 5]);

        $chunks = $series->chunk(2);

        $this->assertSame([[1, 2], [3, 4], [5]], $chunks->all());
    }

    public function testTakeReturnsNewSeries(): void
    {
        $series = new Series([1, 2, 3]);

        $taken = $series->take(2);

        $this->assertSame([1, 2], $taken->all());
    }

    public function testChunkRejectsZeroSize(): void
    {
        $series = new Series([1]);

        $this->expectException(\InvalidArgumentException::class);

        /** @phpstan-ignore argument.type */
        $series->chunk(0);
    }

    public function testTakeRejectsNegativeCount(): void
    {
        $series = new Series([1]);

        $this->expectException(\InvalidArgumentException::class);

        /** @phpstan-ignore argument.type */
        $series->take(-1);
    }

    public function testEachVisitsItems(): void
    {
        $series = new Series([10, 20]);

        $visited = [];
        $series->each(function (int $n) use (&$visited): void {
            $visited[] = $n;
        });

        $this->assertSame([10, 20], $visited);
    }

    public function testFirstOnEmpty(): void
    {
        /** @var Series<int> $series */
        $series = new Series([]);

        $this->assertNull($series->first());
    }
}
